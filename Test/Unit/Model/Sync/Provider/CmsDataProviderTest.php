<?php
/**
 * ClusterifyAI ChatBot CMS data provider unit test
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
use ClusterifyAI\ChatBot\Model\Sync\Provider\CmsDataProvider;
use ClusterifyAI\ChatBot\Model\Sync\SyncItem;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Class CmsDataProviderTest
 *
 * Unit tests for ClusterifyAI\ChatBot\Model\Sync\Provider\CmsDataProvider.
 */
class CmsDataProviderTest extends TestCase
{
    private PageRepositoryInterface $pageRepositoryStub;
    private StoreManagerInterface $storeManagerStub;
    private ScopeConfigInterface $scopeConfigStub;
    private Store $storeStub;
    private HtmlToMarkdown $htmlConverter;
    private Config $configStub;
    private LoggerInterface $loggerStub;
    private CmsDataProvider $provider;

    /**
     * Set up test instances.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->pageRepositoryStub = $this->createStub(PageRepositoryInterface::class);
        $this->storeManagerStub = $this->createStub(StoreManagerInterface::class);
        $this->scopeConfigStub = $this->createStub(ScopeConfigInterface::class);
        $this->storeStub = $this->createStub(Store::class);
        $this->htmlConverter = new HtmlToMarkdown();
        $this->configStub = $this->createStub(Config::class);
        $this->loggerStub = $this->createStub(LoggerInterface::class);

        $this->storeStub->method('getBaseUrl')->willReturn('http://magento.test/');
        $this->storeManagerStub->method('getStore')->willReturn($this->storeStub);

        $this->provider = new CmsDataProvider(
            $this->pageRepositoryStub,
            $this->storeManagerStub,
            $this->scopeConfigStub,
            $this->htmlConverter,
            $this->configStub,
            $this->loggerStub
        );
    }

    /**
     * Test excluded identifiers return null.
     *
     * @return void
     */
    public function testExtractReturnsNullForExcludedPages(): void
    {
        $pageStub = $this->createStub(PageInterface::class);
        $pageStub->method('getIdentifier')->willReturn('no-route');

        $this->pageRepositoryStub->method('getById')->willReturn($pageStub);

        $result = $this->provider->extract(1, 1);
        $this->assertNull($result);
    }

    /**
     * Test active CMS page generates correct URL without .html suffix.
     *
     * @return void
     */
    public function testExtractGeneratesUrlWithoutHtmlSuffix(): void
    {
        $pageStub = $this->createStub(\Magento\Cms\Model\Page::class);
        $pageStub->method('getIdentifier')->willReturn('about-us');
        $pageStub->method('getTitle')->willReturn('About Us');
        $pageStub->method('getContent')->willReturn('<p>Welcome to our store.</p>');
        $pageStub->method('isActive')->willReturn(true);
        $pageStub->method('getStores')->willReturn([0]); // All store views

        $this->pageRepositoryStub->method('getById')->willReturn($pageStub);

        $result = $this->provider->extract(5, 1);
        $this->assertInstanceOf(SyncItem::class, $result);
        $this->assertSame(SyncItem::ACTION_UPSERT, $result->action);
        $this->assertSame('http://magento.test/about-us', $result->url);
        $this->assertStringContainsString('# About Us', $result->content);
        $this->assertStringContainsString('Welcome to our store.', $result->content);
    }

    /**
     * Test home page resolves to root base URL.
     *
     * @return void
     */
    public function testExtractResolvesHomePageToRoot(): void
    {
        $pageStub = $this->createStub(\Magento\Cms\Model\Page::class);
        $pageStub->method('getIdentifier')->willReturn('home');
        $pageStub->method('getTitle')->willReturn('Home');
        $pageStub->method('getContent')->willReturn('<h1>Home Page</h1>');
        $pageStub->method('isActive')->willReturn(true);
        $pageStub->method('getStores')->willReturn([1]);

        $this->pageRepositoryStub->method('getById')->willReturn($pageStub);

        $result = $this->provider->extract(2, 1);
        $this->assertInstanceOf(SyncItem::class, $result);
        $this->assertSame('http://magento.test/', $result->url);
    }

    /**
     * Test inactive CMS page emits ACTION_DELETE.
     *
     * @return void
     */
    public function testExtractInactivePageEmitsDelete(): void
    {
        $pageStub = $this->createStub(\Magento\Cms\Model\Page::class);
        $pageStub->method('getIdentifier')->willReturn('about-us');
        $pageStub->method('isActive')->willReturn(false);
        $pageStub->method('getStores')->willReturn([1]);

        $this->pageRepositoryStub->method('getById')->willReturn($pageStub);

        $result = $this->provider->extract(5, 1);
        $this->assertInstanceOf(SyncItem::class, $result);
        $this->assertSame(SyncItem::ACTION_DELETE, $result->action);
        $this->assertFalse($result->isEnabled);
    }

    /**
     * Test page not assigned to requested store view returns null.
     *
     * @return void
     */
    public function testExtractReturnsNullWhenPageNotAssignedToStore(): void
    {
        $pageStub = $this->createStub(\Magento\Cms\Model\Page::class);
        $pageStub->method('getIdentifier')->willReturn('about-us');
        $pageStub->method('isActive')->willReturn(true);
        $pageStub->method('getStores')->willReturn([2]); // Only assigned to Store 2

        $this->pageRepositoryStub->method('getById')->willReturn($pageStub);

        // Requested for Store 1 -> should return null
        $result = $this->provider->extract(5, 1);
        $this->assertNull($result);
    }

    /**
     * Test active CMS page with custom knowledge attribute appends AI context section.
     *
     * @return void
     */
    public function testExtractActivePageIncludesCustomKnowledge(): void
    {
        $pageStub = $this->createStub(\Magento\Cms\Model\Page::class);
        $pageStub->method('getIdentifier')->willReturn('shipping-policy');
        $pageStub->method('getTitle')->willReturn('Shipping Policy');
        $pageStub->method('getContent')->willReturn('<p>Standard shipping terms.</p>');
        $pageStub->method('isActive')->willReturn(true);
        $pageStub->method('getStores')->willReturn([0]);
        $pageStub->method('getData')->willReturnCallback(function (string $key) {
            return match ($key) {
                'clusterify_chatbot_knowledge' => "Summary: Free express delivery on orders over $50.\nRestrictions: Continental US only.",
                default => null,
            };
        });

        $this->pageRepositoryStub->method('getById')->willReturn($pageStub);

        $result = $this->provider->extract(8, 1);
        $this->assertInstanceOf(SyncItem::class, $result);
        $this->assertSame(SyncItem::ACTION_UPSERT, $result->action);
        $this->assertStringContainsString('## AI Knowledge & Context', $result->content);
        $this->assertStringContainsString('Summary: Free express delivery on orders over $50.', $result->content);
        $this->assertStringContainsString('Restrictions: Continental US only.', $result->content);
    }

    /**
     * Test extract returns null and logs warning when repository throws an exception.
     *
     * @return void
     */
    public function testExtractReturnsNullAndLogsWarningOnThrowable(): void
    {
        $this->pageRepositoryStub->method('getById')->willThrowException(new \RuntimeException('DB error'));

        $loggerMock = $this->createMock(\Psr\Log\LoggerInterface::class);
        $loggerMock->expects($this->once())->method('warning');

        $provider = new CmsDataProvider(
            $this->pageRepositoryStub,
            $this->storeManagerStub,
            $this->scopeConfigStub,
            $this->htmlConverter,
            $this->configStub,
            $loggerMock
        );

        $result = $provider->extract(99, 1);
        $this->assertNull($result);
    }
}
