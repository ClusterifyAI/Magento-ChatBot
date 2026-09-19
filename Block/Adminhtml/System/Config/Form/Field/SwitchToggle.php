<?php
/**
 * ClusterifyAI ChatBot admin toggle switch form field
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
 * Class SwitchToggle
 *
 * Renders a standard Magento 2 toggle switcher UI control for boolean configuration fields,
 * fully compatible with multi-scope (Website and Store View) value inheritance.
 */
class SwitchToggle extends Field
{
    /**
     * Render switch toggle element HTML.
     *
     * @param AbstractElement $element Form element to render
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $name = (string) $element->getName();
        $id = (string) $element->getHtmlId();
        $value = (int) $element->getValue();
        $isChecked = $value === 1 ? 'checked="checked"' : '';
        $isDisabled = $element->getDisabled() ? 'disabled="disabled"' : '';

        $html = sprintf(
            '<div class="admin__actions-switch" data-role="switcher" style="margin-top: 5px;">' .
                '<input type="hidden" name="%s" id="%s_hidden" value="0" %s />' .
                '<input type="checkbox" name="%s" id="%s" class="admin__actions-switch-checkbox" value="1" %s %s />' .
                '<label class="admin__actions-switch-label" for="%s">' .
                    '<span class="admin__actions-switch-text"></span>' .
                '</label>' .
            '</div>',
            $this->escapeHtmlAttr($name),
            $this->escapeHtmlAttr($id),
            $isDisabled,
            $this->escapeHtmlAttr($name),
            $this->escapeHtmlAttr($id),
            $isChecked,
            $isDisabled,
            $this->escapeHtmlAttr($id)
        );

        // Sync hidden input with checkbox when Magento toggles disabled state via 'Use Default' checkbox
        $html .= sprintf(
            '<script type="text/javascript">' .
            'require(["jquery"], function ($) {' .
                'var $cb = $("#%s");' .
                'var $hidden = $("#%s_hidden");' .
                'if ($cb.length && $hidden.length) {' .
                    'var syncDisabled = function () {' .
                        '$hidden.prop("disabled", $cb.prop("disabled"));' .
                    '};' .
                    'var observer = new MutationObserver(syncDisabled);' .
                    'observer.observe($cb[0], { attributes: true, attributeFilter: ["disabled"] });' .
                    '$cb.on("change", function () {' .
                        'if ($cb.is(":checked")) {' .
                            '$hidden.prop("disabled", true);' .
                        '} else {' .
                            '$hidden.prop("disabled", $cb.prop("disabled"));' .
                        '}' .
                    '}).trigger("change");' .
                '}' .
            '});' .
            '</script>',
            $this->escapeJs($id),
            $this->escapeJs($id)
        );

        return $html;
    }
}
