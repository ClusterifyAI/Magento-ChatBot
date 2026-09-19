<?php
/**
 * ClusterifyAI ChatBot sync plan notice block
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Block\Adminhtml\System\Config\Form\Field;

use ClusterifyAI\ChatBot\Model\Config;
use ClusterifyAI\ChatBot\Service\PlanService;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Model\ScopeInterface;

/**
 * Class SyncPlanNotice
 *
 * Renders an informative, positive callout banner at the top of the URL Knowledge Base Synchronization group
 * explaining subscription requirements and providing direct access to the billing portal.
 */
class SyncPlanNotice extends Field
{
    public const BILLING_URL = 'https://dashboard.clusterify.ai/billing';

    /**
     * @param Context     $context     Backend context
     * @param PlanService $planService Plan verification service
     * @param Config      $config      Configuration provider
     * @param array       $data        Additional block data
     */
    public function __construct(
        Context $context,
        private readonly PlanService $planService,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Render the field row spanning all columns.
     *
     * @param AbstractElement $element Form element
     * @return string
     */
    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return sprintf(
            '<tr id="row_%s"><td colspan="3" style="padding: 0 0 15px 0;">%s</td></tr>',
            $element->getHtmlId(),
            $this->_getElementHtml($element)
        );
    }

    /**
     * Render notice banner HTML based on current plan status.
     *
     * @param AbstractElement $element Form element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $storeCode = (string) $this->getRequest()->getParam('store');
        $storeId = $storeCode !== '' ? (int) $this->_storeManager->getStore($storeCode)->getId() : null;

        $pubKey = $this->config->getPublicKey($storeId, ScopeInterface::SCOPE_STORE);
        $secKey = $this->config->getSecretKey($storeId, ScopeInterface::SCOPE_STORE);

        if ($pubKey === '' || $secKey === '') {
            return sprintf(
                '<div style="background: #f8fafc; border: 1px solid #cbd5e1; border-left: 4px solid #64748b; border-radius: 4px; padding: 12px 16px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">' .
                    '<div style="font-weight: 600; font-size: 13px; color: #334155; margin-bottom: 4px;">%s</div>' .
                    '<div style="font-size: 12px; color: #64748b; line-height: 1.5;">%s</div>' .
                '</div>',
                $this->escapeHtml(__('API Credentials Required')),
                $this->escapeHtml(__('Please configure and test your Clusterify API Keys in the Authorization section above to enable URL Knowledge Base synchronization.'))
            );
        }

        $planId = $this->planService->getPlanId($storeId);
        $planName = $this->planService->getPlanName($storeId);
        $isAllowed = $this->planService->isUrlKnowledgeAllowed($storeId);

        $title = $this->escapeHtml(__('Data SYNC & Plan Requirements'));

        if (!$isAllowed || $planId === PlanService::PLAN_ID_STARTER) {
            $bodyIntro = $this->escapeHtml(__(
                'Automated URL-Based Knowledge Base synchronization connects your live catalog directly to Clusterify.AI, and is available on the PROFESSIONAL Plan and above.'
            ));
            $bodyStarterPrefix = $this->escapeHtml(__(
                'On the STARTER Plan, your chatbot operates smoothly and can be fully managed directly in the Clusterify.AI Dashboard. If you would like to unlock automated background synchronization for your CMS, Category, and Product pages, you can easily upgrade anytime in your'
            ));
            $billingLinkText = $this->escapeHtml(__('Clusterify.AI Profile & Billing page'));
            $billingUrl = $this->escapeUrl(self::BILLING_URL);

            return sprintf(
                '<div style="background: #f0f9ff; border: 1px solid #bae6fd; border-left: 4px solid #0284c7; border-radius: 4px; padding: 14px 18px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">' .
                    '<div style="font-weight: 600; font-size: 13px; color: #0369a1; margin-bottom: 6px;">' .
                        '%s <span style="font-weight: normal; font-size: 12px; color: #0284c7;">(%s)</span>' .
                    '</div>' .
                    '<div style="font-size: 12px; line-height: 1.5; color: #334155; margin-bottom: 6px;">' .
                        '%s' .
                    '</div>' .
                    '<div style="font-size: 12px; line-height: 1.5; color: #475569;">' .
                        '%s <a href="%s" target="_blank" rel="noopener noreferrer" style="color: #0284c7; font-weight: 600; text-decoration: underline;">%s &rarr;</a>' .
                    '</div>' .
                '</div>',
                $title,
                $this->escapeHtml((string) __('Current Plan: %1', $planName)),
                $bodyIntro,
                $bodyStarterPrefix,
                $billingUrl,
                $billingLinkText
            );
        }

        return sprintf(
            '<div style="background: #f0f9ff; border: 1px solid #bae6fd; border-left: 4px solid #0284c7; border-radius: 4px; padding: 14px 18px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">' .
                '<div style="font-weight: 600; font-size: 13px; color: #0369a1; margin-bottom: 6px;">' .
                    '%s <span style="font-weight: normal; font-size: 12px; color: #15803d; font-weight: 600;">(%s)</span>' .
                '</div>' .
                '<div style="font-size: 12px; line-height: 1.5; color: #334155;">' .
                    '%s' .
                '</div>' .
            '</div>',
            $title,
            $this->escapeHtml((string) __('Active Subscription: %1', $planName)),
            $this->escapeHtml(__('Your subscription plan permits deep URL-Based Knowledge Base synchronization. Changes to CMS, Category, and Product pages will sync automatically in the background via RabbitMQ.'))
        );
    }
}
