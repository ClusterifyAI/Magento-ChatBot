<?php
/**
 * ClusterifyAI ChatBot product mass attribute action plugin
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Plugin\Catalog;

use ClusterifyAI\ChatBot\Model\Queue\Publisher;
use ClusterifyAI\ChatBot\Service\PlanService;
use Exception;
use Magento\Catalog\Model\Product\Action as ProductAction;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class ProductActionPlugin
 *
 * Intercepts mass attribute updates on products and dispatches sync queue messages.
 */
class ProductActionPlugin
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
     * After mass attribute update.
     *
     * @param ProductAction       $subject
     * @param ProductAction       $result
     * @param array               $productIds
     * @param array               $attrData
     * @param int|string|null     $storeId
     * @return ProductAction
     */
    public function afterUpdateAttributes(
        ProductAction $subject,
        ProductAction $result,
        array $productIds,
        array $attrData,
        int|string|null $storeId = 0
    ): ProductAction {
        if (empty($productIds)) {
            return $result;
        }

        $numericStoreId = (int) $storeId;
        $uniqueIds = array_values(array_filter(
            array_unique(array_map('intval', $productIds)),
            static fn (int $id): bool => $id > 0
        ));
        if (empty($uniqueIds)) {
            return $result;
        }

        try {
            $targetStores = $numericStoreId > 0
                ? [$this->storeManager->getStore($numericStoreId)]
                : $this->storeManager->getStores();
        } catch (NoSuchEntityException $e) {
            $this->logger->warning(sprintf(
                'Clusterify ProductActionPlugin: Invalid store ID %d during mass attribute update: %s',
                $numericStoreId,
                $e->getMessage()
            ));
            return $result;
        }

        foreach ($targetStores as $store) {
            $targetStoreId = (int) $store->getId();
            if ($targetStoreId === 0 || !$this->planService->canSyncEntity('product', $targetStoreId)) {
                continue;
            }

            try {
                $this->publisher->publishBatch('product', $uniqueIds, $targetStoreId);
            } catch (Exception $e) {
                $this->logger->error(sprintf(
                    'Clusterify ProductActionPlugin failed to queue %d products for store %d: %s',
                    count($uniqueIds),
                    $targetStoreId,
                    $e->getMessage()
                ));
            }
        }

        return $result;
    }
}
