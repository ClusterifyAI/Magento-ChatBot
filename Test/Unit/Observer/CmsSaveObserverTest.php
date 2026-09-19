<?php
/**
 * ClusterifyAI ChatBot CMS save observer unit test
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
use ClusterifyAI\ChatBot\Observer\CmsSaveObserver;
use ClusterifyAI\ChatBot\Service\PlanService;
use Magento\Cms\Model\Page;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Class CmsSaveObserverTest
 *
 * Tests the CMS page save commit observer.
 */
class CmsSaveObserverTest extends TestCase
{
    /**
     * Test execute exits early when page is missing or has no ID.
     *
     * @return void
     */
    public function testExecuteExitsWhenPageMissing(): void
    {
        $publisherMock = $this->createMock(Publisher::class);
        $publisherMock->expects($this->never())->method('publish');

        $observer = new CmsSaveObserver(
            $publisherMock,
            $this->createStub(PlanService::class),
            $this->createStub(StoreManagerInterface::class),
            $this->createStub(LoggerInterface::class)
        );

        $event = new Event(['object' => null]);
        $eventObserver = new Observer(['event' => $event]);

        $observer->execute($eventObserver);
    }

    /**
     * Test execute publishes CMS page message.
     *
     * @return void
     */
    public function testExecutePublishesCmsPageMessage(): void
    {
        $pageStub = $this->createStub(Page::class);
        $pageStub->method('getId')->willReturn(8);
        $pageStub->method('getStores')->willReturn([1]);

        $planServiceStub = $this->createStub(PlanService::class);
        $planServiceStub->method('canSyncEntity')->willReturn(true);

        $publisherMock = $this->createMock(Publisher::class);
        $publisherMock->expects($this->once())
            ->method('publish')
            ->with('cms', 8, 1, SyncItem::ACTION_UPSERT);

        $observer = new CmsSaveObserver(
            $publisherMock,
            $planServiceStub,
            $this->createStub(StoreManagerInterface::class),
            $this->createStub(LoggerInterface::class)
        );

        $event = new Event(['object' => $pageStub]);
        $eventObserver = new Observer(['event' => $event]);

        $observer->execute($eventObserver);
    }

    /**
     * Test execute expands CMS page assigned to all store views (stores = [0]).
     *
     * @return void
     */
    public function testExecuteExpandsAllStoreViewsCmsPage(): void
    {
        $pageStub = $this->createStub(Page::class);
        $pageStub->method('getId')->willReturn(12);
        $pageStub->method('getStores')->willReturn([0]); // All store views

        $store1 = $this->createStub(\Magento\Store\Api\Data\StoreInterface::class);
        $store1->method('getId')->willReturn(1);
        $storeManagerStub = $this->createStub(StoreManagerInterface::class);
        $storeManagerStub->method('getStores')->willReturn([$store1]);

        $planServiceStub = $this->createStub(PlanService::class);
        $planServiceStub->method('canSyncEntity')->willReturn(true);

        $publisherMock = $this->createMock(Publisher::class);
        $publisherMock->expects($this->once())
            ->method('publish')
            ->with('cms', 12, 1, SyncItem::ACTION_UPSERT);

        $observer = new CmsSaveObserver(
            $publisherMock,
            $planServiceStub,
            $storeManagerStub,
            $this->createStub(LoggerInterface::class)
        );

        $event = new Event(['object' => $pageStub]);
        $eventObserver = new Observer(['event' => $event]);

        $observer->execute($eventObserver);
    }
}
