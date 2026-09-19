<?php
/**
 * ClusterifyAI ChatBot category save observer
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Observer;

use ClusterifyAI\ChatBot\Model\Queue\Publisher;
use ClusterifyAI\ChatBot\Model\Sync\SyncItem;
use ClusterifyAI\ChatBot\Service\PlanService;
use Exception;
use Magento\Catalog\Model\Category;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class CategorySaveObserver
 *
 * Dispatches synchronization messages when categories or their attributes are saved,
 * ensuring complete changelog capture across both Open Source and Adobe Commerce Staging environments.
 */
class CategorySaveObserver implements ObserverInterface
{
    /**
     * @param Publisher             $publisher    Message queue publisher
     * @param PlanService           $planService  Plan verification service
     * @param StoreManagerInterface $storeManager Store manager
     * @param LoggerInterface       $logger       System logger
     */
    public function __construct(
        private readonly Publisher $publisher,
        private readonly PlanService $planService,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Execute observer on category save commit after.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        /** @var Category|null $category */
        $category = $observer->getEvent()->getCategory();
        if (!$category || !$category->getId()) {
            return;
        }

        // Exclude root anchor categories without storefront presence
        if ((int) $category->getLevel() <= 1) {
            return;
        }

        $categoryId = (int) $category->getId();
        $rawStoreIds = array_map('intval', (array) $category->getStoreIds());
        $storeIds = array_values(array_filter($rawStoreIds, static fn (int $id): bool => $id > 0));

        // If category is global or only has root store 0, notify all active stores
        if (empty($storeIds)) {
            $storeIds = array_map(
                static fn ($s) => (int) $s->getId(),
                $this->storeManager->getStores()
            );
        }

        foreach ($storeIds as $storeId) {
            $storeId = (int) $storeId;
            if ($storeId === 0 || !$this->planService->canSyncEntity('category', $storeId)) {
                continue;
            }

            try {
                $this->publisher->publish('category', $categoryId, $storeId, SyncItem::ACTION_UPSERT);
            } catch (Exception $e) {
                $this->logger->error(sprintf(
                    'Clusterify CategorySaveObserver failed to queue category ID %d for store %d: %s',
                    $categoryId,
                    $storeId,
                    $e->getMessage()
                ));
            }
        }
    }
}
