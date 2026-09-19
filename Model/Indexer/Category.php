<?php
/**
 * ClusterifyAI ChatBot category indexer action
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Model\Indexer;

use ClusterifyAI\ChatBot\Model\Queue\Publisher;
use ClusterifyAI\ChatBot\Service\PlanService;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\Indexer\ActionInterface as IndexerActionInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class Category
 *
 * Indexer responsible for dispatching category hierarchy change notifications to the RabbitMQ sync pipeline.
 * Strictly verifies the Clusterify subscription plan and exits immediately if plan_id = 1 (Starter).
 */
class Category implements IndexerActionInterface, MviewActionInterface
{
    public const INDEXER_ID = 'clusterify_chatbot_category';

    /**
     * Chunk size for querying and publishing category IDs
     */
    private const BATCH_SIZE = 100;

    /**
     * @param Publisher                 $publisher                 Queue publisher service
     * @param CategoryCollectionFactory $categoryCollectionFactory Category collection factory
     * @param StoreManagerInterface     $storeManager              Store manager
     * @param PlanService               $planService               Plan verification service
     */
    public function __construct(
        private readonly Publisher $publisher,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly PlanService $planService
    ) {}

    /**
     * Execute full reindexation.
     *
     * @return void
     */
    public function executeFull(): void
    {
        $stores = $this->storeManager->getStores();

        foreach ($stores as $store) {
            $storeId = (int) $store->getId();

            // Unified check: enabled switches + credentials + plan verification
            if (!$this->planService->canSyncEntity('category', $storeId)) {
                continue;
            }

            $collection = $this->categoryCollectionFactory->create();
            $collection->setStoreId($storeId);
            $collection->addFieldToFilter('is_active', 1);
            $collection->addFieldToFilter('level', ['gt' => 1]); // Exclude root categories
            $ids = array_map('intval', $collection->getAllIds());

            if (!empty($ids)) {
                foreach (array_chunk($ids, self::BATCH_SIZE) as $chunk) {
                    $this->publisher->publishBatch('category', $chunk, $storeId);
                }
            }
        }
    }

    /**
     * Execute partial reindexation by IDs.
     *
     * @param list<int> $ids
     * @return void
     */
    public function executeList(array $ids): void
    {
        $uniqueIds = array_values(array_filter(
            array_unique(array_map('intval', $ids)),
            static fn (int $id): bool => $id > 0
        ));
        if (empty($uniqueIds)) {
            return;
        }

        $stores = $this->storeManager->getStores();
        foreach ($stores as $store) {
            $storeId = (int) $store->getId();

            // Unified check: enabled switches + credentials + plan verification
            if (!$this->planService->canSyncEntity('category', $storeId)) {
                continue;
            }

            foreach (array_chunk($uniqueIds, self::BATCH_SIZE) as $chunk) {
                $this->publisher->publishBatch('category', $chunk, $storeId);
            }
        }
    }

    /**
     * Execute single entity reindexation.
     *
     * @param int|string $id
     * @return void
     */
    public function executeRow($id): void
    {
        $this->executeList([(int) $id]);
    }

    /**
     * @inheritdoc
     */
    public function execute($ids): void
    {
        $this->executeList(is_array($ids) ? $ids : [(int) $ids]);
    }
}
