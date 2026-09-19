<?php
/**
 * ClusterifyAI ChatBot API authorization plan notice block
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
 * Class AuthorizationNotice
 *
 * Renders an informative callout banner at the top of the API Authorization configuration group
 * displaying current subscription plan eligibility and billing links in the matching blue schema.
 */
class AuthorizationNotice extends Field
{
    /**
     * Dashboard portal URLs
     */
    public const DASHBOARD_URL = 'https://dashboard.clusterify.ai';
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
     * Render notice banner HTML content matching the blue schema.
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

        $title = $this->escapeHtml(__('API Integration & Plan Requirements'));

        if ($pubKey === '' || $secKey === '') {
            return sprintf(
                '<div style="background: #f0f9ff; border: 1px solid #bae6fd; border-left: 4px solid #0284c7; border-radius: 4px; padding: 14px 18px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">' .
                    '<div style="font-weight: 600; font-size: 13px; color: #0369a1; margin-bottom: 6px;">%s</div>' .
                    '<div style="font-size: 12px; line-height: 1.5; color: #334155; margin-bottom: 6px;">%s</div>' .
                    '<div style="font-size: 12px; line-height: 1.5; color: #475569;">%s <a href="%s" target="_blank" rel="noopener noreferrer" style="color: #0284c7; font-weight: 600; text-decoration: underline;">%s &rarr;</a></div>' .
                '</div>',
                $title,
                $this->escapeHtml(__('Enter your API Public Key and Secret Key below to authenticate programmatic access and unlock deep URL-Based Assistant Knowledge Base synchronization.')),
                $this->escapeHtml(__('You can retrieve or generate your API keys in the')),
                $this->escapeUrl(self::DASHBOARD_URL . '/api-key'),
                $this->escapeHtml(__('Clusterify.AI Dashboard > API Keys page'))
            );
        }

        $planId = $this->planService->getPlanId($storeId);
        $planName = $this->planService->getPlanName($storeId);
        $isAllowed = $this->planService->isUrlKnowledgeAllowed($storeId);

        if (!$isAllowed || $planId === PlanService::PLAN_ID_STARTER) {
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
                $this->escapeHtml(__('API credentials enable programmatic access and deep URL-Based Assistant Knowledge Base synchronization, which is available on the PROFESSIONAL Plan and above.')),
                $this->escapeHtml(__('On the STARTER Plan, your chatbot operates smoothly via the Public UUID. To unlock automated API synchronization for your catalog, you can upgrade anytime on your')),
                $this->escapeUrl(self::BILLING_URL),
                $this->escapeHtml(__('Clusterify.AI Profile & Billing page'))
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
            $this->escapeHtml(__('Your subscription plan permits deep URL-Based Knowledge Base synchronization. API credentials are valid and ready for automated background synchronization.'))
        );
    }
}
