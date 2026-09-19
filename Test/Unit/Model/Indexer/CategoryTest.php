<?php
/**
 * ClusterifyAI ChatBot category indexer unit test
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Test\Unit\Model\Indexer;

use ClusterifyAI\ChatBot\Model\Indexer\Category;
use ClusterifyAI\ChatBot\Model\Queue\Publisher;
use ClusterifyAI\ChatBot\Service\PlanService;
use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryCollection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class CategoryTest
 *
 * Tests the category indexer actions.
 */
class CategoryTest extends TestCase
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
            ->with('category', [3, 4], 1);

        $indexer = new Category(
            $publisherMock,
            $this->createStub(CategoryCollectionFactory::class),
            $storeManagerStub,
            $planServiceStub
        );

        $indexer->executeList(['3', 0, 4, 3, -1]);
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
            ->with('category', [7], 1);

        $indexer = new Category(
            $publisherMock,
            $this->createStub(CategoryCollectionFactory::class),
            $storeManagerStub,
            $planServiceStub
        );

        $indexer->executeRow(7);
    }

    /**
     * Test executeFull queries active categories and publishes in chunks.
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

        $collectionStub = $this->createStub(CategoryCollection::class);
        $collectionStub->method('getAllIds')->willReturn(['3', '4']);

        $collectionFactoryStub = $this->createStub(CategoryCollectionFactory::class);
        $collectionFactoryStub->method('create')->willReturn($collectionStub);

        $publisherMock = $this->createMock(Publisher::class);
        $publisherMock->expects($this->once())
            ->method('publishBatch')
            ->with('category', [3, 4], 1);

        $indexer = new Category(
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
            ->with('category', [9], 1);

        $indexer = new Category(
            $publisherMock,
            $this->createStub(CategoryCollectionFactory::class),
            $storeManagerStub,
            $planServiceStub
        );

        $indexer->execute([9]);
    }
}
