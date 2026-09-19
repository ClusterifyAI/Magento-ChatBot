<?php
/**
 * ClusterifyAI ChatBot CMS page save observer
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
use Magento\Cms\Model\Page;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class CmsSaveObserver
 *
 * Dispatches synchronization messages when CMS pages are saved.
 */
class CmsSaveObserver implements ObserverInterface
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
     * Execute observer on cms_page_save_commit_after.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        /** @var Page|null $page */
        $page = $observer->getEvent()->getObject();
        if (!$page || !$page->getId()) {
            return;
        }

        $pageId = (int) $page->getId();
        $storeIds = (array) $page->getStores();

        if (empty($storeIds) || in_array(0, array_map('intval', $storeIds), true)) {
            $storeIds = array_map(
                static fn ($s) => (int) $s->getId(),
                $this->storeManager->getStores()
            );
        }

        foreach ($storeIds as $storeId) {
            $storeId = (int) $storeId;
            if ($storeId === 0 || !$this->planService->canSyncEntity('cms', $storeId)) {
                continue;
            }

            try {
                $this->publisher->publish('cms', $pageId, $storeId, SyncItem::ACTION_UPSERT);
            } catch (Exception $e) {
                $this->logger->error(sprintf(
                    'Clusterify CmsSaveObserver failed to queue page ID %d for store %d: %s',
                    $pageId,
                    $storeId,
                    $e->getMessage()
                ));
            }
        }
    }
}
