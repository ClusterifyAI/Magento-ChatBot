<?php
/**
 * ClusterifyAI ChatBot general welcome and feature notice block
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Block\Adminhtml\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Class GeneralNotice
 *
 * Renders an informative and welcoming banner at the top of the General Configuration group,
 * highlighting the marketing and sales advantages of the Clusterify.AI Chatbot with quick links.
 */
class GeneralNotice extends Field
{
    public const URL_HOME = 'https://clusterify.ai';
    public const URL_PRICES = 'https://clusterify.ai/prices';
    public const URL_REGISTER = 'https://clusterify.ai/register';

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
     * Render the notice banner HTML content.
     *
     * @param AbstractElement $element Form element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $title = $this->escapeHtml(__('Supercharge Your Store with Clusterify.AI'));
        $description = $this->escapeHtml(__(
            'Integrate an intelligent 24/7 AI shopping assistant directly into your Magento store. Deliver instant customer answers, guide shoppers to the right products, and elevate conversion rates with automated, human-like sales assistance.'
        ));

        $homeUrl = $this->escapeUrl(self::URL_HOME);
        $pricesUrl = $this->escapeUrl(self::URL_PRICES);
        $registerUrl = $this->escapeUrl(self::URL_REGISTER);

        $btnExplore = $this->escapeHtml(__('Explore Features & Power'));
        $btnPrices = $this->escapeHtml(__('View Plans & Pricing'));
        $btnSignUp = $this->escapeHtml(__('Sign Up / Create Account'));

        return sprintf(
            '<div style="background: linear-gradient(135deg, #f8fafc 0%%, #f1f5f9 100%%); border: 1px solid #cbd5e1; border-left: 4px solid #3b82f6; border-radius: 6px; padding: 16px 20px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">' .
                '<div style="margin-bottom: 8px;">' .
                    '<span style="font-weight: 700; font-size: 14px; color: #1e293b; letter-spacing: -0.2px;">%s</span>' .
                '</div>' .
                '<div style="font-size: 12px; line-height: 1.6; color: #475569; margin-bottom: 14px; max-width: 850px;">' .
                    '%s' .
                '</div>' .
                '<div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">' .
                    '<a href="%s" target="_blank" rel="noopener noreferrer" style="display: inline-flex; align-items: center; padding: 6px 12px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 4px; color: #1e293b; font-size: 11px; font-weight: 600; text-decoration: none; box-shadow: 0 1px 2px rgba(0,0,0,0.04);">' .
                        '%s &rarr;' .
                    '</a>' .
                    '<a href="%s" target="_blank" rel="noopener noreferrer" style="display: inline-flex; align-items: center; padding: 6px 12px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 4px; color: #1e293b; font-size: 11px; font-weight: 600; text-decoration: none; box-shadow: 0 1px 2px rgba(0,0,0,0.04);">' .
                        '%s &rarr;' .
                    '</a>' .
                    '<a href="%s" target="_blank" rel="noopener noreferrer" style="display: inline-flex; align-items: center; padding: 6px 14px; background: #2563eb; border: 1px solid #1d4ed8; border-radius: 4px; color: #ffffff; font-size: 11px; font-weight: 600; text-decoration: none; box-shadow: 0 1px 2px rgba(37,99,235,0.2);">' .
                        '%s &rarr;' .
                    '</a>' .
                '</div>' .
            '</div>',
            $title,
            $description,
            $homeUrl,
            $btnExplore,
            $pricesUrl,
            $btnPrices,
            $registerUrl,
            $btnSignUp
        );
    }
}
