<?php
/**
 * ClusterifyAI ChatBot product page data provider
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Model\Sync\Provider;

use ClusterifyAI\ChatBot\Model\Config;
use ClusterifyAI\ChatBot\Model\Sync\DataProviderInterface;
use ClusterifyAI\ChatBot\Model\Sync\HtmlToMarkdown;
use ClusterifyAI\ChatBot\Model\Sync\SyncItem;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Psr\Log\LoggerInterface;

/**
 * Class ProductDataProvider
 *
 * Extracts product metadata, attributes, stock status, and descriptions into structured Markdown knowledge.
 * Designed to be easily extendable with custom attributes and product type handlers.
 */
class ProductDataProvider implements DataProviderInterface
{
    public const ENTITY_TYPE = 'product';

    /**
     * @param ProductRepositoryInterface $productRepository Product repository
     * @param StockRegistryInterface     $stockRegistry     Stock registry for inventory status
     * @param PriceCurrencyInterface     $priceCurrency     Price currency formatter
     * @param HtmlToMarkdown             $htmlConverter     HTML to Markdown converter
     * @param Config                     $config            Module configuration service
     * @param LoggerInterface            $logger            Logger
     */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StockRegistryInterface $stockRegistry,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly HtmlToMarkdown $htmlConverter,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * @inheritdoc
     */
    public function getEntityType(): string
    {
        return self::ENTITY_TYPE;
    }

    /**
     * @inheritdoc
     */
    public function extract(int $entityId, int $storeId): ?SyncItem
    {
        try {
            /** @var \Magento\Catalog\Model\Product $product */
            $product = $this->productRepository->getById($entityId, false, $storeId);

            // Resolve canonical product URL ignoring any category path prefixes
            $url = '';
            try {
                $url = (string) $product->getUrlModel()->getUrl($product, ['_ignore_category' => true]);
            } catch (\Throwable) {
                // Fallback if URL model fails to initialize
            }

            if ($url === '') {
                $url = (string) $product->getProductUrl(false);
            }

            $url = strtok($url, '?');
            $url = trim(strtok((string) $url, '#'));
            if ($url === '') {
                return null;
            }

            $status = (int) $product->getStatus();
            $visibility = (int) $product->getVisibility();

            // If product is disabled or hidden individually, issue delete action to purge URL
            if ($status !== Status::STATUS_ENABLED || $visibility === Visibility::VISIBILITY_NOT_VISIBLE) {
                return new SyncItem(
                    entityType: self::ENTITY_TYPE,
                    entityId: $entityId,
                    storeId: $storeId,
                    url: $url,
                    content: '',
                    isEnabled: false,
                    action: SyncItem::ACTION_DELETE
                );
            }

            // Stock evaluation (lazy-loaded only when in_stock_only filter or availability display is enabled)
            $needsStock = $this->config->isInStockOnlySyncEnabled($storeId)
                || $this->config->isProductAvailabilitySyncEnabled($storeId);
            $isInStock = $needsStock ? $this->isProductInStock($product) : true;

            // If in-stock filter is enabled and product is out of stock, purge URL to conserve quota
            if ($this->config->isInStockOnlySyncEnabled($storeId) && !$isInStock) {
                return new SyncItem(
                    entityType: self::ENTITY_TYPE,
                    entityId: $entityId,
                    storeId: $storeId,
                    url: $url,
                    content: '',
                    isEnabled: false,
                    action: SyncItem::ACTION_DELETE
                );
            }

            $name = trim((string) $product->getName());
            $sku = (string) $product->getSku();
            $typeId = (string) $product->getTypeId();

            // Descriptions
            $rawShortDesc = (string) ($product->getData('short_description') ?? '');
            $rawFullDesc = (string) ($product->getData('description') ?? '');

            $shortMarkdown = $this->htmlConverter->convert($rawShortDesc);
            $fullMarkdown = $this->htmlConverter->convert($rawFullDesc);

            // Construct structured Markdown
            $markdown = sprintf("# %s\n", $name);
            $markdown .= sprintf("- **SKU**: %s\n", $sku);

            // Price (optional, enabled via configuration)
            if ($this->config->isProductPriceSyncEnabled($storeId)) {
                try {
                    $finalPrice = (float) $product->getPriceInfo()->getPrice('final_price')->getValue();
                    $formattedPrice = $this->priceCurrency->convertAndFormat(
                        $finalPrice,
                        false,
                        PriceCurrencyInterface::DEFAULT_PRECISION,
                        $storeId
                    );
                    $markdown .= sprintf("- **Price**: %s\n", $formattedPrice);
                } catch (\Throwable $e) {
                    $this->logger->debug(sprintf(
                        'Clusterify ProductDataProvider: Price extraction failed for product ID %d (store ID %d): %s',
                        $entityId,
                        $storeId,
                        $e->getMessage()
                    ));
                }
            }

            // Availability status (optional, enabled via configuration)
            if ($this->config->isProductAvailabilitySyncEnabled($storeId)) {
                $availability = $isInStock ? 'In Stock' : 'Out of Stock';
                $markdown .= sprintf("- **Availability**: %s\n", $availability);
            }

            // Configurable options summary
            if ($typeId === 'configurable') {
                $optionsList = $this->extractConfigurableOptions($product);
                if (!empty($optionsList)) {
                    $markdown .= sprintf("- **Options**: %s\n", implode('; ', $optionsList));
                }
            }

            // Custom AI training knowledge attribute
            $customKnowledge = trim((string) ($product->getData('clusterify_chatbot_knowledge') ?? ''));
            if ($customKnowledge !== '' && mb_strlen($customKnowledge) > 20000) {
                $customKnowledge = mb_substr($customKnowledge, 0, 20000);
            }

            $prioritizeCustomKnowledge = $this->config->isCustomKnowledgeOnlySyncEnabled($storeId);

            if ($prioritizeCustomKnowledge && $customKnowledge !== '') {
                // When custom knowledge is present and prioritized, sync it instead of core descriptions
                $markdown .= sprintf("\n## AI Knowledge & Context\n%s\n", $customKnowledge);
            } else {
                // Standard descriptions (and fallback if custom knowledge is empty)
                if ($shortMarkdown !== '') {
                    $markdown .= sprintf("\n## Summary\n%s\n", $shortMarkdown);
                }

                if ($fullMarkdown !== '' && $fullMarkdown !== $shortMarkdown) {
                    $markdown .= sprintf("\n## Description\n%s\n", $fullMarkdown);
                }

                if (!$prioritizeCustomKnowledge && $customKnowledge !== '') {
                    $markdown .= sprintf("\n## AI Knowledge & Context\n%s\n", $customKnowledge);
                }
            }

            return new SyncItem(
                entityType: self::ENTITY_TYPE,
                entityId: $entityId,
                storeId: $storeId,
                url: $url,
                content: trim($markdown),
                isEnabled: true,
                action: SyncItem::ACTION_UPSERT
            );
        } catch (\Throwable $e) {
            $this->logger->warning(sprintf(
                'Clusterify ProductDataProvider failed to extract product ID %d for store ID %d: %s',
                $entityId,
                $storeId,
                $e->getMessage()
            ));
            return null;
        }
    }

    /**
     * @inheritdoc
     */
    public function extractBatch(array $entityIds, int $storeId): array
    {
        $items = [];
        foreach ($entityIds as $id) {
            $item = $this->extract((int) $id, $storeId);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Determine product stock availability across Multi-Source Inventory (MSI) and legacy CatalogInventory.
     *
     * @param ProductInterface $product Product instance to evaluate
     * @return bool True if the product is in stock and salable, false otherwise
     */
    protected function isProductInStock(ProductInterface $product): bool
    {
        try {
            // Evaluates multi-source stock channels when MSI is active
            if (method_exists($product, 'isSalable')) {
                return (bool) $product->isSalable();
            }
        } catch (\Throwable $e) {
            $this->logger->debug(sprintf(
                'Clusterify ProductDataProvider: isSalable check failed for product ID %d: %s. Falling back to StockRegistry.',
                (int) $product->getId(),
                $e->getMessage()
            ));
        }

        try {
            // Fallback to stock registry for legacy catalog inventory or environments without MSI
            $stockItem = $this->stockRegistry->getStockItem((int) $product->getId());
            return (bool) $stockItem->getIsInStock();
        } catch (\Throwable $e) {
            $this->logger->warning(sprintf(
                'Clusterify ProductDataProvider: StockRegistry fallback failed for product ID %d: %s',
                (int) $product->getId(),
                $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Extract configurable attributes summary including available option values.
     *
     * @param ProductInterface $product
     * @return list<string>
     */
    private function extractConfigurableOptions(ProductInterface $product): array
    {
        $summary = [];
        try {
            /** @var \Magento\ConfigurableProduct\Model\Product\Type\Configurable $typeInstance */
            $typeInstance = $product->getTypeInstance();
            $attributes = $typeInstance->getConfigurableAttributes($product);

            foreach ($attributes as $attribute) {
                $label = (string) $attribute->getProductAttribute()->getFrontendLabel();
                $options = [];
                foreach ((array) $attribute->getOptions() as $opt) {
                    $val = trim((string) ($opt['label'] ?? $opt['store_label'] ?? ''));
                    if ($val !== '') {
                        $options[] = $val;
                    }
                }
                if (!empty($options)) {
                    $summary[] = sprintf('%s: %s', $label, implode(', ', $options));
                } else {
                    $summary[] = $label;
                }
            }
        } catch (\Throwable $e) {
            // Non-blocking fallback
        }

        return $summary;
    }
}
