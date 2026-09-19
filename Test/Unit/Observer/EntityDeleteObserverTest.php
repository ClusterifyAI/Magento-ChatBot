<?php
/**
 * ClusterifyAI ChatBot entity pre-deletion observer unit test
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Test\Unit\Observer;

use ClusterifyAI\ChatBot\Model\Config;
use ClusterifyAI\ChatBot\Model\Queue\Publisher;
use ClusterifyAI\ChatBot\Model\Sync\SyncItem;
use ClusterifyAI\ChatBot\Observer\EntityDeleteObserver;
use ClusterifyAI\ChatBot\Service\PlanService;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Cms\Model\Page;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Class EntityDeleteObserverTest
 *
 * Unit tests for ClusterifyAI\ChatBot\Observer\EntityDeleteObserver across multi-store products, categories, and CMS pages.
 */
#[AllowMockObjectsWithoutExpectations]
class EntityDeleteObserverTest extends TestCase
{
    private Publisher|MockObject $publisherMock;
    private StoreManagerInterface $storeManagerStub;
    private Store $storeStub1;
    private Store $storeStub2;
    private Config $configStub;
    private PlanService $planServiceStub;
    private LoggerInterface $loggerStub;
    private EntityDeleteObserver $observer;

    /**
     * Set up test instances.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->publisherMock = $this->createMock(PublisherInterfaceProxy::class);
        $this->storeManagerStub = $this->createStub(StoreManagerInterface::class);
        $this->storeStub1 = $this->createStub(Store::class);
        $this->storeStub2 = $this->createStub(Store::class);
        $this->configStub = $this->createStub(Config::class);
        $this->planServiceStub = $this->createStub(PlanService::class);
        $this->loggerStub = $this->createStub(LoggerInterface::class);

        $this->storeStub1->method('getId')->willReturn(1);
        $this->storeStub1->method('getBaseUrl')->willReturn('http://magento.test/');

        $this->storeStub2->method('getId')->willReturn(2);
        $this->storeStub2->method('getBaseUrl')->willReturn('http://magento.test/fr/');

        $this->storeManagerStub->method('getStores')->willReturn([$this->storeStub1, $this->storeStub2]);
        $this->planServiceStub->method('canSyncEntity')->willReturn(true);

        $this->observer = new EntityDeleteObserver(
            $this->publisherMock,
            $this->storeManagerStub,
            $this->configStub,
            $this->planServiceStub,
            $this->loggerStub
        );
    }

    /**
     * Test multi-store product deletion publishes delete message per enabled store.
     *
     * @return void
     */
    public function testExecutePublishesMultiStoreProductDeletion(): void
    {
        $this->configStub->method('isProductSyncEnabled')->willReturn(true);

        $productStub = $this->createStub(Product::class);
        $productStub->method('getId')->willReturn(42);
        $productStub->method('getProductUrl')->willReturn('http://magento.test/deleted-product.html');

        $eventStub = $this->createStub(Event::class);
        $eventStub->method('getName')->willReturn('catalog_product_delete_before');
        $eventStub->method('getData')->willReturnCallback(fn (string $key) => $key === 'product' ? $productStub : null);

        $observerStub = $this->createStub(Observer::class);
        $observerStub->method('getEvent')->willReturn($eventStub);

        $this->publisherMock->expects($this->exactly(2))
            ->method('publish')
            ->with(
                'product',
                42,
                $this->logicalOr($this->equalTo(1), $this->equalTo(2)),
                SyncItem::ACTION_DELETE,
                'http://magento.test/deleted-product.html'
            );

        $this->observer->execute($observerStub);
    }

    /**
     * Test category deletion publishes delete message.
     *
     * @return void
     */
    public function testExecutePublishesCategoryDeletion(): void
    {
        $this->configStub->method('isCategorySyncEnabled')->willReturn(true);

        $categoryStub = $this->createStub(Category::class);
        $categoryStub->method('getId')->willReturn(15);
        $categoryStub->method('getUrl')->willReturn('http://magento.test/deleted-category.html');

        $eventStub = $this->createStub(Event::class);
        $eventStub->method('getName')->willReturn('catalog_category_delete_before');
        $eventStub->method('getData')->willReturnCallback(fn (string $key) => $key === 'category' ? $categoryStub : null);

        $observerStub = $this->createStub(Observer::class);
        $observerStub->method('getEvent')->willReturn($eventStub);

        $this->publisherMock->expects($this->exactly(2))
            ->method('publish')
            ->with(
                'category',
                15,
                $this->logicalOr($this->equalTo(1), $this->equalTo(2)),
                SyncItem::ACTION_DELETE,
                'http://magento.test/deleted-category.html'
            );

        $this->observer->execute($observerStub);
    }

    /**
     * Test CMS page deletion publishes delete message.
     *
     * @return void
     */
    public function testExecutePublishesCmsPageDeletion(): void
    {
        $this->configStub->method('isCmsSyncEnabled')->willReturn(true);

        $pageStub = $this->createStub(Page::class);
        $pageStub->method('getId')->willReturn(8);
        $pageStub->method('getIdentifier')->willReturn('about-us');

        $eventStub = $this->createStub(Event::class);
        $eventStub->method('getName')->willReturn('cms_page_delete_before');
        $eventStub->method('getData')->willReturnCallback(fn (string $key) => $key === 'page' ? $pageStub : null);

        $observerStub = $this->createStub(Observer::class);
        $observerStub->method('getEvent')->willReturn($eventStub);

        $this->publisherMock->expects($this->exactly(2))
            ->method('publish')
            ->with(
                'cms',
                8,
                $this->logicalOr($this->equalTo(1), $this->equalTo(2)),
                SyncItem::ACTION_DELETE,
                $this->logicalOr(
                    $this->equalTo('http://magento.test/about-us'),
                    $this->equalTo('http://magento.test/fr/about-us')
                )
            );

        $this->observer->execute($observerStub);
    }
}

/**
 * Proxy mock class for Publisher testing
 */
class PublisherInterfaceProxy extends Publisher
{
    public function __construct() {}
}
