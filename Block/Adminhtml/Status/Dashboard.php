<?php
/**
 * ClusterifyAI ChatBot admin status dashboard block
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Block\Adminhtml\Status;

use Clusterify\DTO\KnowledgeUrl\KnowledgeUrlStats;
use Clusterify\DTO\Profile\PlanData;
use ClusterifyAI\ChatBot\Model\Config;
use ClusterifyAI\ChatBot\Model\Indexer\Category as CategoryIndexer;
use ClusterifyAI\ChatBot\Model\Indexer\Cms as CmsIndexer;
use ClusterifyAI\ChatBot\Model\Indexer\Product as ProductIndexer;
use ClusterifyAI\ChatBot\Service\ClientFactory;
use ClusterifyAI\ChatBot\Service\PageVisibility;
use ClusterifyAI\ChatBot\Service\PlanService;
use Exception;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\Amqp\Config as AmqpConfig;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Indexer\StateInterface;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

/**
 * Class Dashboard
 *
 * Block providing diagnostic data, plan status, quota metrics, and indexer states
 * for the Admin Monitoring Dashboard.
 */
class Dashboard extends Template
{
    public const BILLING_URL = 'https://dashboard.clusterify.ai/billing';
    public const DASHBOARD_URL = 'https://dashboard.clusterify.ai';
    public const QUOTA_CACHE_PREFIX = 'clusterify_quota_stats_';
    public const QUOTA_CACHE_TTL = 300;

    /**
     * Path to template file
     */
    protected $_template = 'ClusterifyAI_ChatBot::status/dashboard.phtml';

    /**
     * Memoized quota stats for current request
     */
    private ?KnowledgeUrlStats $cachedQuotaStats = null;
    private bool $quotaStatsLoaded = false;

    /**
     * @param Context          $context               Backend template context
     * @param PlanService      $planService           Plan verification service
     * @param Config           $config                Configuration provider
     * @param ClientFactory    $clientFactory         SDK client factory
     * @param IndexerRegistry  $indexerRegistry       Indexer registry
     * @param PageVisibility   $pageVisibilityService Page visibility service
     * @param CacheInterface   $cache                 Magento cache interface
     * @param TimezoneInterface $timezone             Timezone and locale formatting service
     * @param LoggerInterface  $logger                Logger
     * @param array            $data                  Additional block data
     * @param JsonHelper|null  $jsonHelper            JSON helper
     * @param DirectoryHelper|null $directoryHelper   Directory helper
     */
    public function __construct(
        Context $context,
        private readonly PlanService $planService,
        private readonly Config $config,
        private readonly ClientFactory $clientFactory,
        private readonly IndexerRegistry $indexerRegistry,
        private readonly PageVisibility $pageVisibilityService,
        private readonly CacheInterface $cache,
        private readonly TimezoneInterface $timezone,
        private readonly LoggerInterface $logger,
        private readonly AmqpConfig $amqpConfig,
        array $data = [],
        ?JsonHelper $jsonHelper = null,
        ?DirectoryHelper $directoryHelper = null
    ) {
        parent::__construct($context, $data, $jsonHelper, $directoryHelper);
    }

    /**
     * Retrieve active store view ID safely handling non-existent store codes.
     *
     * @return int|null
     */
    public function getActiveStoreId(): ?int
    {
        $storeCode = (string) $this->getRequest()->getParam('store');
        if ($storeCode !== '') {
            try {
                return (int) $this->_storeManager->getStore($storeCode)->getId();
            } catch (NoSuchEntityException $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * Check if master extension is enabled.
     *
     * @return bool
     */
    public function isExtensionEnabled(): bool
    {
        return $this->config->isEnabled($this->getActiveStoreId(), ScopeInterface::SCOPE_STORE);
    }

    /**
     * Check if storefront presentation is enabled.
     *
     * @return bool
     */
    public function isShowOnStorefront(): bool
    {
        return $this->config->isShowOnStorefront($this->getActiveStoreId(), ScopeInterface::SCOPE_STORE);
    }

    /**
     * Retrieve Chatbot Public UUID.
     *
     * @return string
     */
    public function getPublicUuid(): string
    {
        return $this->config->getPublicUuid($this->getActiveStoreId(), ScopeInterface::SCOPE_STORE);
    }

    /**
     * Check if API credentials are configured.
     *
     * @return bool
     */
    public function hasApiCredentials(): bool
    {
        $storeId = $this->getActiveStoreId();
        return $this->config->getPublicKey($storeId, ScopeInterface::SCOPE_STORE) !== ''
            && $this->config->getSecretKey($storeId, ScopeInterface::SCOPE_STORE) !== '';
    }

    /**
     * Retrieve configured API Base URL.
     *
     * @return string
     */
    public function getApiBaseUrl(): string
    {
        return $this->config->getApiBaseUrl($this->getActiveStoreId(), ScopeInterface::SCOPE_STORE);
    }

    /**
     * Retrieve extension release version.
     *
     * @return string
     */
    public function getExtensionVersion(): string
    {
        return $this->config->getExtensionVersion();
    }

    /**
     * Retrieve subscription plan metadata.
     *
     * @return PlanData|null
     */
    public function getPlanData(): ?PlanData
    {
        return $this->planService->getPlanData($this->getActiveStoreId());
    }

    /**
     * Retrieve subscription plan ID.
     *
     * @return int
     */
    public function getPlanId(): int
    {
        return $this->planService->getPlanId($this->getActiveStoreId());
    }

    /**
     * Retrieve human-readable plan name.
     *
     * @return string
     */
    public function getPlanName(): string
    {
        return $this->planService->getPlanName($this->getActiveStoreId());
    }

    /**
     * Check if URL Knowledge Base synchronization is permitted.
     *
     * @return bool
     */
    public function isUrlKnowledgeAllowed(): bool
    {
        return $this->planService->isUrlKnowledgeAllowed($this->getActiveStoreId());
    }

    /**
     * Retrieve live or cached URL Knowledge Base usage quota statistics.
     *
     * @return KnowledgeUrlStats|null
     */
    public function getQuotaStats(): ?KnowledgeUrlStats
    {
        if ($this->quotaStatsLoaded) {
            return $this->cachedQuotaStats;
        }

        $this->quotaStatsLoaded = true;

        if (!$this->hasApiCredentials() || !$this->isUrlKnowledgeAllowed()) {
            return null;
        }

        $storeId = $this->getActiveStoreId();
        $cacheKey = self::QUOTA_CACHE_PREFIX . (string) ($storeId ?? 0);
        $cached = $this->cache->load($cacheKey);

        if ($cached !== false) {
            $data = json_decode((string) $cached, true);
            if (is_array($data)) {
                $this->cachedQuotaStats = KnowledgeUrlStats::fromArray($data);
                return $this->cachedQuotaStats;
            }
        }

        try {
            $client = $this->clientFactory->create(
                scopeCode: $storeId,
                scopeType: ScopeInterface::SCOPE_STORE
            );
            $stats = $client->knowledgeUrl()->stats();
            $this->cachedQuotaStats = $stats;

            $this->cache->save(
                (string) json_encode($stats->toArray()),
                $cacheKey,
                ['clusterify_chatbot'],
                self::QUOTA_CACHE_TTL
            );

            return $this->cachedQuotaStats;
        } catch (Exception $e) {
            $this->logger->warning(sprintf(
                'Clusterify Dashboard failed to retrieve quota stats for store %s: %s',
                var_export($storeId, true),
                $e->getMessage()
            ));
            return null;
        }
    }

    /**
     * Retrieve status of all 3 dedicated sync indexers.
     *
     * @return list<array{id: string, title: string, status: string, is_valid: bool, is_scheduled: bool, last_updated: string}>
     */
    public function getIndexerStatusList(): array
    {
        $indexers = [
            CmsIndexer::INDEXER_ID => (string) __('CMS Pages Indexer'),
            CategoryIndexer::INDEXER_ID => (string) __('Categories Indexer'),
            ProductIndexer::INDEXER_ID => (string) __('Products Indexer'),
        ];

        $list = [];
        foreach ($indexers as $id => $title) {
            try {
                $indexer = $this->indexerRegistry->get($id);
                $status = $indexer->getStatus();

                $statusLabel = match ($status) {
                    StateInterface::STATUS_VALID => (string) __('Ready'),
                    StateInterface::STATUS_INVALID => (string) __('Needs Reindex'),
                    StateInterface::STATUS_WORKING => (string) __('Processing'),
                    default => (string) __('Unknown'),
                };

                $rawUpdated = (string) $indexer->getLatestUpdated();
                $formattedUpdated = ($rawUpdated !== '' && $rawUpdated !== 'Never')
                    ? $this->timezone->formatDateTime($rawUpdated, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT)
                    : '';

                $list[] = [
                    'id' => $id,
                    'title' => $title,
                    'status' => $statusLabel,
                    'is_valid' => $status === StateInterface::STATUS_VALID,
                    'is_scheduled' => (bool) $indexer->isScheduled(),
                    'last_updated' => $formattedUpdated,
                ];
            } catch (Exception $e) {
                $list[] = [
                    'id' => $id,
                    'title' => $title,
                    'status' => (string) __('Unavailable'),
                    'is_valid' => false,
                    'is_scheduled' => true,
                    'last_updated' => '',
                ];
            }
        }

        return $list;
    }

    /**
     * Retrieve page visibility summary statistics.
     *
     * @return array{total: int, allowed: int, blocked: int}
     */
    public function getPageVisibilitySummary(): array
    {
        $storeId = $this->getActiveStoreId();
        $allPages = $this->pageVisibilityService->getAllPageTypes();
        $allowed = 0;
        $blocked = 0;

        foreach (array_keys($allPages) as $code) {
            if ($this->pageVisibilityService->isPageAllowed($code, $storeId, ScopeInterface::SCOPE_STORE)) {
                $allowed++;
            } else {
                $blocked++;
            }
        }

        return [
            'total' => count($allPages),
            'allowed' => $allowed,
            'blocked' => $blocked,
        ];
    }

    /**
     * Retrieve URL knowledge sync settings state.
     *
     * @return array{master: bool, cms: bool, category: bool, product: bool, in_stock_only: bool}
     */
    public function getSyncSettings(): array
    {
        $storeId = $this->getActiveStoreId();

        return [
            'master' => $this->config->isSyncEnabled($storeId, ScopeInterface::SCOPE_STORE),
            'cms' => $this->config->isCmsSyncEnabled($storeId, ScopeInterface::SCOPE_STORE),
            'category' => $this->config->isCategorySyncEnabled($storeId, ScopeInterface::SCOPE_STORE),
            'product' => $this->config->isProductSyncEnabled($storeId, ScopeInterface::SCOPE_STORE),
            'in_stock_only' => $this->config->isInStockOnlySyncEnabled($storeId, ScopeInterface::SCOPE_STORE),
        ];
    }

    /**
     * Retrieve real-time RabbitMQ queue backlog and consumer worker status.
     *
     * @return list<array{queue_name: string, title: string, message_count: int, consumer_count: int, is_available: bool}>
     */
    public function getQueueStatusList(): array
    {
        $queues = [
            'clusterify.chatbot.sync.cms' => (string) __('CMS Pages Queue'),
            'clusterify.chatbot.sync.category' => (string) __('Categories Queue'),
            'clusterify.chatbot.sync.product' => (string) __('Products Queue'),
        ];

        $list = [];

        try {
            $channel = $this->amqpConfig?->getChannel();
        } catch (Exception $e) {
            $channel = null;
        }

        foreach ($queues as $queueName => $title) {
            $messageCount = 0;
            $consumerCount = 0;
            $isAvailable = false;

            if ($channel !== null) {
                try {
                    $declareResult = $channel->queue_declare($queueName, true);
                    $messageCount = (int) ($declareResult[1] ?? 0);
                    $consumerCount = (int) ($declareResult[2] ?? 0);
                    $isAvailable = true;
                } catch (Exception $e) {
                    $isAvailable = false;
                }
            }

            $list[] = [
                'queue_name' => $queueName,
                'title' => $title,
                'message_count' => $messageCount,
                'consumer_count' => $consumerCount,
                'is_available' => $isAvailable,
            ];
        }

        return $list;
    }

    /**
     * Retrieve System Configuration edit URL for ChatBot.
     *
     * @return string
     */
    public function getConfigUrl(): string
    {
        return $this->getUrl('adminhtml/system_config/edit', ['section' => 'clusterify_chatbot']);
    }

    /**
     * Retrieve Index Management URL.
     *
     * @return string
     */
    public function getIndexManagementUrl(): string
    {
        return $this->getUrl('indexer/indexer/list');
    }

    /**
     * Retrieve Billing upgrade URL.
     *
     * @return string
     */
    public function getBillingUrl(): string
    {
        return self::BILLING_URL;
    }

    /**
     * Retrieve Clusterify Dashboard URL.
     *
     * @return string
     */
    public function getDashboardUrl(): string
    {
        return self::DASHBOARD_URL;
    }
}
