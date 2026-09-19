<?php
/**
 * ClusterifyAI ChatBot system configuration divider field
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
 * Class Divider
 *
 * Renders a full-width subtle horizontal divider line in system configuration.
 */
class Divider extends Field
{
    /**
     * Render full-width horizontal rule across all configuration columns.
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element): string
    {
        return '<tr id="row_' . $element->getHtmlId() . '" class="clusterify-config-divider">' .
            '<td colspan="4" style="padding: 16px 0 16px 0; border: none; background: transparent;">' .
            '<hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 0; width: 100%;"/>' .
            '</td></tr>';
    }
}
