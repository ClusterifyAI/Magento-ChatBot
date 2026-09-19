<?php
/**
 * ClusterifyAI ChatBot page visibility service unit test
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Test\Unit\Service;

use ClusterifyAI\ChatBot\Service\PageVisibility;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Layout\PageType\Config as PageTypeConfig;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class PageVisibilityTest
 *
 * Unit tests for ClusterifyAI\ChatBot\Service\PageVisibility.
 */
class PageVisibilityTest extends TestCase
{
    private PageTypeConfig $pageTypeConfigStub;
    private ScopeConfigInterface $scopeConfigStub;
    private StoreManagerInterface $storeManagerStub;
    private StoreInterface $storeStub;
    private PageVisibility $service;

    /**
     * Set up test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->pageTypeConfigStub = $this->createStub(PageTypeConfig::class);
        $this->scopeConfigStub = $this->createStub(ScopeConfigInterface::class);
        $this->storeManagerStub = $this->createStub(StoreManagerInterface::class);
        $this->storeStub = $this->createStub(StoreInterface::class);

        $this->storeStub->method('getId')->willReturn(1);
        $this->storeManagerStub->method('getStore')->willReturn($this->storeStub);

        $this->service = new PageVisibility(
            $this->pageTypeConfigStub,
            $this->scopeConfigStub,
            $this->storeManagerStub
        );
    }

    /**
     * Test checkout pages are identified correctly.
     *
     * @return void
     */
    public function testIsCheckoutPageType(): void
    {
        $this->assertTrue($this->service->isCheckoutPageType('checkout_cart_index'));
        $this->assertTrue($this->service->isCheckoutPageType('checkout_index_index'));
        $this->assertTrue($this->service->isCheckoutPageType('paypal_express_review'));
        $this->assertFalse($this->service->isCheckoutPageType('cms_index_index'));
        $this->assertFalse($this->service->isCheckoutPageType('catalog_product_view'));
    }

    /**
     * Test default status rules (checkout disabled, others enabled).
     *
     * @return void
     */
    public function testGetDefaultStatus(): void
    {
        $this->assertFalse($this->service->getDefaultStatus('checkout_cart_index'));
        $this->assertTrue($this->service->getDefaultStatus('cms_index_index'));
        $this->assertTrue($this->service->getDefaultStatus('catalog_product_view'));
    }

    /**
     * Test isPageAllowed respects default fallback when unconfigured.
     *
     * @return void
     */
    public function testIsPageAllowedDefaultBehavior(): void
    {
        $this->scopeConfigStub->method('getValue')->willReturn('');

        // Checkout is blocked by default
        $this->assertFalse($this->service->isPageAllowed('checkout_cart_index'));

        // Product view is allowed by default
        $this->assertTrue($this->service->isPageAllowed('catalog_product_view'));
    }

    /**
     * Test isPageAllowed respects explicit configured overrides.
     *
     * @return void
     */
    public function testIsPageAllowedRespectsExplicitOverride(): void
    {
        $configuredJson = json_encode([
            'checkout_cart_index' => 1, // explicitly enabled
            'catalog_product_view' => 0, // explicitly disabled
        ]);

        $this->scopeConfigStub->method('getValue')->willReturn($configuredJson);

        // Cart is now enabled because of override
        $this->assertTrue($this->service->isPageAllowed('checkout_cart_index'));

        // Product view is now disabled because of override
        $this->assertFalse($this->service->isPageAllowed('catalog_product_view'));
    }
}
