<?php
/**
 * ClusterifyAI ChatBot admin test connection button block
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
 * Class TestConnection
 *
 * Form field block that renders a "Test Connection" button calling the Clusterify API ping endpoint,
 * scoped to the active website or store view.
 */
class TestConnection extends Field
{
    /**
     * Path to template
     */
    protected $_template = 'ClusterifyAI_ChatBot::system/config/test_connection.phtml';

    /**
     * Remove scope label and inherit checkbox from button.
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Return element HTML from template.
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        return $this->_toHtml();
    }

    /**
     * Retrieve URL for AJAX test connection endpoint with active scope parameters.
     *
     * @return string
     */
    public function getAjaxUrl(): string
    {
        $params = [];
        $store = (string) $this->getRequest()->getParam('store');
        $website = (string) $this->getRequest()->getParam('website');

        if ($store !== '') {
            $params['store'] = $store;
        } elseif ($website !== '') {
            $params['website'] = $website;
        }

        return $this->getUrl('clusterify_chatbot/system_config/testConnection', $params);
    }

    /**
     * Generate HTML for the test connection button.
     *
     * @return string
     */
    public function getButtonHtml(): string
    {
        $button = $this->getLayout()->createBlock(
            \Magento\Backend\Block\Widget\Button::class
        )->setData([
            'id' => 'clusterify_test_connection_btn',
            'label' => __('Test API Connection'),
            'class' => 'action-secondary'
        ]);

        return $button->toHtml();
    }
}
