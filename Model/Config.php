<?php
/**
 * ClusterifyAI ChatBot configuration provider
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Model;

use Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class Config
 *
 * Central service for retrieving module configuration values from core_config_data
 * across Default, Website, and Store View scopes.
 */
class Config
{
    /**
     * Configuration XML paths
     */
    public const XML_PATH_ENABLED = 'clusterify_chatbot/general/enabled';
    public const XML_PATH_SHOW_ON_STOREFRONT = 'clusterify_chatbot/general/show_on_storefront';
    public const XML_PATH_PUBLIC_UUID = 'clusterify_chatbot/starter_plan/public_uuid';
    public const XML_PATH_PUBLIC_KEY = 'clusterify_chatbot/authorization/public_key';
    public const XML_PATH_SECRET_KEY = 'clusterify_chatbot/authorization/secret_key';
    public const XML_PATH_API_BASE_URL = 'clusterify_chatbot/authorization/api_base_url';
    public const XML_PATH_ALLOWED_PAGES = 'clusterify_chatbot/page_visibility/allowed_pages';
    public const XML_PATH_SYNC_ENABLED = 'clusterify_chatbot/url_knowledge_sync/enabled';
    public const XML_PATH_SYNC_CMS = 'clusterify_chatbot/url_knowledge_sync/sync_cms';
    public const XML_PATH_SYNC_CATEGORIES = 'clusterify_chatbot/url_knowledge_sync/sync_categories';
    public const XML_PATH_SYNC_PRODUCTS = 'clusterify_chatbot/url_knowledge_sync/sync_products';
    public const XML_PATH_SYNC_IN_STOCK_ONLY = 'clusterify_chatbot/url_knowledge_sync/in_stock_only';
    public const XML_PATH_SYNC_CUSTOM_KNOWLEDGE_ONLY = 'clusterify_chatbot/url_knowledge_sync/custom_knowledge_only';
    public const XML_PATH_SYNC_PRODUCT_PRICE = 'clusterify_chatbot/url_knowledge_sync/sync_product_price';
    public const XML_PATH_SYNC_PRODUCT_AVAILABILITY = 'clusterify_chatbot/url_knowledge_sync/sync_product_availability';
    public const XML_PATH_QUEUE_CRON_ENABLED = 'clusterify_chatbot/url_knowledge_sync/queue_cron_enabled';
    public const XML_PATH_QUEUE_BATCH_SIZE = 'clusterify_chatbot/url_knowledge_sync/queue_batch_size';

    public const DEFAULT_QUEUE_BATCH_SIZE = 50;

    /**
     * Extension release version
     */
    public const EXTENSION_VERSION = '1.0.0';

    /**
     * Default bundle script URL
     */
    public const DEFAULT_BUNDLE_SCRIPT_URL = 'https://api.clusterify.ai/static/clusterify-chatbot-react.bundle.min.js';

    /**
     * Default API base URL
     */
    public const DEFAULT_API_BASE_URL = 'https://api.clusterify.ai';

    /**
     * @param ScopeConfigInterface  $scopeConfig  Scope configuration reader
     * @param EncryptorInterface    $encryptor    Magento encryption service for decrypting secret key
     * @param StoreManagerInterface $storeManager Store manager for resolving default store view scope
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor,
        private readonly StoreManagerInterface $storeManager
    ) {}

    /**
     * Resolve effective scope code when not explicitly provided.
     *
     * @param int|string|null $scopeCode Store/Website code or ID
     * @param string          $scopeType Scope type (stores, websites, default)
     * @return int|string|null
     */
    protected function resolveScopeCode(int|string|null $scopeCode, string $scopeType): int|string|null
    {
        if ($scopeCode !== null) {
            return $scopeCode;
        }

        if ($scopeType === ScopeInterface::SCOPE_STORE) {
            try {
                return (int) $this->storeManager->getStore()->getId();
            } catch (Exception $e) {
                return null;
            }
        }

        if ($scopeType === ScopeInterface::SCOPE_WEBSITE || $scopeType === 'websites') {
            try {
                return (int) $this->storeManager->getWebsite()->getId();
            } catch (Exception $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * Check if the Clusterify ChatBot extension is enabled for the specified scope.
     *
     * @param int|string|null $scopeCode Store/Website code or ID
     * @param string          $scopeType Scope type (stores, websites, default)
     * @return bool
     */
    public function isEnabled(
        int|string|null $scopeCode = null,
        string $scopeType = ScopeInterface::SCOPE_STORE
    ): bool {
        $code = $this->resolveScopeCode($scopeCode, $scopeType);
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, $scopeType, $code);
    }

    /**
     * Check if the ChatBot assistant should be displayed on the storefront for the specified scope.
     *
     * @param int|string|null $scopeCode Store/Website code or ID
     * @param string          $scopeType Scope type (stores, websites, default)
     * @return bool
     */
    public function isShowOnStorefront(
        int|string|null $scopeCode = null,
        string $scopeType = ScopeInterface::SCOPE_STORE
    ): bool {
        return $this->isEnabled($scopeCode, $scopeType)
            && $this->scopeConfig->isSetFlag(
                self::XML_PATH_SHOW_ON_STOREFRONT,
                $scopeType,
                $this->resolveScopeCode($scopeCode, $scopeType)
            );
    }

    /**
     * Retrieve the configured ChatBot Public UUID for the specified scope.
     *
     * @param int|string|null $scopeCode Store/Website code or ID
     * @param string          $scopeType Scope type (stores, websites, default)
     * @return string
     */
    public function getPublicUuid(
        int|string|null $scopeCode = null,
        string $scopeType = ScopeInterface::SCOPE_STORE
    ): string {
        $code = $this->resolveScopeCode($scopeCode, $scopeType);
        return trim((string) $this->scopeConfig->getValue(self::XML_PATH_PUBLIC_UUID, $scopeType, $code));
    }

    /**
     * Retrieve the API Public Key for the specified scope.
     *
     * @param int|string|null $scopeCode Store/Website code or ID
     * @param string          $scopeType Scope type (stores, websites, default)
     * @return string
     */
    public function getPublicKey(
        int|string|null $scopeCode = null,
        string $scopeType = ScopeInterface::SCOPE_STORE
    ): string {
        $code = $this->resolveScopeCode($scopeCode, $scopeType);
        return trim((string) $this->scopeConfig->getValue(self::XML_PATH_PUBLIC_KEY, $scopeType, $code));
    }

    /**
     * Retrieve the decrypted API Secret Key for the specified scope.
     *
     * @param int|string|null $scopeCode Store/Website code or ID
     * @param string          $scopeType Scope type (stores, websites, default)
     * @return string
     */
    public function getSecretKey(
        int|string|null $scopeCode = null,
        string $scopeType = ScopeInterface::SCOPE_STORE
    ): string {
        $code = $this->resolveScopeCode($scopeCode, $scopeType);
        $encrypted = (string) $this->scopeConfig->getValue(self::XML_PATH_SECRET_KEY, $scopeType, $code);
        if ($encrypted === '') {
            return '';
        }
        return trim((string) $this->encryptor->decrypt($encrypted));
    }

    /**
     * Retrieve the API Base URL for the specified scope.
     *
     * @param int|string|null $scopeCode Store/Website code or ID
     * @param string          $scopeType Scope type (stores, websites, default)
     * @return string
     */
    public function getApiBaseUrl(
        int|string|null $scopeCode = null,
        string $scopeType = ScopeInterface::SCOPE_STORE
    ): string {
        $code = $this->resolveScopeCode($scopeCode, $scopeType);
        $url = trim((string) $this->scopeConfig->getValue(self::XML_PATH_API_BASE_URL, $scopeType, $code));
        return $url !== '' ? rtrim($url, '/') : self::DEFAULT_API_BASE_URL;
    }

    /**
     * Retrieve the configured allowed pages array.
     *
     * @param int|string|null $scopeCode
     * @param string          $scopeType
     * @return array<string, int|string|bool>
     */
    public function getAllowedPages(
        int|string|null $scopeCode = null,
        string $scopeType = ScopeInterface::SCOPE_STORE
    ): array {
        $code = $this->resolveScopeCode($scopeCode, $scopeType);
        $raw = (string) $this->scopeConfig->getValue(self::XML_PATH_ALLOWED_PAGES, $scopeType, $code);
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Check if URL Knowledge Base Synchronization is enabled for the scope.
     *
     * @param int|string|null $scopeCode
     * @param string          $scopeType
     * @return bool
     */
    public function isSyncEnabled(
        int|string|null $scopeCode = null,
        string $scopeType = ScopeInterface::SCOPE_STORE
    ): bool {
        $code = $this->resolveScopeCode($scopeCode, $scopeType);
        return $this->isEnabled($scopeCode, $scopeType)
            && $this->scopeConfig->isSetFlag(self::XML_PATH_SYNC_ENABLED, $scopeType, $code);
    }

    /**
     * Check if CMS page synchronization is enabled.
     *
     * @param int|string|null $scopeCode
     * @param string          $scopeType
     * @return bool
     */
    public function isCmsSyncEnabled(
        int|string|null $scopeCode = null,
        string $scopeType = ScopeInterface::SCOPE_STORE
    ): bool {
        $code = $this->resolveScopeCode($scopeCode, $scopeType);
        return $this->isSyncEnabled($scopeCode, $scopeType)
            && $this->scopeConfig->isSetFlag(self::XML_PATH_SYNC_CMS, $scopeType, $code);
    }

    /**
     * Check if Category page synchronization is enabled.
     *
     * @param int|string|null $scopeCode
     * @param string          $scopeType
     * @return bool
     */
    public function isCategorySyncEnabled(
        int|string|null $scopeCode = null,
        string $scopeType = ScopeInterface::SCOPE_STORE
    ): bool {
        $code = $this->resolveScopeCode($scopeCode, $scopeType);
        return $this->isSyncEnabled($scopeCode, $scopeType)
            && $this->scopeConfig->isSetFlag(self::XML_PATH_SYNC_CATEGORIES, $scopeType, $code);
    }

    /**
     * Check if Product page synchronization is enabled.
     *
     * @param int|string|null $scopeCode
     * @param string          $scopeType
     * @return bool
     */
    public function isProductSyncEnabled(
        int|string|null $scopeCode = null,
        string $scopeType = ScopeInterface::SCOPE_STORE
    ): bool {
        $code = $this->resolveScopeCode($scopeCode, $scopeType);
        return $this->isSyncEnabled($scopeCode, $scopeType)
            && $this->scopeConfig->isSetFlag(self::XML_PATH_SYNC_PRODUCTS, $scopeType, $code);
    }

    /**
     * Check if product sync should filter to in-stock items only.
     *
     * @param int|string|null $scopeCode
     * @param string          $scopeType
     * @return bool
     */
    public function isInStockOnlySyncEnabled(
        int|string|null $scopeCode = null,
        string $scopeType = ScopeInterface::SCOPE_STORE
    ): bool {
        $code = $this->resolveScopeCode($scopeCode, $scopeType);
        return $this->scopeConfig->isSetFlag(self::XML_PATH_SYNC_IN_STOCK_ONLY, $scopeType, $code);
    }

    /**
     * Check if product price should be synchronized to the ChatBot knowledge base.
     *
     * @param int|string|null $scopeCode
     * @param string          $scopeType
     * @return bool
     */
    public function isProductPriceSyncEnabled(
        int|string|null $scopeCode = null,
        string $scopeType = ScopeInterface::SCOPE_STORE
    ): bool {
        $code = $this->resolveScopeCode($scopeCode, $scopeType);
        return $this->scopeConfig->isSetFlag(self::XML_PATH_SYNC_PRODUCT_PRICE, $scopeType, $code);
    }

    /**
     * Check if product availability (stock status) should be synchronized to the ChatBot knowledge base.
     *
     * @param int|string|null $scopeCode
     * @param string          $scopeType
     * @return bool
     */
    public function isProductAvailabilitySyncEnabled(
        int|string|null $scopeCode = null,
        string $scopeType = ScopeInterface::SCOPE_STORE
    ): bool {
        $code = $this->resolveScopeCode($scopeCode, $scopeType);
        return $this->scopeConfig->isSetFlag(self::XML_PATH_SYNC_PRODUCT_AVAILABILITY, $scopeType, $code);
    }

    /**
     * Check if synchronization should prioritize dedicated custom AI knowledge context over core descriptions.
     *
     * @param int|string|null $scopeCode
     * @param string          $scopeType
     * @return bool
     */
    public function isCustomKnowledgeOnlySyncEnabled(
        int|string|null $scopeCode = null,
        string $scopeType = ScopeInterface::SCOPE_STORE
    ): bool {
        $code = $this->resolveScopeCode($scopeCode, $scopeType);
        $val = $this->scopeConfig->getValue(self::XML_PATH_SYNC_CUSTOM_KNOWLEDGE_ONLY, $scopeType, $code);
        return $val === null || (bool) $val;
    }

    /**
     * Check if automated background queue consumption via cron is enabled.
     *
     * @param int|string|null $scopeCode
     * @param string          $scopeType
     * @return bool
     */
    public function isQueueCronEnabled(
        int|string|null $scopeCode = null,
        string $scopeType = ScopeInterface::SCOPE_STORE
    ): bool {
        $code = $this->resolveScopeCode($scopeCode, $scopeType);
        return $this->scopeConfig->isSetFlag(self::XML_PATH_QUEUE_CRON_ENABLED, $scopeType, $code);
    }

    /**
     * Retrieve the queue batch processing size per run.
     *
     * @param int|string|null $scopeCode
     * @param string          $scopeType
     * @return int
     */
    public function getQueueBatchSize(
        int|string|null $scopeCode = null,
        string $scopeType = ScopeInterface::SCOPE_STORE
    ): int {
        $code = $this->resolveScopeCode($scopeCode, $scopeType);
        $val = (int) $this->scopeConfig->getValue(self::XML_PATH_QUEUE_BATCH_SIZE, $scopeType, $code);
        return $val > 0 ? $val : self::DEFAULT_QUEUE_BATCH_SIZE;
    }

    /**
     * Retrieve the static bundle script URL.
     *
     * @return string
     */
    public function getBundleScriptUrl(): string
    {
        return self::DEFAULT_BUNDLE_SCRIPT_URL;
    }

    /**
     * Retrieve the extension release version.
     *
     * @return string
     */
    public function getExtensionVersion(): string
    {
        return self::EXTENSION_VERSION;
    }

    /**
     * Generate the embeddable HTML script tag snippet from the configured Public UUID.
     *
     * @param string|null $publicUuid Optional UUID override
     * @return string
     */
    public function generateScriptSnippet(?string $publicUuid = null): string
    {
        $uuid = $publicUuid ?? $this->getPublicUuid();
        $uuidString = $uuid !== '' ? $uuid : 'YOUR_CHATBOT_PUBLIC_UUID';

        return "<!-- Clusterify.AI ChatBot Loader - START -->\n" .
            "<script id=\"clusterify-chatbot-script\">\n" .
            "(function () {\n" .
            "    window.__clusterify = window.__clusterify || {};\n" .
            "    window.__clusterify.public_uuid = " . json_encode($uuidString, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ";\n" .
            "    var script = document.createElement(\"script\");\n" .
            "    script.src = " . json_encode(self::DEFAULT_BUNDLE_SCRIPT_URL, JSON_UNESCAPED_SLASHES) . ";\n" .
            "    document.head.appendChild(script);\n" .
            "})();\n" .
            "</script>\n" .
            "<noscript>Please enable JavaScript to access the Clusterify.AI ChatBot.</noscript>\n" .
            "<!-- Clusterify.AI ChatBot Loader - END -->";
    }
}
