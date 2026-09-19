<?php
/**
 * ClusterifyAI ChatBot CMS indexer unit test
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Test\Unit\Model\Indexer;

use ClusterifyAI\ChatBot\Model\Indexer\Cms;
use ClusterifyAI\ChatBot\Model\Queue\Publisher;
use ClusterifyAI\ChatBot\Service\PlanService;
use Magento\Cms\Model\ResourceModel\Page\Collection as PageCollection;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class CmsTest
 *
 * Tests the CMS page indexer actions.
 */
class CmsTest extends TestCase
{
    /**
     * Test executeList filters non-positive IDs and publishes unique chunks.
     *
     * @return void
     */
    public function testExecuteListPublishesUniquePositiveIds(): void
    {
        $storeStub = $this->createStub(StoreInterface::class);
        $storeStub->method('getId')->willReturn(1);

        $storeManagerStub = $this->createStub(StoreManagerInterface::class);
        $storeManagerStub->method('getStores')->willReturn([$storeStub]);

        $planServiceStub = $this->createStub(PlanService::class);
        $planServiceStub->method('canSyncEntity')->willReturn(true);

        $publisherMock = $this->createMock(Publisher::class);
        $publisherMock->expects($this->once())
            ->method('publishBatch')
            ->with('cms', [1, 2], 1);

        $indexer = new Cms(
            $publisherMock,
            $this->createStub(PageCollectionFactory::class),
            $storeManagerStub,
            $planServiceStub
        );

        $indexer->executeList(['1', 0, 2, 1]);
    }

    /**
     * Test executeRow delegates to executeList.
     *
     * @return void
     */
    public function testExecuteRowDelegatesToExecuteList(): void
    {
        $storeStub = $this->createStub(StoreInterface::class);
        $storeStub->method('getId')->willReturn(1);

        $storeManagerStub = $this->createStub(StoreManagerInterface::class);
        $storeManagerStub->method('getStores')->willReturn([$storeStub]);

        $planServiceStub = $this->createStub(PlanService::class);
        $planServiceStub->method('canSyncEntity')->willReturn(true);

        $publisherMock = $this->createMock(Publisher::class);
        $publisherMock->expects($this->once())
            ->method('publishBatch')
            ->with('cms', [5], 1);

        $indexer = new Cms(
            $publisherMock,
            $this->createStub(PageCollectionFactory::class),
            $storeManagerStub,
            $planServiceStub
        );

        $indexer->executeRow(5);
    }

    /**
     * Test executeFull queries active CMS pages and publishes in chunks.
     *
     * @return void
     */
    public function testExecuteFullQueriesCollectionAndPublishes(): void
    {
        $storeStub = $this->createStub(StoreInterface::class);
        $storeStub->method('getId')->willReturn(1);

        $storeManagerStub = $this->createStub(StoreManagerInterface::class);
        $storeManagerStub->method('getStores')->willReturn([$storeStub]);

        $planServiceStub = $this->createStub(PlanService::class);
        $planServiceStub->method('canSyncEntity')->willReturn(true);

        $collectionStub = $this->createStub(PageCollection::class);
        $collectionStub->method('getAllIds')->willReturn(['1', '2']);

        $collectionFactoryStub = $this->createStub(PageCollectionFactory::class);
        $collectionFactoryStub->method('create')->willReturn($collectionStub);

        $publisherMock = $this->createMock(Publisher::class);
        $publisherMock->expects($this->once())
            ->method('publishBatch')
            ->with('cms', [1, 2], 1);

        $indexer = new Cms(
            $publisherMock,
            $collectionFactoryStub,
            $storeManagerStub,
            $planServiceStub
        );

        $indexer->executeFull();
    }

    /**
     * Test execute MviewActionInterface delegates properly.
     *
     * @return void
     */
    public function testExecuteMviewActionDelegatesToExecuteList(): void
    {
        $storeStub = $this->createStub(StoreInterface::class);
        $storeStub->method('getId')->willReturn(1);

        $storeManagerStub = $this->createStub(StoreManagerInterface::class);
        $storeManagerStub->method('getStores')->willReturn([$storeStub]);

        $planServiceStub = $this->createStub(PlanService::class);
        $planServiceStub->method('canSyncEntity')->willReturn(true);

        $publisherMock = $this->createMock(Publisher::class);
        $publisherMock->expects($this->once())
            ->method('publishBatch')
            ->with('cms', [6], 1);

        $indexer = new Cms(
            $publisherMock,
            $this->createStub(PageCollectionFactory::class),
            $storeManagerStub,
            $planServiceStub
        );

        $indexer->execute([6]);
    }
}
