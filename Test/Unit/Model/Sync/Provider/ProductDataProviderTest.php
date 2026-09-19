<?php
/**
 * ClusterifyAI ChatBot product data provider unit test
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Test\Unit\Model\Sync\Provider;

use ClusterifyAI\ChatBot\Model\Config;
use ClusterifyAI\ChatBot\Model\Sync\HtmlToMarkdown;
use ClusterifyAI\ChatBot\Model\Sync\Provider\ProductDataProvider;
use ClusterifyAI\ChatBot\Model\Sync\SyncItem;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Pricing\PriceInfo\Base as PriceInfo;
use Magento\Framework\Pricing\Price\PriceInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Class ProductDataProviderTest
 *
 * Unit tests for ClusterifyAI\ChatBot\Model\Sync\Provider\ProductDataProvider.
 */
class ProductDataProviderTest extends TestCase
{
    private ProductRepositoryInterface $productRepositoryStub;
    private StockRegistryInterface $stockRegistryStub;
    private PriceCurrencyInterface $priceCurrencyStub;
    private HtmlToMarkdown $htmlConverter;
    private Config $configStub;
    private LoggerInterface $loggerStub;
    private ProductDataProvider $provider;

    /**
     * Set up test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->productRepositoryStub = $this->createStub(ProductRepositoryInterface::class);
        $this->stockRegistryStub = $this->createStub(StockRegistryInterface::class);
        $this->priceCurrencyStub = $this->createStub(PriceCurrencyInterface::class);
        $this->priceCurrencyStub->method('convertAndFormat')->willReturnCallback(
            static fn ($amount): string => '$' . number_format((float) $amount, 2)
        );
        $this->priceCurrencyStub->method('format')->willReturnCallback(
            static fn ($amount): string => '$' . number_format((float) $amount, 2)
        );
        $this->htmlConverter = new HtmlToMarkdown();
        $this->configStub = $this->createStub(Config::class);
        $this->loggerStub = $this->createStub(LoggerInterface::class);

        $this->provider = new ProductDataProvider(
            $this->productRepositoryStub,
            $this->stockRegistryStub,
            $this->priceCurrencyStub,
            $this->htmlConverter,
            $this->configStub,
            $this->loggerStub
        );
    }

    /**
     * Test disabled product emits ACTION_DELETE.
     *
     * @return void
     */
    public function testExtractDisabledProductEmitsDelete(): void
    {
        $productStub = $this->createStub(Product::class);
        $productStub->method('getProductUrl')->willReturn('http://magento.test/bag.html');
        $productStub->method('getStatus')->willReturn(Status::STATUS_DISABLED);
        $productStub->method('getVisibility')->willReturn(Visibility::VISIBILITY_BOTH);

        $this->productRepositoryStub->method('getById')->willReturn($productStub);

        $result = $this->provider->extract(1, 1);
        $this->assertInstanceOf(SyncItem::class, $result);
        $this->assertSame(SyncItem::ACTION_DELETE, $result->action);
    }

    /**
     * Test hidden product (not visible individually) emits ACTION_DELETE.
     *
     * @return void
     */
    public function testExtractHiddenProductEmitsDelete(): void
    {
        $productStub = $this->createStub(Product::class);
        $productStub->method('getProductUrl')->willReturn('http://magento.test/variant.html');
        $productStub->method('getStatus')->willReturn(Status::STATUS_ENABLED);
        $productStub->method('getVisibility')->willReturn(Visibility::VISIBILITY_NOT_VISIBLE);

        $this->productRepositoryStub->method('getById')->willReturn($productStub);

        $result = $this->provider->extract(150, 1);
        $this->assertInstanceOf(SyncItem::class, $result);
        $this->assertSame(SyncItem::ACTION_DELETE, $result->action);
    }

    /**
     * Test out of stock product emits ACTION_DELETE when in-stock filter is enabled.
     *
     * @return void
     */
    public function testExtractOutOfStockProductEmitsDeleteWhenFilterEnabled(): void
    {
        $productStub = $this->createStub(Product::class);
        $productStub->method('getId')->willReturn(10);
        $productStub->method('getProductUrl')->willReturn('http://magento.test/out-of-stock.html');
        $productStub->method('getStatus')->willReturn(Status::STATUS_ENABLED);
        $productStub->method('getVisibility')->willReturn(Visibility::VISIBILITY_BOTH);
        $productStub->method('isSalable')->willReturn(false);

        $stockItemStub = $this->createStub(StockItemInterface::class);
        $stockItemStub->method('getIsInStock')->willReturn(false);

        $this->stockRegistryStub->method('getStockItem')->willReturn($stockItemStub);
        $this->productRepositoryStub->method('getById')->willReturn($productStub);
        $this->configStub->method('isInStockOnlySyncEnabled')->willReturn(true);

        $result = $this->provider->extract(10, 1);
        $this->assertInstanceOf(SyncItem::class, $result);
        $this->assertSame(SyncItem::ACTION_DELETE, $result->action);
    }

    /**
     * Test active visible product emits ACTION_UPSERT with structured Markdown.
     *
     * @return void
     */
    public function testExtractActiveProductEmitsUpsert(): void
    {
        $productStub = $this->createStub(Product::class);
        $productStub->method('getId')->willReturn(1);
        $productStub->method('getName')->willReturn('Joust Duffle Bag');
        $productStub->method('getSku')->willReturn('24-MB01');
        $productStub->method('getTypeId')->willReturn('simple');
        $productStub->method('getProductUrl')->willReturn('http://magento.test/joust-duffle-bag.html');
        $productStub->method('getStatus')->willReturn(Status::STATUS_ENABLED);
        $productStub->method('getVisibility')->willReturn(Visibility::VISIBILITY_BOTH);
        $productStub->method('isSalable')->willReturn(true);
        $productStub->method('getData')->willReturnCallback(function (string $key) {
            return match ($key) {
                'description' => '<p>Top-quality duffle bag.</p>',
                'short_description' => '<p>Duffle bag.</p>',
                default => null,
            };
        });

        $priceInfoStub = $this->createStub(PriceInfo::class);
        $priceStub = $this->createStub(PriceInterface::class);
        $priceStub->method('getValue')->willReturn(34.00);
        $priceInfoStub->method('getPrice')->willReturn($priceStub);
        $productStub->method('getPriceInfo')->willReturn($priceInfoStub);

        $this->priceCurrencyStub->method('format')->willReturn('$34.00');

        $stockItemStub = $this->createStub(StockItemInterface::class);
        $stockItemStub->method('getIsInStock')->willReturn(true);
        $this->stockRegistryStub->method('getStockItem')->willReturn($stockItemStub);

        $this->configStub->method('isProductPriceSyncEnabled')->willReturn(true);
        $this->configStub->method('isProductAvailabilitySyncEnabled')->willReturn(true);

        $this->productRepositoryStub->method('getById')->willReturn($productStub);

        $result = $this->provider->extract(1, 1);
        $this->assertInstanceOf(SyncItem::class, $result);
        $this->assertSame(SyncItem::ACTION_UPSERT, $result->action);
        $this->assertSame('http://magento.test/joust-duffle-bag.html', $result->url);
        $this->assertStringContainsString('# Joust Duffle Bag', $result->content);
        $this->assertStringContainsString('- **SKU**: 24-MB01', $result->content);
        $this->assertStringContainsString('- **Price**: $34.00', $result->content);
        $this->assertStringContainsString('- **Availability**: In Stock', $result->content);
    }

    /**
     * Test active product with custom knowledge attribute appends AI context section.
     *
     * @return void
     */
    public function testExtractActiveProductIncludesCustomKnowledge(): void
    {
        $productStub = $this->createStub(Product::class);
        $productStub->method('getId')->willReturn(2);
        $productStub->method('getName')->willReturn('Compete Track Tote');
        $productStub->method('getSku')->willReturn('24-WB02');
        $productStub->method('getTypeId')->willReturn('simple');
        $productStub->method('getProductUrl')->willReturn('http://magento.test/compete-track-tote.html');
        $productStub->method('getStatus')->willReturn(Status::STATUS_ENABLED);
        $productStub->method('getVisibility')->willReturn(Visibility::VISIBILITY_BOTH);
        $productStub->method('isSalable')->willReturn(true);
        $productStub->method('getData')->willReturnCallback(function (string $key) {
            return match ($key) {
                'description' => '<p>Durable tote bag.</p>',
                'short_description' => '',
                'clusterify_chatbot_knowledge' => "Why buy: Made of tear-resistant nylon.\nFit: Fits up to 15-inch laptops.",
                default => null,
            };
        });

        $priceInfoStub = $this->createStub(PriceInfo::class);
        $priceStub = $this->createStub(PriceInterface::class);
        $priceStub->method('getValue')->willReturn(32.00);
        $priceInfoStub->method('getPrice')->willReturn($priceStub);
        $productStub->method('getPriceInfo')->willReturn($priceInfoStub);

        $this->priceCurrencyStub->method('format')->willReturn('$32.00');

        $stockItemStub = $this->createStub(StockItemInterface::class);
        $stockItemStub->method('getIsInStock')->willReturn(true);
        $this->stockRegistryStub->method('getStockItem')->willReturn($stockItemStub);

        $this->productRepositoryStub->method('getById')->willReturn($productStub);

        $result = $this->provider->extract(2, 1);
        $this->assertInstanceOf(SyncItem::class, $result);
        $this->assertStringContainsString('## AI Knowledge & Context', $result->content);
        $this->assertStringContainsString('Why buy: Made of tear-resistant nylon.', $result->content);
        $this->assertStringContainsString('Fit: Fits up to 15-inch laptops.', $result->content);
    }

    /**
     * Test MSI salable product returns in-stock without falling back to StockRegistry.
     *
     * @return void
     */
    public function testExtractMsiSalableProductTrue(): void
    {
        $productStub = $this->createStub(Product::class);
        $productStub->method('getId')->willReturn(15);
        $productStub->method('getName')->willReturn('MSI In Stock Item');
        $productStub->method('getSku')->willReturn('MSI-01');
        $productStub->method('getTypeId')->willReturn('simple');
        $productStub->method('getProductUrl')->willReturn('http://magento.test/msi-item.html');
        $productStub->method('getStatus')->willReturn(Status::STATUS_ENABLED);
        $productStub->method('getVisibility')->willReturn(Visibility::VISIBILITY_BOTH);
        $productStub->method('isSalable')->willReturn(true);

        $priceInfoStub = $this->createStub(PriceInfo::class);
        $priceStub = $this->createStub(PriceInterface::class);
        $priceStub->method('getValue')->willReturn(19.99);
        $priceInfoStub->method('getPrice')->willReturn($priceStub);
        $productStub->method('getPriceInfo')->willReturn($priceInfoStub);

        $this->priceCurrencyStub->method('format')->willReturn('$19.99');
        $this->productRepositoryStub->method('getById')->willReturn($productStub);

        $stockRegistryMock = $this->createMock(StockRegistryInterface::class);
        $stockRegistryMock->expects($this->never())->method('getStockItem');

        $configStub = $this->createStub(Config::class);
        $configStub->method('isProductAvailabilitySyncEnabled')->willReturn(true);

        $provider = new ProductDataProvider(
            $this->productRepositoryStub,
            $stockRegistryMock,
            $this->priceCurrencyStub,
            $this->htmlConverter,
            $configStub,
            $this->loggerStub
        );

        $result = $provider->extract(15, 1);
        $this->assertInstanceOf(SyncItem::class, $result);
        $this->assertSame(SyncItem::ACTION_UPSERT, $result->action);
        $this->assertStringContainsString('- **Availability**: In Stock', $result->content);
    }

    /**
     * Test MSI out-of-stock product does not fallback to stale StockRegistry.
     *
     * @return void
     */
    public function testExtractMsiSalableProductFalseEmitsDeleteWhenFilterEnabled(): void
    {
        $productStub = $this->createStub(Product::class);
        $productStub->method('getId')->willReturn(16);
        $productStub->method('getProductUrl')->willReturn('http://magento.test/msi-oos.html');
        $productStub->method('getStatus')->willReturn(Status::STATUS_ENABLED);
        $productStub->method('getVisibility')->willReturn(Visibility::VISIBILITY_BOTH);
        $productStub->method('isSalable')->willReturn(false);

        $this->productRepositoryStub->method('getById')->willReturn($productStub);
        $this->configStub->method('isInStockOnlySyncEnabled')->willReturn(true);

        $stockRegistryMock = $this->createMock(StockRegistryInterface::class);
        $stockRegistryMock->expects($this->never())->method('getStockItem');

        $provider = new ProductDataProvider(
            $this->productRepositoryStub,
            $stockRegistryMock,
            $this->priceCurrencyStub,
            $this->htmlConverter,
            $this->configStub,
            $this->loggerStub
        );

        $result = $provider->extract(16, 1);
        $this->assertInstanceOf(SyncItem::class, $result);
        $this->assertSame(SyncItem::ACTION_DELETE, $result->action);
    }

    /**
     * Test fallback to StockRegistry when isSalable throws an unexpected exception.
     *
     * @return void
     */
    public function testExtractFallbackToStockRegistryWhenIsSalableThrows(): void
    {
        $productStub = $this->createStub(Product::class);
        $productStub->method('getId')->willReturn(17);
        $productStub->method('getName')->willReturn('Fallback Item');
        $productStub->method('getSku')->willReturn('FB-01');
        $productStub->method('getTypeId')->willReturn('simple');
        $productStub->method('getProductUrl')->willReturn('http://magento.test/fallback-item.html');
        $productStub->method('getStatus')->willReturn(Status::STATUS_ENABLED);
        $productStub->method('getVisibility')->willReturn(Visibility::VISIBILITY_BOTH);
        $productStub->method('isSalable')->willThrowException(new \RuntimeException('MSI channel error'));

        $priceInfoStub = $this->createStub(PriceInfo::class);
        $priceStub = $this->createStub(PriceInterface::class);
        $priceStub->method('getValue')->willReturn(25.00);
        $priceInfoStub->method('getPrice')->willReturn($priceStub);
        $productStub->method('getPriceInfo')->willReturn($priceInfoStub);

        $this->priceCurrencyStub->method('format')->willReturn('$25.00');

        $stockItemStub = $this->createStub(StockItemInterface::class);
        $stockItemStub->method('getIsInStock')->willReturn(true);
        $this->stockRegistryStub->method('getStockItem')->willReturn($stockItemStub);

        $configStub = $this->createStub(Config::class);
        $configStub->method('isProductAvailabilitySyncEnabled')->willReturn(true);

        $this->productRepositoryStub->method('getById')->willReturn($productStub);

        $provider = new ProductDataProvider(
            $this->productRepositoryStub,
            $this->stockRegistryStub,
            $this->priceCurrencyStub,
            $this->htmlConverter,
            $configStub,
            $this->loggerStub
        );

        $result = $provider->extract(17, 1);
        $this->assertInstanceOf(SyncItem::class, $result);
        $this->assertSame(SyncItem::ACTION_UPSERT, $result->action);
        $this->assertStringContainsString('- **Availability**: In Stock', $result->content);
    }

    /**
     * Test extract returns null and logs warning when repository throws an exception.
     *
     * @return void
     */
    public function testExtractReturnsNullAndLogsWarningOnThrowable(): void
    {
        $this->productRepositoryStub->method('getById')->willThrowException(new \RuntimeException('Product DB error'));

        $loggerMock = $this->createMock(\Psr\Log\LoggerInterface::class);
        $loggerMock->expects($this->once())->method('warning');

        $provider = new ProductDataProvider(
            $this->productRepositoryStub,
            $this->stockRegistryStub,
            $this->priceCurrencyStub,
            $this->htmlConverter,
            $this->configStub,
            $loggerMock
        );

        $result = $provider->extract(999, 1);
        $this->assertNull($result);
    }

    /**
     * Test extract prioritizes custom knowledge and omits core description when enabled.
     *
     * @return void
     */
    public function testExtractPrioritizesCustomKnowledgeOverCoreDescription(): void
    {
        $productStub = $this->createStub(Product::class);
        $productStub->method('getId')->willReturn(25);
        $productStub->method('getName')->willReturn('Priority Item');
        $productStub->method('getSku')->willReturn('PRIO-01');
        $productStub->method('getTypeId')->willReturn('simple');
        $productStub->method('getProductUrl')->willReturn('http://magento.test/priority-item.html');
        $productStub->method('getStatus')->willReturn(Status::STATUS_ENABLED);
        $productStub->method('getVisibility')->willReturn(Visibility::VISIBILITY_BOTH);
        $productStub->method('isSalable')->willReturn(true);
        $productStub->method('getData')->willReturnCallback(function (string $key) {
            return match ($key) {
                'description' => '<p>Standard core description to be omitted.</p>',
                'short_description' => '',
                'clusterify_chatbot_knowledge' => 'Exclusive AI training context.',
                default => null,
            };
        });

        $priceInfoStub = $this->createStub(PriceInfo::class);
        $priceStub = $this->createStub(PriceInterface::class);
        $priceStub->method('getValue')->willReturn(40.00);
        $priceInfoStub->method('getPrice')->willReturn($priceStub);
        $productStub->method('getPriceInfo')->willReturn($priceInfoStub);

        $this->priceCurrencyStub->method('format')->willReturn('$40.00');
        $this->productRepositoryStub->method('getById')->willReturn($productStub);

        $configMock = $this->createStub(Config::class);
        $configMock->method('isCustomKnowledgeOnlySyncEnabled')->willReturn(true);

        $provider = new ProductDataProvider(
            $this->productRepositoryStub,
            $this->stockRegistryStub,
            $this->priceCurrencyStub,
            $this->htmlConverter,
            $configMock,
            $this->loggerStub
        );

        $result = $provider->extract(25, 1);
        $this->assertInstanceOf(SyncItem::class, $result);
        // Custom knowledge should be present
        $this->assertStringContainsString('## AI Knowledge & Context', $result->content);
        $this->assertStringContainsString('Exclusive AI training context.', $result->content);
        // Core description should be omitted!
        $this->assertStringNotContainsString('Standard core description to be omitted.', $result->content);
        $this->assertStringNotContainsString('## Description', $result->content);
    }

    /**
     * Test extract falls back to core description when custom knowledge is empty.
     *
     * @return void
     */
    public function testExtractFallsBackToDescriptionWhenCustomKnowledgeEmpty(): void
    {
        $productStub = $this->createStub(Product::class);
        $productStub->method('getId')->willReturn(26);
        $productStub->method('getName')->willReturn('Fallback Item');
        $productStub->method('getSku')->willReturn('FB-02');
        $productStub->method('getTypeId')->willReturn('simple');
        $productStub->method('getProductUrl')->willReturn('http://magento.test/fallback-item.html');
        $productStub->method('getStatus')->willReturn(Status::STATUS_ENABLED);
        $productStub->method('getVisibility')->willReturn(Visibility::VISIBILITY_BOTH);
        $productStub->method('isSalable')->willReturn(true);
        $productStub->method('getData')->willReturnCallback(function (string $key) {
            return match ($key) {
                'description' => '<p>Fallback core description when custom knowledge is empty.</p>',
                'short_description' => '',
                'clusterify_chatbot_knowledge' => '', // Empty custom knowledge
                default => null,
            };
        });

        $priceInfoStub = $this->createStub(PriceInfo::class);
        $priceStub = $this->createStub(PriceInterface::class);
        $priceStub->method('getValue')->willReturn(50.00);
        $priceInfoStub->method('getPrice')->willReturn($priceStub);
        $productStub->method('getPriceInfo')->willReturn($priceInfoStub);

        $this->priceCurrencyStub->method('format')->willReturn('$50.00');
        $this->productRepositoryStub->method('getById')->willReturn($productStub);

        $configMock = $this->createStub(Config::class);
        $configMock->method('isCustomKnowledgeOnlySyncEnabled')->willReturn(true);

        $provider = new ProductDataProvider(
            $this->productRepositoryStub,
            $this->stockRegistryStub,
            $this->priceCurrencyStub,
            $this->htmlConverter,
            $configMock,
            $this->loggerStub
        );

        $result = $provider->extract(26, 1);
        $this->assertInstanceOf(SyncItem::class, $result);
        // Falls back to core description
        $this->assertStringContainsString('Fallback core description when custom knowledge is empty.', $result->content);
        $this->assertStringContainsString('## Description', $result->content);
        $this->assertStringNotContainsString('## AI Knowledge & Context', $result->content);
    }

    /**
     * Test extract omits price and availability by default (when disabled).
     *
     * @return void
     */
    public function testExtractOmitsPriceAndAvailabilityByDefault(): void
    {
        $productStub = $this->createStub(Product::class);
        $productStub->method('getId')->willReturn(27);
        $productStub->method('getName')->willReturn('Default Metadata Product');
        $productStub->method('getSku')->willReturn('DEF-01');
        $productStub->method('getTypeId')->willReturn('simple');
        $productStub->method('getProductUrl')->willReturn('http://magento.test/default-meta.html');
        $productStub->method('getStatus')->willReturn(Status::STATUS_ENABLED);
        $productStub->method('getVisibility')->willReturn(Visibility::VISIBILITY_BOTH);
        $productStub->method('isSalable')->willReturn(true);
        $productStub->method('getData')->willReturnCallback(function (string $key) {
            return $key === 'description' ? '<p>Product description</p>' : null;
        });

        $this->productRepositoryStub->method('getById')->willReturn($productStub);

        $configStub = $this->createStub(Config::class);
        $configStub->method('isProductPriceSyncEnabled')->willReturn(false);
        $configStub->method('isProductAvailabilitySyncEnabled')->willReturn(false);

        $provider = new ProductDataProvider(
            $this->productRepositoryStub,
            $this->stockRegistryStub,
            $this->priceCurrencyStub,
            $this->htmlConverter,
            $configStub,
            $this->loggerStub
        );

        $result = $provider->extract(27, 1);
        $this->assertInstanceOf(SyncItem::class, $result);
        $this->assertStringContainsString('# Default Metadata Product', $result->content);
        $this->assertStringContainsString('- **SKU**: DEF-01', $result->content);
        // Price and Availability MUST NOT be present
        $this->assertStringNotContainsString('- **Price**:', $result->content);
        $this->assertStringNotContainsString('- **Availability**:', $result->content);
    }

    /**
     * Test extract includes price and availability when both are enabled in configuration.
     *
     * @return void
     */
    public function testExtractIncludesPriceAndAvailabilityWhenConfigured(): void
    {
        $productStub = $this->createStub(Product::class);
        $productStub->method('getId')->willReturn(28);
        $productStub->method('getName')->willReturn('Full Metadata Product');
        $productStub->method('getSku')->willReturn('FULL-01');
        $productStub->method('getTypeId')->willReturn('simple');
        $productStub->method('getProductUrl')->willReturn('http://magento.test/full-meta.html');
        $productStub->method('getStatus')->willReturn(Status::STATUS_ENABLED);
        $productStub->method('getVisibility')->willReturn(Visibility::VISIBILITY_BOTH);
        $productStub->method('isSalable')->willReturn(true);
        $productStub->method('getData')->willReturnCallback(function (string $key) {
            return $key === 'description' ? '<p>Product description</p>' : null;
        });

        $priceInfoStub = $this->createStub(PriceInfo::class);
        $priceStub = $this->createStub(PriceInterface::class);
        $priceStub->method('getValue')->willReturn(89.00);
        $priceInfoStub->method('getPrice')->willReturn($priceStub);
        $productStub->method('getPriceInfo')->willReturn($priceInfoStub);

        $this->priceCurrencyStub->method('format')->willReturn('$89.00');
        $this->productRepositoryStub->method('getById')->willReturn($productStub);

        $configStub = $this->createStub(Config::class);
        $configStub->method('isProductPriceSyncEnabled')->willReturn(true);
        $configStub->method('isProductAvailabilitySyncEnabled')->willReturn(true);

        $provider = new ProductDataProvider(
            $this->productRepositoryStub,
            $this->stockRegistryStub,
            $this->priceCurrencyStub,
            $this->htmlConverter,
            $configStub,
            $this->loggerStub
        );

        $result = $provider->extract(28, 1);
        $this->assertInstanceOf(SyncItem::class, $result);
        $this->assertStringContainsString('- **SKU**: FULL-01', $result->content);
        $this->assertStringContainsString('- **Price**: $89.00', $result->content);
        $this->assertStringContainsString('- **Availability**: In Stock', $result->content);
    }
}
