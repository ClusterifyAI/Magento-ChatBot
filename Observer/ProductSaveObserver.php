<?php
/**
 * ClusterifyAI ChatBot product save observer
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
use Magento\Catalog\Model\Product;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class ProductSaveObserver
 *
 * Dispatches synchronization messages when products or their attributes are saved,
 * ensuring complete changelog capture across both Open Source and Adobe Commerce Staging environments.
 */
class ProductSaveObserver implements ObserverInterface
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
     * Execute observer on product save commit after.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        /** @var Product|null $product */
        $product = $observer->getEvent()->getProduct();
        if (!$product || !$product->getId()) {
            return;
        }

        $productId = (int) $product->getId();
        $rawStoreIds = array_map('intval', (array) $product->getStoreIds());
        $storeIds = array_values(array_filter($rawStoreIds, static fn (int $id): bool => $id > 0));

        // If product is global (no specific store assigned), notify all active stores
        if (empty($storeIds)) {
            $storeIds = array_map(
                static fn ($s) => (int) $s->getId(),
                $this->storeManager->getStores()
            );
        }

        foreach ($storeIds as $storeId) {
            $storeId = (int) $storeId;
            if ($storeId === 0 || !$this->planService->canSyncEntity('product', $storeId)) {
                continue;
            }

            try {
                $this->publisher->publish('product', $productId, $storeId, SyncItem::ACTION_UPSERT);
            } catch (Exception $e) {
                $this->logger->error(sprintf(
                    'Clusterify ProductSaveObserver failed to queue product ID %d for store %d: %s',
                    $productId,
                    $storeId,
                    $e->getMessage()
                ));
            }
        }
    }
}
