<?php
/**
 * ClusterifyAI ChatBot CMS page data provider
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
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Helper\Page as CmsPageHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class CmsDataProvider
 *
 * Extracts CMS page metadata and converts HTML content into structured Markdown knowledge.
 */
class CmsDataProvider implements DataProviderInterface
{
    public const ENTITY_TYPE = 'cms';

    /**
     * Identifiers that should not be synced as public knowledge
     */
    private const EXCLUDED_IDENTIFIERS = [
        'no-route',
        'enable-cookies',
    ];

    /**
     * @param PageRepositoryInterface $pageRepository Page repository
     * @param StoreManagerInterface   $storeManager  Store manager
     * @param ScopeConfigInterface    $scopeConfig   Scope configuration reader
     * @param HtmlToMarkdown          $htmlConverter HTML to Markdown utility
     * @param Config                  $config        Configuration service
     * @param LoggerInterface         $logger        Logger
     */
    public function __construct(
        private readonly PageRepositoryInterface $pageRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
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
            $page = $this->pageRepository->getById($entityId);
            $identifier = trim((string) $page->getIdentifier());

            if (in_array($identifier, self::EXCLUDED_IDENTIFIERS, true)) {
                return null;
            }

            // Verify store view assignment
            $pageStores = array_map('intval', (array) $page->getStores());
            if (!empty($pageStores) && !in_array(0, $pageStores, true) && !in_array($storeId, $pageStores, true)) {
                return null;
            }

            $store = $this->storeManager->getStore($storeId);
            $baseUrl = rtrim((string) $store->getBaseUrl(), '/');

            $homeIdentifier = (string) $this->scopeConfig->getValue(
                CmsPageHelper::XML_PATH_HOME_PAGE,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
            if ($homeIdentifier === '') {
                $homeIdentifier = 'home';
            }

            // CMS pages do not use .html suffix in Magento; home page canonical is root /
            $url = ($identifier === $homeIdentifier || $identifier === 'home')
                ? $baseUrl . '/'
                : $baseUrl . '/' . ltrim($identifier, '/');

            $url = strtok($url, '?');
            $url = trim(strtok((string) $url, '#'));
            if ($url === '') {
                return null;
            }

            // If page is disabled, issue a delete action to remove it from Clusterify
            if (!(bool) $page->isActive()) {
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

            $title = trim((string) $page->getTitle());
            $rawContent = (string) ($page->getContent() ?? '');
            $markdownContent = $this->htmlConverter->convert($rawContent);

            // Custom AI training knowledge attribute
            $customKnowledge = trim((string) ($page->getData('clusterify_chatbot_knowledge') ?? ''));
            if ($customKnowledge !== '' && mb_strlen($customKnowledge) > 20000) {
                $customKnowledge = mb_substr($customKnowledge, 0, 20000);
            }

            $prioritizeCustomKnowledge = $this->config->isCustomKnowledgeOnlySyncEnabled($storeId);

            if ($prioritizeCustomKnowledge && $customKnowledge !== '') {
                // When custom knowledge is present and prioritized, sync it instead of core page HTML
                $markdown = sprintf("# %s\n\n## AI Knowledge & Context\n%s", $title, $customKnowledge);
            } else {
                // Standard page content (and fallback if custom knowledge is empty)
                $markdown = sprintf("# %s\n\n%s", $title, $markdownContent);

                if (!$prioritizeCustomKnowledge && $customKnowledge !== '') {
                    $markdown .= sprintf("\n\n## AI Knowledge & Context\n%s", $customKnowledge);
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
                'Clusterify CmsDataProvider failed to extract page ID %d for store ID %d: %s',
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
}
