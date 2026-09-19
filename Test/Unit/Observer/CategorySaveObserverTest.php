<?php
/**
 * ClusterifyAI ChatBot category save observer unit test
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
use ClusterifyAI\ChatBot\Observer\CategorySaveObserver;
use ClusterifyAI\ChatBot\Service\PlanService;
use Magento\Catalog\Model\Category;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Class CategorySaveObserverTest
 *
 * Tests the category save commit observer.
 */
class CategorySaveObserverTest extends TestCase
{
    /**
     * Test execute skips root categories (level <= 1).
     *
     * @return void
     */
    public function testExecuteSkipsRootCategories(): void
    {
        $categoryStub = $this->createStub(Category::class);
        $categoryStub->method('getId')->willReturn(1);
        $categoryStub->method('getLevel')->willReturn(1); // Root category

        $publisherMock = $this->createMock(Publisher::class);
        $publisherMock->expects($this->never())->method('publish');

        $observer = new CategorySaveObserver(
            $publisherMock,
            $this->createStub(PlanService::class),
            $this->createStub(StoreManagerInterface::class),
            $this->createStub(LoggerInterface::class)
        );

        $event = new Event(['category' => $categoryStub]);
        $eventObserver = new Observer(['event' => $event]);

        $observer->execute($eventObserver);
    }

    /**
     * Test execute publishes category message and filters store 0.
     *
     * @return void
     */
    public function testExecutePublishesValidCategoryMessage(): void
    {
        $categoryStub = $this->createStub(Category::class);
        $categoryStub->method('getId')->willReturn(5);
        $categoryStub->method('getLevel')->willReturn(2); // Subcategory
        $categoryStub->method('getStoreIds')->willReturn([0, 1]); // includes store 0

        $planServiceStub = $this->createStub(PlanService::class);
        $planServiceStub->method('canSyncEntity')->willReturn(true);

        $publisherMock = $this->createMock(Publisher::class);
        // Only store 1 should be published
        $publisherMock->expects($this->once())
            ->method('publish')
            ->with('category', 5, 1, SyncItem::ACTION_UPSERT);

        $observer = new CategorySaveObserver(
            $publisherMock,
            $planServiceStub,
            $this->createStub(StoreManagerInterface::class),
            $this->createStub(LoggerInterface::class)
        );

        $event = new Event(['category' => $categoryStub]);
        $eventObserver = new Observer(['event' => $event]);

        $observer->execute($eventObserver);
    }

    /**
     * Test execute expands global category (store_ids only includes store 0 or empty).
     *
     * @return void
     */
    public function testExecuteExpandsGlobalCategoryToActiveStores(): void
    {
        $categoryStub = $this->createStub(Category::class);
        $categoryStub->method('getId')->willReturn(14);
        $categoryStub->method('getLevel')->willReturn(2);
        $categoryStub->method('getStoreIds')->willReturn([0]); // Only root store 0

        $store1 = $this->createStub(\Magento\Store\Api\Data\StoreInterface::class);
        $store1->method('getId')->willReturn(1);
        $storeManagerStub = $this->createStub(StoreManagerInterface::class);
        $storeManagerStub->method('getStores')->willReturn([$store1]);

        $planServiceStub = $this->createStub(PlanService::class);
        $planServiceStub->method('canSyncEntity')->willReturn(true);

        $publisherMock = $this->createMock(Publisher::class);
        $publisherMock->expects($this->once())
            ->method('publish')
            ->with('category', 14, 1, SyncItem::ACTION_UPSERT);

        $observer = new CategorySaveObserver(
            $publisherMock,
            $planServiceStub,
            $storeManagerStub,
            $this->createStub(LoggerInterface::class)
        );

        $event = new Event(['category' => $categoryStub]);
        $eventObserver = new Observer(['event' => $event]);

        $observer->execute($eventObserver);
    }
}
