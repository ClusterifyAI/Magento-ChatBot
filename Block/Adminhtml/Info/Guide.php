<?php
/**
 * ClusterifyAI ChatBot admin important information guide block
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Block\Adminhtml\Info;

use ClusterifyAI\ChatBot\Model\Config;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\Store\Model\ScopeInterface;

/**
 * Class Guide
 *
 * Block providing navigation URLs and contextual data for the Important Information & Guide page.
 */
class Guide extends Template
{
    public const URL_DASHBOARD = 'https://dashboard.clusterify.ai';
    public const URL_CHATBOT = 'https://dashboard.clusterify.ai/chatbot';
    public const URL_API_KEYS = 'https://dashboard.clusterify.ai/api-key';
    public const URL_BUBBLE_BUILDER = 'https://dashboard.clusterify.ai/bubble-builder';
    public const URL_CHATBOT_BUILDER = 'https://dashboard.clusterify.ai/chatbot-builder';
    public const URL_CHATBOT_BEHAVING = 'https://dashboard.clusterify.ai/chatbot-behaving';
    public const URL_BILLING = 'https://dashboard.clusterify.ai/billing';

    /**
     * @param Context              $context         Block context
     * @param Config               $config          Module configuration service
     * @param array                $data            Additional data
     * @param JsonHelper|null      $jsonHelper      JSON helper
     * @param DirectoryHelper|null $directoryHelper Directory helper
     */
    public function __construct(
        Context $context,
        private readonly Config $config,
        array $data = [],
        ?JsonHelper $jsonHelper = null,
        ?DirectoryHelper $directoryHelper = null
    ) {
        parent::__construct($context, $data, $jsonHelper, $directoryHelper);
    }

    /**
     * Retrieve extension version string.
     *
     * @return string
     */
    public function getExtensionVersion(): string
    {
        return $this->config->getExtensionVersion();
    }

    /**
     * Check if master extension is enabled.
     *
     * @return bool
     */
    public function isExtensionEnabled(): bool
    {
        return $this->config->isEnabled();
    }

    /**
     * Check if URL Knowledge Base synchronization is enabled.
     *
     * @return bool
     */
    public function isSyncEnabled(): bool
    {
        return $this->config->isSyncEnabled();
    }

    /**
     * Check if API credentials are configured.
     *
     * @return bool
     */
    public function hasApiCredentials(): bool
    {
        return $this->config->getPublicKey() !== '' && $this->config->getSecretKey() !== '';
    }

    /**
     * Retrieve System Configuration URL for ChatBot.
     *
     * @return string
     */
    public function getConfigUrl(): string
    {
        return $this->getUrl('adminhtml/system_config/edit', ['section' => 'clusterify_chatbot']);
    }

    /**
     * Retrieve Status Dashboard URL.
     *
     * @return string
     */
    public function getStatusUrl(): string
    {
        return $this->getUrl('clusterify_chatbot/status/index');
    }

    /**
     * Retrieve Index Management URL.
     *
     * @return string
     */
    public function getIndexManagementUrl(): string
    {
        return $this->getUrl('indexer/indexer/list');
    }

    /**
     * Retrieve direct link to Clusterify Cloud Dashboard.
     *
     * @return string
     */
    public function getDashboardUrl(): string
    {
        return self::URL_DASHBOARD;
    }

    /**
     * Retrieve direct link to Chatbot Public UUID page.
     *
     * @return string
     */
    public function getChatbotUrl(): string
    {
        return self::URL_CHATBOT;
    }

    /**
     * Retrieve direct link to API Keys page.
     *
     * @return string
     */
    public function getApiKeysUrl(): string
    {
        return self::URL_API_KEYS;
    }

    /**
     * Retrieve direct link to Bubble Button Builder.
     *
     * @return string
     */
    public function getBubbleBuilderUrl(): string
    {
        return self::URL_BUBBLE_BUILDER;
    }

    /**
     * Retrieve direct link to Chatbot Window Builder.
     *
     * @return string
     */
    public function getChatbotBuilderUrl(): string
    {
        return self::URL_CHATBOT_BUILDER;
    }

    /**
     * Retrieve direct link to Chatbot Interactive Behaviors & Tools.
     *
     * @return string
     */
    public function getChatbotBehavingUrl(): string
    {
        return self::URL_CHATBOT_BEHAVING;
    }

    /**
     * Retrieve direct link to Profile & Billing page.
     *
     * @return string
     */
    public function getBillingUrl(): string
    {
        return self::URL_BILLING;
    }
}
