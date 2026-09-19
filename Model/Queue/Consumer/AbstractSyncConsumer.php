<?php
/**
 * ClusterifyAI ChatBot abstract queue sync consumer
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Model\Queue\Consumer;

use Clusterify\ClusterifyClient;
use Clusterify\Exceptions\AuthenticationException;
use Clusterify\Exceptions\AuthorizationException;
use Clusterify\Exceptions\ForbiddenPlanException;
use Clusterify\Exceptions\NetworkException;
use Clusterify\Exceptions\PlanLimitExceededException;
use Clusterify\Exceptions\RateLimitExceededException;
use ClusterifyAI\ChatBot\Api\Data\SyncMessageInterface;
use ClusterifyAI\ChatBot\Model\Config;
use ClusterifyAI\ChatBot\Model\Sync\ProviderPool;
use ClusterifyAI\ChatBot\Model\Sync\SyncItem;
use ClusterifyAI\ChatBot\Service\ClientFactory;
use ClusterifyAI\ChatBot\Service\PlanService;
use Magento\Framework\App\Area;
use Magento\Framework\App\CacheInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Class AbstractSyncConsumer
 *
 * Base consumer logic handling message extraction with storefront emulation,
 * Clusterify SDK batching/upserts, dynamic rate-limit retries, and plan requirement safeguards.
 */
abstract class AbstractSyncConsumer
{
    /**
     * Delay in microseconds (300ms) between API requests to prevent bursting
     */
    protected const THROTTLE_DELAY_MICROS = 300000;

    /**
     * Cache key for temporary plan restriction suppression
     */
    protected const CACHE_KEY_FORBIDDEN_PLAN = 'clusterify_plan_forbidden_';

    /**
     * @param ClientFactory   $clientFactory Client factory service
     * @param Config          $config        Configuration service
     * @param ProviderPool    $providerPool  Provider registry
     * @param Emulation       $appEmulation  Store environment emulation
     * @param CacheInterface  $cache         Magento cache interface
     * @param PlanService     $planService   Plan verification service
     * @param LoggerInterface $logger        System logger
     */
    public function __construct(
        protected readonly ClientFactory $clientFactory,
        protected readonly Config $config,
        protected readonly ProviderPool $providerPool,
        protected readonly Emulation $appEmulation,
        protected readonly CacheInterface $cache,
        protected readonly PlanService $planService,
        protected readonly LoggerInterface $logger
    ) {}

    /**
     * Process an incoming queue message.
     *
     * @param SyncMessageInterface $message
     * @return void
     */
    public function processMessage(SyncMessageInterface $message): void
    {
        $entityType = 'unknown';
        $entityId = 0;
        $storeId = 1;

        try {
            $entityType = $message->getEntityType();
            $entityId = $message->getEntityId();
            $storeId = $message->getStoreId();
            $action = $message->getAction();
            $directUrl = $message->getUrl();

            if ($storeId <= 0 || $entityId <= 0) {
                return;
            }

            // 1. Authoritative check: master enabled + sync enabled + entity enabled + credentials + plan check
            if (!$this->planService->canSyncEntity($entityType, $storeId)) {
                return;
            }

            // Suppress repeated API storm if plan restriction was already encountered for this store
            $cacheKey = self::CACHE_KEY_FORBIDDEN_PLAN . $storeId;
            if ($this->cache->load($cacheKey) !== false) {
                return;
            }

            $client = $this->clientFactory->create(
                scopeCode: $storeId,
                scopeType: ScopeInterface::SCOPE_STORE
            );

            // 1. Direct URL purge for deleted entities (bypasses entity loading)
            if ($action === SyncItem::ACTION_DELETE && !empty($directUrl)) {
                $this->dispatchWithRetry(fn () => $client->knowledgeUrl()->bulkDelete(urls: [$directUrl]));
                $this->logger->info(sprintf(
                    'Clusterify Knowledge URL purged (deleted): %s [Entity: %s #%d, Store: %d]',
                    $directUrl,
                    $entityType,
                    $entityId,
                    $storeId
                ));
                usleep(self::THROTTLE_DELAY_MICROS);
                return;
            }

            if (!$this->providerPool->hasProvider($entityType)) {
                $this->logger->warning(sprintf('No sync provider available for entity type "%s".', $entityType));
                return;
            }

            // 2. Extract entity data wrapped in store emulation
            $provider = $this->providerPool->getProvider($entityType);

            $isEmulated = false;
            try {
                $this->appEmulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);
                $isEmulated = true;
                $syncItem = $provider->extract($entityId, $storeId);
            } finally {
                if ($isEmulated) {
                    $this->appEmulation->stopEnvironmentEmulation();
                }
            }

            if ($syncItem === null) {
                return;
            }

            // 3. Dispatch upsert or delete action
            if ($action === SyncItem::ACTION_DELETE || $syncItem->action === SyncItem::ACTION_DELETE || !$syncItem->isEnabled) {
                $this->dispatchWithRetry(fn () => $client->knowledgeUrl()->bulkDelete(urls: [$syncItem->url]));
                $this->logger->info(sprintf(
                    'Clusterify Knowledge URL purged: %s [Entity: %s #%d, Store: %d]',
                    $syncItem->url,
                    $entityType,
                    $entityId,
                    $storeId
                ));
            } else {
                $this->dispatchWithRetry(fn () => $client->knowledgeUrl()->upsert(
                    url: $syncItem->url,
                    content: $syncItem->content,
                    isEnabled: $syncItem->isEnabled
                ));
                $this->logger->info(sprintf(
                    'Clusterify Knowledge URL synced: %s [Entity: %s #%d, Store: %d]',
                    $syncItem->url,
                    $entityType,
                    $entityId,
                    $storeId
                ));
            }

            usleep(self::THROTTLE_DELAY_MICROS);
        } catch (ForbiddenPlanException $e) {
            $cacheKey = self::CACHE_KEY_FORBIDDEN_PLAN . $storeId;
            $this->logger->warning(sprintf(
                'Clusterify URL Knowledge sync paused for 1 hour: Account plan (%d) requires Professional Plan or higher.',
                $e->getCurrentPlan()
            ));
            // Cache restriction for 1 hour to prevent 403 request storms across queue messages
            $this->cache->save('1', $cacheKey, ['clusterify_chatbot'], 3600);
        } catch (PlanLimitExceededException $e) {
            $this->logger->warning(sprintf('Clusterify URL Knowledge sync skipped: Plan URL limit exceeded (%s).', $e->getMessage()));
        } catch (AuthenticationException|AuthorizationException $e) {
            $this->logger->error(sprintf('Clusterify API authentication error during sync: %s', $e->getMessage()));
        } catch (Throwable $e) {
            $this->logger->error(sprintf(
                'Clusterify unexpected sync consumer error [Entity: %s #%d]: %s',
                $entityType,
                $entityId,
                $e->getMessage()
            ));
        }
    }

    /**
     * Dispatch an API operation with automatic retry on HTTP 429 rate limit and transient network glitch.
     *
     * @param callable $operation
     * @return void
     * @throws \Throwable
     */
    protected function dispatchWithRetry(callable $operation): void
    {
        try {
            $operation();
        } catch (RateLimitExceededException $e) {
            $retryAfter = max((int) ($e->getRetryAfter() ?? 5), 1);
            $this->logger->warning(sprintf('Clusterify rate limit hit (429). Pausing for %d seconds before retry.', $retryAfter));
            sleep(min($retryAfter, 15));
            $operation(); // Retry once
        } catch (NetworkException $e) {
            $this->logger->warning(sprintf('Clusterify network glitch (%s). Pausing 1s before retry.', $e->getMessage()));
            sleep(1);
            $operation(); // Retry once
        }
    }
}
