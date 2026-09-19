<?php
/**
 * ClusterifyAI ChatBot storefront widget snippet block
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Block;

use ClusterifyAI\ChatBot\Model\Config;
use ClusterifyAI\ChatBot\Service\PageVisibility as PageVisibilityService;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\ScopeInterface;

/**
 * Class ChatbotSnippet
 *
 * Renders the embeddable Clusterify ChatBot script snippet on storefront pages,
 * correctly scoped to the current store view and filtered by page visibility rules.
 */
class ChatbotSnippet extends Template
{
    /**
     * @param Context               $context               Template block context
     * @param Config                $config                Clusterify configuration provider
     * @param PageVisibilityService $pageVisibilityService Dynamic page types visibility service
     * @param array                 $data                  Additional block data
     */
    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly PageVisibilityService $pageVisibilityService,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Retrieve the current store view ID.
     *
     * @return int
     */
    public function getCurrentStoreId(): int
    {
        return (int) $this->_storeManager->getStore()->getId();
    }

    /**
     * Determine whether the chatbot widget can be displayed on the current store view and page type.
     *
     * @return bool
     */
    public function canShowChatbot(): bool
    {
        $storeId = $this->getCurrentStoreId();

        if (!$this->config->isEnabled($storeId, ScopeInterface::SCOPE_STORE)
            || !$this->config->isShowOnStorefront($storeId, ScopeInterface::SCOPE_STORE)
            || $this->config->getPublicUuid($storeId, ScopeInterface::SCOPE_STORE) === ''
        ) {
            return false;
        }

        $fullActionName = (string) $this->getRequest()->getFullActionName();
        $handles = $this->getLayout()->getUpdate()->getHandles();

        return $this->pageVisibilityService->isPageAllowed(
            $fullActionName,
            $storeId,
            ScopeInterface::SCOPE_STORE,
            $handles
        );
    }

    /**
     * Retrieve the configured Chatbot Public UUID for the current store view.
     *
     * @return string
     */
    public function getPublicUuid(): string
    {
        $storeId = $this->getCurrentStoreId();
        return $this->config->getPublicUuid($storeId, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Retrieve the bundle script URL.
     *
     * @return string
     */
    public function getBundleScriptUrl(): string
    {
        return $this->config->getBundleScriptUrl();
    }

    /**
     * Render block HTML only when chatbot is permitted to display on this store view and page type.
     *
     * @return string
     */
    protected function _toHtml(): string
    {
        if (!$this->canShowChatbot()) {
            return '';
        }

        return parent::_toHtml();
    }
}
