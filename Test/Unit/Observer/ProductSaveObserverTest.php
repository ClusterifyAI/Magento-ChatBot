<?php
/**
 * ClusterifyAI ChatBot product save observer unit test
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Test\Unit\Observer;

use ClusterifyAI\ChatBot\Model\Queue\Publisher;
use ClusterifyAI\ChatBot\Model\Sync\SyncItem;
use ClusterifyAI\ChatBot\Observer\ProductSaveObserver;
use ClusterifyAI\ChatBot\Service\PlanService;
use Magento\Catalog\Model\Product;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Class ProductSaveObserverTest
 *
 * Tests the product save commit observer.
 */
class ProductSaveObserverTest extends TestCase
{
    /**
     * Test execute exits early when product is missing or has no ID.
     *
     * @return void
     */
    public function testExecuteExitsWhenProductMissing(): void
    {
        $publisherMock = $this->createMock(Publisher::class);
        $publisherMock->expects($this->never())->method('publish');

        $observer = new ProductSaveObserver(
            $publisherMock,
            $this->createStub(PlanService::class),
            $this->createStub(StoreManagerInterface::class),
            $this->createStub(LoggerInterface::class)
        );

        $event = new Event(['product' => null]);
        $eventObserver = new Observer(['event' => $event]);

        $observer->execute($eventObserver);
    }

    /**
     * Test execute publishes message and sanitizes store IDs.
     *
     * @return void
     */
    public function testExecutePublishesProductMessage(): void
    {
        $productStub = $this->createStub(Product::class);
        $productStub->method('getId')->willReturn(15);
        $productStub->method('getStoreIds')->willReturn([0, 1]); // includes admin store 0

        $planServiceStub = $this->createStub(PlanService::class);
        $planServiceStub->method('canSyncEntity')->willReturn(true);

        $publisherMock = $this->createMock(Publisher::class);
        // Only store 1 should be published (store 0 filtered out)
        $publisherMock->expects($this->once())
            ->method('publish')
            ->with('product', 15, 1, SyncItem::ACTION_UPSERT);

        $observer = new ProductSaveObserver(
            $publisherMock,
            $planServiceStub,
            $this->createStub(StoreManagerInterface::class),
            $this->createStub(LoggerInterface::class)
        );

        $event = new Event(['product' => $productStub]);
        $eventObserver = new Observer(['event' => $event]);

        $observer->execute($eventObserver);
    }

    /**
     * Test execute expands global product (store_ids empty) to active storefront stores.
     *
     * @return void
     */
    public function testExecuteExpandsGlobalProductToActiveStores(): void
    {
        $productStub = $this->createStub(Product::class);
        $productStub->method('getId')->willReturn(20);
        $productStub->method('getStoreIds')->willReturn([]); // Global scope

        $store1 = $this->createStub(StoreInterface::class);
        $store1->method('getId')->willReturn(1);
        $store2 = $this->createStub(StoreInterface::class);
        $store2->method('getId')->willReturn(2);

        $storeManagerStub = $this->createStub(StoreManagerInterface::class);
        $storeManagerStub->method('getStores')->willReturn([$store1, $store2]);

        $planServiceStub = $this->createStub(PlanService::class);
        $planServiceStub->method('canSyncEntity')->willReturn(true);

        $publisherMock = $this->createMock(Publisher::class);
        $publisherMock->expects($this->exactly(2))->method('publish');

        $observer = new ProductSaveObserver(
            $publisherMock,
            $planServiceStub,
            $storeManagerStub,
            $this->createStub(LoggerInterface::class)
        );

        $event = new Event(['product' => $productStub]);
        $eventObserver = new Observer(['event' => $event]);

        $observer->execute($eventObserver);
    }
}
