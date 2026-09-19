<?php
/**
 * ClusterifyAI ChatBot entity pre-deletion observer
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Observer;

use ClusterifyAI\ChatBot\Model\Config;
use ClusterifyAI\ChatBot\Model\Queue\Publisher;
use ClusterifyAI\ChatBot\Model\Sync\SyncItem;
use ClusterifyAI\ChatBot\Service\PlanService;
use Exception;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Cms\Model\Page;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class EntityDeleteObserver
 *
 * Captures product, category, and CMS page deletions before database removal,
 * resolving their active URLs and dispatching deletion purge messages to RabbitMQ.
 */
class EntityDeleteObserver implements ObserverInterface
{
    /**
     * @param Publisher             $publisher    Queue publisher service
     * @param StoreManagerInterface $storeManager Store manager
     * @param Config                $config       Configuration provider
     * @param PlanService           $planService  Plan verification service
     * @param LoggerInterface       $logger       Logger
     */
    public function __construct(
        private readonly Publisher $publisher,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        private readonly PlanService $planService,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Execute pre-deletion observation.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        try {
            $event = $observer->getEvent();
            $eventName = $event->getName();

            $stores = $this->storeManager->getStores();

            if (str_contains($eventName, 'product')) {
                /** @var Product $product */
                $product = $event->getData('product') ?? $event->getData('object');
                if ($product instanceof Product) {
                    $entityId = (int) $product->getId();
                    foreach ($stores as $store) {
                        $storeId = (int) $store->getId();
                        if ($this->planService->canSyncEntity('product', $storeId)) {
                            $productClone = clone $product;
                            $productClone->setStoreId($storeId);
                            $productClone->unsetData('request_path');
                            $productClone->unsetData('url');
                            $url = '';
                            try {
                                $url = (string) $productClone->getUrlModel()->getUrl($productClone, ['_ignore_category' => true]);
                            } catch (\Throwable) {
                                // Fallback
                            }
                            if ($url === '') {
                                $url = (string) $productClone->getProductUrl(false);
                            }
                            $url = strtok($url, '?');
                            $url = trim(strtok((string) $url, '#'));
                            if ($url !== '') {
                                $this->publisher->publish('product', $entityId, $storeId, SyncItem::ACTION_DELETE, $url);
                            }
                        }
                    }
                }
            } elseif (str_contains($eventName, 'category')) {
                /** @var Category $category */
                $category = $event->getData('category') ?? $event->getData('object');
                if ($category instanceof Category) {
                    $entityId = (int) $category->getId();
                    foreach ($stores as $store) {
                        $storeId = (int) $store->getId();
                        if ($this->planService->canSyncEntity('category', $storeId)) {
                            $categoryClone = clone $category;
                            $categoryClone->setStoreId($storeId);
                            $url = (string) $categoryClone->getUrl();
                            $url = strtok($url, '?');
                            $url = trim(strtok((string) $url, '#'));
                            if ($url !== '') {
                                $this->publisher->publish('category', $entityId, $storeId, SyncItem::ACTION_DELETE, $url);
                            }
                        }
                    }
                }
            } elseif (str_contains($eventName, 'page') || str_contains($eventName, 'cms')) {
                /** @var Page $page */
                $page = $event->getData('page') ?? $event->getData('object');
                if ($page instanceof Page) {
                    $entityId = (int) $page->getId();
                    $identifier = trim((string) $page->getIdentifier());
                    foreach ($stores as $store) {
                        $storeId = (int) $store->getId();
                        if ($this->planService->canSyncEntity('cms', $storeId)) {
                            $baseUrl = rtrim((string) $store->getBaseUrl(), '/');
                            $url = $identifier === 'home'
                                ? $baseUrl . '/'
                                : $baseUrl . '/' . ltrim($identifier, '/');
                            $url = strtok($url, '?');
                            $url = trim(strtok((string) $url, '#'));
                            if ($url !== '') {
                                $this->publisher->publish('cms', $entityId, $storeId, SyncItem::ACTION_DELETE, $url);
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $this->logger->warning('Clusterify EntityDeleteObserver error: ' . $e->getMessage());
        }
    }
}
