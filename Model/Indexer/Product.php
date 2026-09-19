<?php
/**
 * ClusterifyAI ChatBot product indexer action
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
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Indexer\ActionInterface as IndexerActionInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class Product
 *
 * Indexer responsible for dispatching catalog product change notifications to the RabbitMQ sync pipeline.
 * Strictly verifies the Clusterify subscription plan and exits immediately if plan_id = 1 (Starter).
 */
class Product implements IndexerActionInterface, MviewActionInterface
{
    public const INDEXER_ID = 'clusterify_chatbot_product';

    /**
     * Chunk size for querying and publishing product IDs
     */
    private const BATCH_SIZE = 100;

    /**
     * @param Publisher                $publisher                Queue publisher service
     * @param ProductCollectionFactory $productCollectionFactory Product collection factory
     * @param StoreManagerInterface    $storeManager             Store manager
     * @param PlanService              $planService              Plan verification service
     */
    public function __construct(
        private readonly Publisher $publisher,
        private readonly ProductCollectionFactory $productCollectionFactory,
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
            if (!$this->planService->canSyncEntity('product', $storeId)) {
                continue;
            }

            $collection = $this->productCollectionFactory->create();
            $collection->addStoreFilter($storeId);
            $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
            $collection->addAttributeToFilter('visibility', [
                'in' => [
                    Visibility::VISIBILITY_IN_CATALOG,
                    Visibility::VISIBILITY_IN_SEARCH,
                    Visibility::VISIBILITY_BOTH,
                ],
            ]);

            $ids = array_map('intval', $collection->getAllIds());

            if (!empty($ids)) {
                // Publish in safe chunks of 100
                foreach (array_chunk($ids, self::BATCH_SIZE) as $chunk) {
                    $this->publisher->publishBatch('product', $chunk, $storeId);
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
            if (!$this->planService->canSyncEntity('product', $storeId)) {
                continue;
            }

            foreach (array_chunk($uniqueIds, self::BATCH_SIZE) as $chunk) {
                $this->publisher->publishBatch('product', $chunk, $storeId);
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
