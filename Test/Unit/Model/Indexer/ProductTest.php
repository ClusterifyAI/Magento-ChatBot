<?php
/**
 * ClusterifyAI ChatBot product indexer unit test
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Test\Unit\Model\Indexer;

use ClusterifyAI\ChatBot\Model\Indexer\Product;
use ClusterifyAI\ChatBot\Model\Queue\Publisher;
use ClusterifyAI\ChatBot\Service\PlanService;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class ProductTest
 *
 * Tests the product indexer actions.
 */
class ProductTest extends TestCase
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
            ->with('product', [5, 10], 1);

        $indexer = new Product(
            $publisherMock,
            $this->createStub(ProductCollectionFactory::class),
            $storeManagerStub,
            $planServiceStub
        );

        // Includes duplicates, 0, and string representation
        $indexer->executeList(['5', 0, 10, 5, -3]);
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
            ->with('product', [42], 1);

        $indexer = new Product(
            $publisherMock,
            $this->createStub(ProductCollectionFactory::class),
            $storeManagerStub,
            $planServiceStub
        );

        $indexer->executeRow(42);
    }

    /**
     * Test executeFull queries active products and publishes in chunks.
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

        $collectionStub = $this->createStub(ProductCollection::class);
        $collectionStub->method('getAllIds')->willReturn(['1', '2', '3']);

        $collectionFactoryStub = $this->createStub(ProductCollectionFactory::class);
        $collectionFactoryStub->method('create')->willReturn($collectionStub);

        $publisherMock = $this->createMock(Publisher::class);
        $publisherMock->expects($this->once())
            ->method('publishBatch')
            ->with('product', [1, 2, 3], 1);

        $indexer = new Product(
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
            ->with('product', [8], 1);

        $indexer = new Product(
            $publisherMock,
            $this->createStub(ProductCollectionFactory::class),
            $storeManagerStub,
            $planServiceStub
        );

        $indexer->execute([8]);
    }
}
