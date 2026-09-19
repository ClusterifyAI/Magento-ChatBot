<?php
/**
 * ClusterifyAI ChatBot sync switch toggle field with plan restriction
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Block\Adminhtml\System\Config\Form\Field;

use ClusterifyAI\ChatBot\Service\PlanService;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Class SyncSwitchToggle
 *
 * Toggle switch field that dynamically verifies the Clusterify plan ID. If the plan is
 * Starter (plan_id = 1), forces the switch to Disabled ('0') and locks the control.
 */
class SyncSwitchToggle extends SwitchToggle
{
    /**
     * @param Context     $context     Backend context
     * @param PlanService $planService Plan verification service
     * @param array       $data        Additional block data
     */
    public function __construct(
        Context $context,
        private readonly PlanService $planService,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Render switch toggle element HTML, enforcing plan restrictions.
     *
     * @param AbstractElement $element Form element to render
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $storeCode = (string) $this->getRequest()->getParam('store');
        $storeId = $storeCode !== '' ? (int) $this->_storeManager->getStore($storeCode)->getId() : null;

        $isAllowed = $this->planService->isUrlKnowledgeAllowed($storeId);

        if (!$isAllowed) {
            $element->setValue('0');
            $element->setDisabled(true);

            $parentHtml = parent::_getElementHtml($element);

            return sprintf(
                '<div style="display: flex; align-items: center; flex-wrap: wrap;">' .
                    '%s' .
                    '<span style="font-size: 11px; color: #0284c7; margin-left: 10px; font-weight: 500;">' .
                        '%s' .
                    '</span>' .
                '</div>',
                $parentHtml,
                $this->escapeHtml(__('(Requires Professional Plan)'))
            );
        }

        return parent::_getElementHtml($element);
    }
}
