<?php
/**
 * ClusterifyAI ChatBot admin configuration save observer
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Observer\Adminhtml;

use ClusterifyAI\ChatBot\Service\PlanService;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Class ConfigChangeObserver
 *
 * Invalidates cached subscription plan metadata, quota statistics, and storefront block/page caches
 * whenever an administrator saves the Clusterify.AI ChatBot configuration section.
 */
class ConfigChangeObserver implements ObserverInterface
{
    /**
     * @param PlanService       $planService   Plan verification and cache manager
     * @param TypeListInterface $cacheTypeList Magento cache type manager
     */
    public function __construct(
        private readonly PlanService $planService,
        private readonly TypeListInterface $cacheTypeList
    ) {}

    /**
     * Clear cached Clusterify plan/quota entries and invalidate storefront block/page caches.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $this->planService->clearPlanCache();
        $this->cacheTypeList->cleanType('block_html');
        $this->cacheTypeList->cleanType('full_page');
    }
}

