<?php
/**
 * ClusterifyAI ChatBot code snippet preview block
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
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Model\ScopeInterface;

/**
 * Class SnippetPreview
 *
 * Renders an informative, read-only preview of the storefront embed script snippet
 * dynamically populated with the merchant's saved Chatbot Public UUID for the active scope.
 */
class SnippetPreview extends Field
{
    /**
     * @param Context $context Backend block context
     * @param Config  $config  Module configuration service
     * @param array   $data    Additional block data
     */
    public function __construct(
        Context $context,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Remove inherit checkbox from read-only snippet preview.
     *
     * @param AbstractElement $element Form element
     * @return string
     */
    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Render the snippet preview HTML container.
     *
     * @param AbstractElement $element Form element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $storeCode = (string) $this->getRequest()->getParam('store');
        $websiteCode = (string) $this->getRequest()->getParam('website');

        if ($storeCode !== '') {
            $uuid = $this->config->getPublicUuid($storeCode, ScopeInterface::SCOPE_STORE);
        } elseif ($websiteCode !== '') {
            $uuid = $this->config->getPublicUuid($websiteCode, ScopeInterface::SCOPE_WEBSITE);
        } else {
            $uuid = $this->config->getPublicUuid(null, \Magento\Framework\App\Config\ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
        }

        $snippet = $this->config->generateScriptSnippet($uuid !== '' ? $uuid : null);

        $html = '<div class="clusterify-snippet-container" style="background:#1e293b; color:#f8fafc; border-radius:6px; padding:14px; font-family:monospace; font-size:12px; position:relative; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';

        // Header / Informative title
        $html .= '<div style="color:#38bdf8; font-weight:bold; margin-bottom:4px; font-size:11px; text-transform:uppercase; letter-spacing:0.5px;">';
        $html .= $this->escapeHtml(__('Storefront Embed Code (Information / Automatic Injection)'));
        $html .= '</div>';

        $html .= '<div style="color:#94a3b8; font-size:11px; margin-bottom:10px; font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif; line-height:1.4;">';
        $html .= $this->escapeHtml(
            __('This is an informative read-only preview. This code is automatically injected into your storefront before the closing </body> tag when "Show Chatbot on Storefront" is enabled. Manual copying is not required.')
        );
        $html .= '</div>';

        // Code block
        $html .= '<pre style="margin:0; white-space:pre-wrap; word-break:break-all; color:#e2e8f0; background:#0f172a; border:1px solid #334155; border-radius:4px; padding:10px; font-size:11px; line-height:1.45;">';
        $html .= $this->escapeHtml($snippet);
        $html .= '</pre>';

        // Public UUID Security Notice
        $html .= '<div style="margin-top:10px; padding:8px 12px; background:rgba(56,189,248,0.08); border-left:3px solid #38bdf8; border-radius:4px; font-size:11px; color:#cbd5e1; font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif; line-height:1.45;">';
        $html .= '<strong>' . $this->escapeHtml(__('Public UUID Security:')) . '</strong> ';
        $html .= $this->escapeHtml(
            __("Your ChatBot's Public UUID is designed to be visible in client-side HTML. It uniquely identifies your widget appearance and allowed domain. It never exposes your AI API Secret Keys or private database information.")
        );
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }
}
