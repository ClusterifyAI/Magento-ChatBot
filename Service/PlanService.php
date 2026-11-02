<?php
/**
 * ClusterifyAI ChatBot plan and profile service
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Service;

use Clusterify\DTO\Profile\PlanData;
use Clusterify\Http\RequestOptions;
use ClusterifyAI\ChatBot\Model\Config;
use Exception;
use Magento\Framework\App\CacheInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

/**
 * Class PlanService
 *
 * Manages Clusterify subscription plan verification, caching profile queries,
 * and enforcing feature gates (such as restricting URL Knowledge Base sync to plan_id >= 2).
 */
class PlanService
{
    /**
     * Cache key prefix for plan metadata
     */
    public const CACHE_PREFIX = 'clusterify_plan_cache_';

    /**
     * Shared cache tag for Clusterify runtime metadata
     */
    public const CACHE_TAG = 'clusterify_chatbot';

    /**
     * Cache TTL in seconds (10 minutes)
     */
    public const CACHE_TTL = 600;

    /**
     * Plan ID constants
     */
    public const PLAN_ID_STARTER = 1;
    public const PLAN_ID_PROFESSIONAL = 2;

    /**
     * @param ClientFactory   $clientFactory Client factory service
     * @param Config          $config        Module configuration provider
     * @param CacheInterface  $cache         Magento cache interface
     * @param LoggerInterface $logger        System logger
     */
    public function __construct(
        private readonly ClientFactory $clientFactory,
        private readonly Config $config,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Retrieve subscription plan metadata for a given configuration scope.
     *
     * @param int|string|null $storeId      Store view ID or scope code
     * @param string          $scopeType    Scope type (store, website, default)
     * @param bool            $forceRefresh Bypass local and upstream API caches via RequestOptions::noCache()
     * @return PlanData|null
     */
    public function getPlanData(
        int|string|null $storeId = null,
        string $scopeType = ScopeInterface::SCOPE_STORE,
        bool $forceRefresh = false
    ): ?PlanData {
        $pubKey = $this->config->getPublicKey($storeId, $scopeType);
        $secKey = $this->config->getSecretKey($storeId, $scopeType);

        if ($pubKey === '' || $secKey === '') {
            return null;
        }

        $cacheKey = $this->buildCacheKey($storeId, $scopeType);

        if (!$forceRefresh) {
            $cachedRaw = $this->cache->load($cacheKey);
            if ($cachedRaw !== false) {
                $data = json_decode((string) $cachedRaw, true);
                if (is_array($data)) {
                    return PlanData::fromArray($data);
                }
            }
        }

        try {
            $client = $this->clientFactory->create(
                scopeCode: $storeId,
                scopeType: $scopeType
            );

            $options = $forceRefresh ? RequestOptions::noCache() : null;
            $profileResponse = $client->profile()->get($options);
            $plan = $profileResponse->plan;

            $this->cache->save(
                (string) json_encode($plan->toArray()),
                $cacheKey,
                [self::CACHE_TAG],
                self::CACHE_TTL
            );

            return $plan;
        } catch (Exception $e) {
            $this->logger->warning('Clusterify PlanService failed to fetch profile: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Retrieve the subscription plan ID (e.g. 1 = Starter, 2 = Professional).
     *
     * @param int|string|null $storeId   Store view ID or scope code
     * @param string          $scopeType Scope type
     * @return int
     */
    public function getPlanId(
        int|string|null $storeId = null,
        string $scopeType = ScopeInterface::SCOPE_STORE
    ): int {
        $plan = $this->getPlanData($storeId, $scopeType);
        return $plan !== null ? (int) $plan->id : self::PLAN_ID_STARTER;
    }

    /**
     * Retrieve human-readable plan name.
     *
     * @param int|string|null $storeId   Store view ID or scope code
     * @param string          $scopeType Scope type
     * @return string
     */
    public function getPlanName(
        int|string|null $storeId = null,
        string $scopeType = ScopeInterface::SCOPE_STORE
    ): string {
        $plan = $this->getPlanData($storeId, $scopeType);
        return $plan !== null ? (string) $plan->name : 'STARTER Plan';
    }

    /**
     * Authoritative guard checking if synchronization is permitted for a specific entity type and store view.
     * Evaluates local configuration switches first (zero network/API overhead) before performing subscription plan checks.
     *
     * @param string   $entityType Entity type code ('cms', 'category', 'product')
     * @param int|null $storeId    Store view ID
     * @return bool
     */
    public function canSyncEntity(string $entityType, ?int $storeId = null): bool
    {
        // 1. Extension master switch must be enabled (zero network)
        if (!$this->config->isEnabled($storeId, ScopeInterface::SCOPE_STORE)) {
            return false;
        }

        // 2. URL Knowledge Base master sync must be enabled (zero network)
        if (!$this->config->isSyncEnabled($storeId, ScopeInterface::SCOPE_STORE)) {
            return false;
        }

        // 3. Entity-specific sync switch must be enabled (zero network)
        $isEntityEnabled = match ($entityType) {
            'cms' => $this->config->isCmsSyncEnabled($storeId, ScopeInterface::SCOPE_STORE),
            'category' => $this->config->isCategorySyncEnabled($storeId, ScopeInterface::SCOPE_STORE),
            'product' => $this->config->isProductSyncEnabled($storeId, ScopeInterface::SCOPE_STORE),
            default => false,
        };

        if (!$isEntityEnabled) {
            return false;
        }

        // 4. API credentials must be configured (zero network)
        $pubKey = $this->config->getPublicKey($storeId, ScopeInterface::SCOPE_STORE);
        $secKey = $this->config->getSecretKey($storeId, ScopeInterface::SCOPE_STORE);
        if ($pubKey === '' || $secKey === '') {
            return false;
        }

        // 5. Subscription plan check (only reached if all 4 local checks pass)
        return $this->isUrlKnowledgeAllowed($storeId, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Check if the subscription plan permits URL-Based Knowledge Base synchronization.
     * Must be Professional or higher (plan_id >= 2).
     *
     * @param int|string|null $storeId   Store view ID or scope code
     * @param string          $scopeType Scope type
     * @return bool
     */
    public function isUrlKnowledgeAllowed(
        int|string|null $storeId = null,
        string $scopeType = ScopeInterface::SCOPE_STORE
    ): bool {
        $plan = $this->getPlanData($storeId, $scopeType);
        if ($plan === null) {
            return false;
        }

        // Plan ID 1 is Starter Plan and strictly disallowed from URL Knowledge Base
        if ((int) $plan->id === self::PLAN_ID_STARTER) {
            return false;
        }

        return (bool) $plan->isUrlKnowledgeAllowed;
    }

    /**
     * Clear cached plan metadata for a specific store or all scopes.
     *
     * @param int|null $storeId
     * @return void
     */
    public function clearPlanCache(?int $storeId = null): void
    {
        if ($storeId !== null) {
            $this->cache->remove(self::CACHE_PREFIX . $storeId);
        } else {
            $this->cache->clean([self::CACHE_TAG]);
        }
    }

    /**
     * Build deterministic cache key per configuration scope.
     *
     * @param int|string|null $scopeCode
     * @param string          $scopeType
     * @return string
     */
    private function buildCacheKey(int|string|null $scopeCode, string $scopeType): string
    {
        if ($scopeType === ScopeInterface::SCOPE_STORE) {
            return self::CACHE_PREFIX . (string) ($scopeCode ?? 0);
        }

        return self::CACHE_PREFIX . preg_replace('/[^a-zA-Z0-9_]/', '_', $scopeType . '_' . (string) ($scopeCode ?? 'default'));
    }
}
