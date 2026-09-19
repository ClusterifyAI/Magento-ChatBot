<?php
/**
 * ClusterifyAI ChatBot page visibility matrix form field block
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Block\Adminhtml\System\Config\Form\Field;

use ClusterifyAI\ChatBot\Service\PageVisibility as PageVisibilityService;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Model\ScopeInterface;

/**
 * Class PageVisibility
 *
 * Form field block rendering dynamically discovered page types grouped visually,
 * with individual switch controls to determine where the ChatBot is allowed to appear.
 */
class PageVisibility extends Field
{
    /**
     * @param Context               $context               Backend block context
     * @param PageVisibilityService $pageVisibilityService Dynamic page types service
     * @param array                 $data                  Additional block data
     */
    public function __construct(
        Context $context,
        private readonly PageVisibilityService $pageVisibilityService,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Render the field row spanning all 3 columns.
     *
     * @param AbstractElement $element Form element
     * @return string
     */
    public function render(AbstractElement $element): string
    {
        return sprintf(
            '<tr id="row_%s">' .
                '<td colspan="3" style="padding: 5px 0 20px 0;">%s</td>' .
            '</tr>',
            $this->escapeHtmlAttr($element->getHtmlId()),
            $this->_getElementHtml($element)
        );
    }

    /**
     * Render the page visibility matrix HTML.
     *
     * @param AbstractElement $element Form element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $storeCode = (string) $this->getRequest()->getParam('store');
        $websiteCode = (string) $this->getRequest()->getParam('website');

        $scopeCode = null;
        $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;

        if ($storeCode !== '') {
            $scopeCode = $storeCode;
            $scopeType = ScopeInterface::SCOPE_STORE;
        } elseif ($websiteCode !== '') {
            $scopeCode = $websiteCode;
            $scopeType = ScopeInterface::SCOPE_WEBSITE;
        }

        $configured = $this->pageVisibilityService->getConfiguredPages($scopeCode, $scopeType);
        $categorized = $this->pageVisibilityService->getCategorizedPageTypes();

        $html = '<div class="clusterify-page-visibility-matrix" style="max-width: 950px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">';

        // Filter and bulk action toolbar
        $html .= '<div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 16px; padding: 12px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">';
        $html .= '<div style="flex: 1; min-width: 250px;">';
        $html .= '<input type="text" id="clusterify_page_filter" placeholder="' . $this->escapeHtmlAttr(__('Search page types by name or handle...')) . '" style="width: 100%; max-width: 380px; padding: 6px 12px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 12px;" />';
        $html .= '</div>';
        $html .= '<div style="display: flex; gap: 8px;">';
        $html .= '<button type="button" id="clusterify_btn_enable_all" class="action-secondary" style="font-size: 11px; padding: 4px 10px;">' . $this->escapeHtml(__('Enable All')) . '</button>';
        $html .= '<button type="button" id="clusterify_btn_disable_all" class="action-secondary" style="font-size: 11px; padding: 4px 10px;">' . $this->escapeHtml(__('Disable All')) . '</button>';
        $html .= '<button type="button" id="clusterify_btn_reset_defaults" class="action-secondary" style="font-size: 11px; padding: 4px 10px;">' . $this->escapeHtml(__('Reset to Defaults')) . '</button>';
        $html .= '</div>';
        $html .= '</div>';

        // Render each category
        foreach ($categorized as $catKey => $category) {
            $catTitle = $category['title'];
            $items = $category['items'];
            $isCheckout = $category['default_disabled'];

            $html .= '<div class="clusterify-page-category" data-cat="' . $this->escapeHtmlAttr($catKey) . '" style="margin-bottom: 24px;">';

            // Category Subtitle Header with Divider Line
            $html .= '<div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">';
            $html .= '<span style="font-weight: 700; font-size: 13px; color: #1e293b; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap;">';
            $html .= $this->escapeHtml($catTitle);
            if ($isCheckout) {
                $html .= ' <span style="font-size: 11px; font-weight: normal; color: #94a3b8; text-transform: none;">(' . $this->escapeHtml(__('Default: Disabled')) . ')</span>';
            }
            $html .= '</span>';
            $html .= '<div style="flex: 1; height: 1px; background: #e2e8f0;"></div>';
            $html .= '<span class="clusterify-cat-count" style="font-size: 11px; color: #94a3b8; font-weight: 600;">' . count($items) . '</span>';
            $html .= '</div>';

            if (empty($items)) {
                $html .= '<div class="clusterify-page-empty" style="padding: 14px 18px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 6px; color: #64748b; font-size: 12px; font-style: italic;">';
                $html .= $this->escapeHtml(__('There is no custom page type.'));
                $html .= '</div>';
            } else {
                $html .= '<div class="clusterify-page-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 8px;">';

                foreach ($items as $code => $label) {
                    $defaultState = $this->pageVisibilityService->getDefaultStatus($code);
                    $isEnabled = array_key_exists($code, $configured)
                        ? (bool) (int) $configured[$code]
                        : $defaultState;

                    $isCheckedAttr = $isEnabled ? 'checked="checked"' : '';
                    $inputName = sprintf('groups[page_visibility][fields][allowed_pages][value][%s]', $code);
                    $inputId = 'clusterify_page_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $code);

                    $html .= '<div class="clusterify-page-item" data-code="' . $this->escapeHtmlAttr($code) . '" data-label="' . $this->escapeHtmlAttr(strtolower($label)) . '" style="padding: 10px 14px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; display: flex; align-items: center; justify-content: space-between; gap: 12px; transition: border-color 0.15s ease;">';
                    $html .= '<div style="flex: 1; min-width: 0;">';
                    $html .= '<div style="font-weight: 600; font-size: 12px; color: #1e293b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="' . $this->escapeHtmlAttr($label) . '">';
                    $html .= $this->escapeHtml($label);
                    $html .= '</div>';
                    $html .= '<div style="font-size: 10px; font-family: monospace; color: #64748b; margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="' . $this->escapeHtmlAttr($code) . '">';
                    $html .= $this->escapeHtml($code);
                    $html .= '</div>';
                    $html .= '</div>';

                    // Toggle Switch on Right
                    $html .= '<div class="admin__actions-switch" data-role="switcher" style="flex-shrink: 0;">';
                    $html .= '<input type="hidden" name="' . $this->escapeHtmlAttr($inputName) . '" id="' . $this->escapeHtmlAttr($inputId) . '_hidden" value="0" />';
                    $html .= '<input type="checkbox" class="admin__actions-switch-checkbox clusterify-page-toggle" data-default="' . ($defaultState ? '1' : '0') . '" name="' . $this->escapeHtmlAttr($inputName) . '" id="' . $this->escapeHtmlAttr($inputId) . '" value="1" ' . $isCheckedAttr . ' />';
                    $html .= '<label class="admin__actions-switch-label" for="' . $this->escapeHtmlAttr($inputId) . '">';
                    $html .= '<span class="admin__actions-switch-text"></span>';
                    $html .= '</label>';
                    $html .= '</div>';

                    $html .= '</div>';
                }

                $html .= '</div>';
            }

            $html .= '</div>';
        }

        $html .= '</div>';

        // Client-side interactions: real-time filtering, bulk actions, and hidden input syncing
        $html .= '<script type="text/javascript">';
        $html .= 'require(["jquery"], function ($) {';
        $html .= '  "use strict";';

        // Real-time filter
        $html .= '  $("#clusterify_page_filter").on("input", function () {';
        $html .= '    var term = $.trim($(this).val()).toLowerCase();';
        $html .= '    $(".clusterify-page-category").each(function () {';
        $html .= '      var $cat = $(this);';
        $html .= '      var $items = $cat.find(".clusterify-page-item");';
        $html .= '      if (!term) {';
        $html .= '        $items.show();';
        $html .= '        $cat.show();';
        $html .= '        return;';
        $html .= '      }';
        $html .= '      var visibleCount = 0;';
        $html .= '      $items.each(function () {';
        $html .= '        var code = $(this).attr("data-code").toLowerCase();';
        $html .= '        var label = $(this).attr("data-label").toLowerCase();';
        $html .= '        if (code.indexOf(term) > -1 || label.indexOf(term) > -1) {';
        $html .= '          $(this).show();';
        $html .= '          visibleCount++;';
        $html .= '        } else {';
        $html .= '          $(this).hide();';
        $html .= '        }';
        $html .= '      });';
        $html .= '      if (visibleCount > 0) { $cat.show(); } else { $cat.hide(); }';
        $html .= '    });';
        $html .= '  });';

        // Enable All
        $html .= '  $("#clusterify_btn_enable_all").on("click", function (e) {';
        $html .= '    e.preventDefault();';
        $html .= '    $(".clusterify-page-toggle").prop("checked", true).trigger("change");';
        $html .= '  });';

        // Disable All
        $html .= '  $("#clusterify_btn_disable_all").on("click", function (e) {';
        $html .= '    e.preventDefault();';
        $html .= '    $(".clusterify-page-toggle").prop("checked", false).trigger("change");';
        $html .= '  });';

        // Reset to Defaults (Checkout OFF, others ON)
        $html .= '  $("#clusterify_btn_reset_defaults").on("click", function (e) {';
        $html .= '    e.preventDefault();';
        $html .= '    $(".clusterify-page-toggle").each(function () {';
        $html .= '      var isDef = $(this).attr("data-default") === "1";';
        $html .= '      $(this).prop("checked", isDef).trigger("change");';
        $html .= '    });';
        $html .= '  });';

        // Sync hidden input with checkbox state so that unchecked transmits '0' and checked transmits '1'
        $html .= '  $(".clusterify-page-toggle").on("change", function () {';
        $html .= '    var id = $(this).attr("id");';
        $html .= '    var $hidden = $("#" + id + "_hidden");';
        $html .= '    if ($(this).is(":checked")) {';
        $html .= '      $hidden.prop("disabled", true);';
        $html .= '    } else {';
        $html .= '      $hidden.prop("disabled", false);';
        $html .= '    }';
        $html .= '  }).trigger("change");';

        $html .= '});';
        $html .= '</script>';

        return $html;
    }
}
