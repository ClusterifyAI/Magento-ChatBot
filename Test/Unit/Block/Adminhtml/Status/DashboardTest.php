<?php
/**
 * ClusterifyAI ChatBot admin dashboard block unit test
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Test\Unit\Block\Adminhtml\Status;

use Clusterify\ClusterifyClient;
use Clusterify\DTO\KnowledgeUrl\KnowledgeUrlStats;
use Clusterify\Resources\KnowledgeUrlResource;
use ClusterifyAI\ChatBot\Block\Adminhtml\Status\Dashboard;
use ClusterifyAI\ChatBot\Model\Config;
use ClusterifyAI\ChatBot\Service\ClientFactory;
use ClusterifyAI\ChatBot\Service\PageVisibility;
use ClusterifyAI\ChatBot\Service\PlanService;
use Exception;
use Magento\Backend\Block\Template\Context;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\Amqp\Config as AmqpConfig;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Indexer\IndexerInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Indexer\StateInterface;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Class DashboardTest
 *
 * Unit tests for ClusterifyAI\ChatBot\Block\Adminhtml\Status\Dashboard.
 */
#[AllowMockObjectsWithoutExpectations]
class DashboardTest extends TestCase
{
    private Context $contextStub;
    private RequestInterface|MockObject $requestMock;
    private StoreManagerInterface|MockObject $storeManagerMock;
    private PlanService $planServiceStub;
    private Config $configStub;
    private ClientFactory $clientFactoryStub;
    private IndexerRegistry $indexerRegistryStub;
    private PageVisibility $pageVisibilityStub;
    private CacheInterface $cacheStub;
    private TimezoneInterface $timezoneStub;
    private LoggerInterface|MockObject $loggerMock;
    private Dashboard $dashboard;

    /**
     * Set up test instances.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->contextStub = $this->createStub(Context::class);
        $this->requestMock = $this->createMock(RequestInterface::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->planServiceStub = $this->createStub(PlanService::class);
        $this->configStub = $this->createStub(Config::class);
        $this->clientFactoryStub = $this->createStub(ClientFactory::class);
        $this->indexerRegistryStub = $this->createStub(IndexerRegistry::class);
        $this->pageVisibilityStub = $this->createStub(PageVisibility::class);
        $this->cacheStub = $this->createStub(CacheInterface::class);
        $this->timezoneStub = $this->createStub(TimezoneInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->contextStub->method('getRequest')->willReturn($this->requestMock);
        $this->contextStub->method('getStoreManager')->willReturn($this->storeManagerMock);

        $jsonHelperStub = $this->createStub(JsonHelper::class);
        $directoryHelperStub = $this->createStub(DirectoryHelper::class);
        $amqpConfigStub = $this->createStub(AmqpConfig::class);

        $this->dashboard = new Dashboard(
            $this->contextStub,
            $this->planServiceStub,
            $this->configStub,
            $this->clientFactoryStub,
            $this->indexerRegistryStub,
            $this->pageVisibilityStub,
            $this->cacheStub,
            $this->timezoneStub,
            $this->loggerMock,
            $amqpConfigStub,
            [],
            $jsonHelperStub,
            $directoryHelperStub
        );
    }

    /**
     * Test getActiveStoreId returns store ID when store code is valid.
     *
     * @return void
     */
    public function testGetActiveStoreIdReturnsStoreId(): void
    {
        $storeStub = $this->createStub(StoreInterface::class);
        $storeStub->method('getId')->willReturn(2);

        $this->requestMock->expects($this->once())->method('getParam')->with('store')->willReturn('french');
        $this->storeManagerMock->expects($this->once())->method('getStore')->with('french')->willReturn($storeStub);

        $this->assertSame(2, $this->dashboard->getActiveStoreId());
    }

    /**
     * Test getActiveStoreId catches NoSuchEntityException safely and returns null.
     *
     * @return void
     */
    public function testGetActiveStoreIdHandlesNoSuchEntityExceptionGracefully(): void
    {
        $this->requestMock->expects($this->once())->method('getParam')->with('store')->willReturn('non_existent_code');
        $this->storeManagerMock->expects($this->once())->method('getStore')
            ->with('non_existent_code')
            ->willThrowException(new NoSuchEntityException(__('Store does not exist.')));

        $this->assertNull($this->dashboard->getActiveStoreId());
    }

    /**
     * Test getIndexerStatusList returns mapped indexers.
     *
     * @return void
     */
    public function testGetIndexerStatusList(): void
    {
        $indexerStub = $this->createStub(IndexerInterface::class);
        $indexerStub->method('getStatus')->willReturn(StateInterface::STATUS_VALID);
        $indexerStub->method('isScheduled')->willReturn(true);
        $indexerStub->method('getLatestUpdated')->willReturn('2026-09-18 12:00:00');

        $this->timezoneStub->method('formatDateTime')->willReturn('Sep 18, 2026, 12:00 PM');
        $this->indexerRegistryStub->method('get')->willReturn($indexerStub);

        $list = $this->dashboard->getIndexerStatusList();
        $this->assertCount(3, $list);
        $this->assertSame('clusterify_chatbot_cms', $list[0]['id']);
        $this->assertTrue($list[0]['is_valid']);
        $this->assertTrue($list[0]['is_scheduled']);
        $this->assertSame('Sep 18, 2026, 12:00 PM', $list[0]['last_updated']);
    }

    /**
     * Test getPageVisibilitySummary aggregates totals.
     *
     * @return void
     */
    public function testGetPageVisibilitySummary(): void
    {
        $this->pageVisibilityStub->method('getAllPageTypes')->willReturn([
            'cms_index_index' => ['label' => 'Home'],
            'checkout_cart_index' => ['label' => 'Cart'],
        ]);

        $this->pageVisibilityStub->method('isPageAllowed')->willReturnCallback(function (string $code) {
            return $code === 'cms_index_index';
        });

        $summary = $this->dashboard->getPageVisibilitySummary();
        $this->assertSame(2, $summary['total']);
        $this->assertSame(1, $summary['allowed']);
        $this->assertSame(1, $summary['blocked']);
    }

    /**
     * Test getQuotaStats returns null when credentials or plan permission are missing.
     *
     * @return void
     */
    public function testGetQuotaStatsReturnsNullWhenUnconfiguredOrNotAllowed(): void
    {
        $this->configStub->method('getPublicKey')->willReturn('');
        $this->assertNull($this->dashboard->getQuotaStats());
    }

    /**
     * Test getQuotaStats returns cached stats when available.
     *
     * @return void
     */
    public function testGetQuotaStatsReturnsCachedData(): void
    {
        $this->configStub->method('getPublicKey')->willReturn('pk_live_123');
        $this->configStub->method('getSecretKey')->willReturn('sk_live_456');
        $this->planServiceStub->method('isUrlKnowledgeAllowed')->willReturn(true);

        $cachedJson = json_encode([
            'total' => 15,
            'enabled' => 15,
            'disabled' => 0,
            'max_urls' => 1000,
            'max_content_length' => 20000,
        ]);

        $this->cacheStub->method('load')->willReturn($cachedJson);

        $stats = $this->dashboard->getQuotaStats();
        $this->assertInstanceOf(KnowledgeUrlStats::class, $stats);
        $this->assertSame(15, $stats->total);
        $this->assertSame(1000, $stats->maxUrls);
    }

    /**
     * Test getQuotaStats handles API exception safely and logs warning.
     *
     * @return void
     */
    public function testGetQuotaStatsHandlesApiExceptionGracefully(): void
    {
        $this->configStub->method('getPublicKey')->willReturn('pk_live_123');
        $this->configStub->method('getSecretKey')->willReturn('sk_live_456');
        $this->planServiceStub->method('isUrlKnowledgeAllowed')->willReturn(true);

        $this->cacheStub->method('load')->willReturn(false);

        $this->clientFactoryStub->method('create')
            ->willThrowException(new Exception('Network timeout'));

        $this->loggerMock->expects($this->once())
            ->method('warning');

        $this->assertNull($this->dashboard->getQuotaStats());
    }

    /**
     * Test getQueueStatusList returns queues structure.
     *
     * @return void
     */
    public function testGetQueueStatusListReturnsQueues(): void
    {
        $list = $this->dashboard->getQueueStatusList();
        $this->assertCount(3, $list);
        $this->assertSame('clusterify.chatbot.sync.cms', $list[0]['queue_name']);
        $this->assertSame(0, $list[0]['message_count']);
        $this->assertFalse($list[0]['is_available']);
    }

    /**
     * Test getInfoUrl returns correct path.
     *
     * @return void
     */
    public function testGetInfoUrl(): void
    {
        $urlBuilderMock = $this->createMock(\Magento\Framework\UrlInterface::class);
        $urlBuilderMock->expects($this->once())
            ->method('getUrl')
            ->with('clusterify_chatbot/info/index')
            ->willReturn('http://magento.test/admin/info');

        $contextStub = $this->createStub(Context::class);
        $contextStub->method('getUrlBuilder')->willReturn($urlBuilderMock);

        $jsonHelperStub = $this->createStub(JsonHelper::class);
        $directoryHelperStub = $this->createStub(DirectoryHelper::class);
        $amqpConfigStub = $this->createStub(AmqpConfig::class);

        $dashboard = new Dashboard(
            $contextStub,
            $this->planServiceStub,
            $this->configStub,
            $this->clientFactoryStub,
            $this->indexerRegistryStub,
            $this->pageVisibilityStub,
            $this->cacheStub,
            $this->timezoneStub,
            $this->loggerMock,
            $amqpConfigStub,
            [],
            $jsonHelperStub,
            $directoryHelperStub
        );

        $this->assertSame('http://magento.test/admin/info', $dashboard->getInfoUrl());
    }
}
