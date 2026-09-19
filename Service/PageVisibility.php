<?php
/**
 * ClusterifyAI ChatBot page visibility service
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Layout\PageType\Config as PageTypeConfig;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class PageVisibility
 *
 * Discovers all frontend page types dynamically from core and third-party modules,
 * groups them into logical categories, and determines storefront widget display eligibility.
 */
class PageVisibility
{
    /**
     * Configuration XML path for allowed page types
     */
    public const XML_PATH_ALLOWED_PAGES = 'clusterify_chatbot/page_visibility/allowed_pages';

    /**
     * Category keys
     */
    public const CATEGORY_LANDING = 'landing';
    public const CATEGORY_PRODUCT = 'product';
    public const CATEGORY_CHECKOUT = 'checkout';
    public const CATEGORY_CUSTOMER = 'customer';
    public const CATEGORY_SEARCH = 'search';
    public const CATEGORY_CUSTOM = 'custom';

    /**
     * @param PageTypeConfig        $pageTypeConfig Configuration reader for page_types.xml
     * @param ScopeConfigInterface  $scopeConfig    Scope configuration reader
     * @param StoreManagerInterface $storeManager   Store manager for resolving active store view
     */
    public function __construct(
        private readonly PageTypeConfig $pageTypeConfig,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager
    ) {}

    /**
     * Retrieve all dynamically registered page types across all installed modules.
     *
     * @return array<string, array<string, string>>
     */
    public function getAllPageTypes(): array
    {
        return $this->pageTypeConfig->getPageTypes();
    }

    /**
     * Check if a specific page type is considered a checkout/cart page.
     *
     * @param string $pageTypeCode Technical page type / layout handle code
     * @return bool
     */
    public function isCheckoutPageType(string $pageTypeCode): bool
    {
        return str_starts_with($pageTypeCode, 'checkout_')
            || str_starts_with($pageTypeCode, 'paypal_')
            || str_starts_with($pageTypeCode, 'transparent');
    }

    /**
     * Return default enabled status for a given page type code.
     * Checkout pages default to false (disabled); all other page types default to true (enabled).
     *
     * @param string $pageTypeCode
     * @return bool
     */
    public function getDefaultStatus(string $pageTypeCode): bool
    {
        return !$this->isCheckoutPageType($pageTypeCode);
    }

    /**
     * Group all discovered page types into visual categories.
     *
     * @return array<string, array{title: string, default_disabled: bool, items: array<string, string>}>
     */
    public function getCategorizedPageTypes(): array
    {
        $categories = [
            self::CATEGORY_LANDING => [
                'title' => (string) __('Landing & CMS Pages'),
                'default_disabled' => false,
                'items' => [],
            ],
            self::CATEGORY_PRODUCT => [
                'title' => (string) __('Product & Catalog Pages'),
                'default_disabled' => false,
                'items' => [],
            ],
            self::CATEGORY_CHECKOUT => [
                'title' => (string) __('Checkout & Cart Pages'),
                'default_disabled' => true,
                'items' => [],
            ],
            self::CATEGORY_CUSTOMER => [
                'title' => (string) __('Customer Account Pages'),
                'default_disabled' => false,
                'items' => [],
            ],
            self::CATEGORY_SEARCH => [
                'title' => (string) __('Search & Utility Pages'),
                'default_disabled' => false,
                'items' => [],
            ],
            self::CATEGORY_CUSTOM => [
                'title' => (string) __('Custom Page Types'),
                'default_disabled' => false,
                'items' => [],
            ],
        ];

        $allTypes = $this->getAllPageTypes();

        foreach ($allTypes as $code => $data) {
            $label = trim((string) ($data['label'] ?? $code));
            if ($label === '') {
                $label = $code;
            }

            if (str_starts_with($code, 'cms_')) {
                $categories[self::CATEGORY_LANDING]['items'][$code] = $label;
            } elseif (
                str_starts_with($code, 'catalog_product_') ||
                str_starts_with($code, 'catalog_category_') ||
                str_starts_with($code, 'review_product_') ||
                str_starts_with($code, 'sendfriend_')
            ) {
                $categories[self::CATEGORY_PRODUCT]['items'][$code] = $label;
            } elseif (
                str_starts_with($code, 'checkout_') ||
                str_starts_with($code, 'paypal_') ||
                str_starts_with($code, 'transparent')
            ) {
                $categories[self::CATEGORY_CHECKOUT]['items'][$code] = $label;
            } elseif (
                str_starts_with($code, 'customer_') ||
                str_starts_with($code, 'sales_') ||
                str_starts_with($code, 'wishlist_') ||
                str_starts_with($code, 'newsletter_') ||
                str_starts_with($code, 'downloadable_') ||
                str_starts_with($code, 'review_customer_')
            ) {
                $categories[self::CATEGORY_CUSTOMER]['items'][$code] = $label;
            } elseif (
                str_starts_with($code, 'catalogsearch_') ||
                str_starts_with($code, 'contact_') ||
                str_starts_with($code, 'search_term_') ||
                str_starts_with($code, 'shipping_') ||
                str_starts_with($code, 'rss_')
            ) {
                $categories[self::CATEGORY_SEARCH]['items'][$code] = $label;
            } else {
                $categories[self::CATEGORY_CUSTOM]['items'][$code] = $label;
            }
        }

        return $categories;
    }

    /**
     * Retrieve the configured page visibility settings map for a scope.
     *
     * @param int|string|null $scopeCode Store/Website code or ID
     * @param string          $scopeType Scope type (stores, websites, default)
     * @return array<string, int|string|bool>
     */
    public function getConfiguredPages(
        int|string|null $scopeCode = null,
        string $scopeType = ScopeInterface::SCOPE_STORE
    ): array {
        if ($scopeCode === null && $scopeType === ScopeInterface::SCOPE_STORE) {
            try {
                $scopeCode = (int) $this->storeManager->getStore()->getId();
            } catch (\Exception $e) {
                $scopeCode = null;
            }
        }

        $raw = (string) $this->scopeConfig->getValue(self::XML_PATH_ALLOWED_PAGES, $scopeType, $scopeCode);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Determine whether the chatbot is allowed to appear on a specific storefront page type.
     *
     * @param string          $pageTypeCode Current action name or layout handle (e.g. 'cms_index_index')
     * @param int|string|null $scopeCode    Store/Website code or ID
     * @param string          $scopeType    Scope type (stores, websites, default)
     * @param list<string>    $handles      Additional layout handles active on current page
     * @return bool
     */
    public function isPageAllowed(
        string $pageTypeCode,
        int|string|null $scopeCode = null,
        string $scopeType = ScopeInterface::SCOPE_STORE,
        array $handles = []
    ): bool {
        $configured = $this->getConfiguredPages($scopeCode, $scopeType);

        // 1. Direct match on pageTypeCode
        if (array_key_exists($pageTypeCode, $configured)) {
            return (bool) (int) $configured[$pageTypeCode];
        }

        // 2. Check layout handles for an explicit configured override
        foreach ($handles as $handle) {
            if (array_key_exists($handle, $configured)) {
                return (bool) (int) $configured[$handle];
            }
        }

        // 3. Fall back to default behavior: Checkout pages OFF, others ON
        if ($this->isCheckoutPageType($pageTypeCode)) {
            return false;
        }

        foreach ($handles as $handle) {
            if ($this->isCheckoutPageType($handle)) {
                return false;
            }
        }

        return true;
    }
}
