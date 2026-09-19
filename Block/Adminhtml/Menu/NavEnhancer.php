<?php
/**
 * ClusterifyAI ChatBot admin navigation menu enhancer block
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Block\Adminhtml\Menu;

use ClusterifyAI\ChatBot\Model\Config;
use ClusterifyAI\ChatBot\Service\PlanService;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\Json\Helper\Data as JsonHelper;

/**
 * Class NavEnhancer
 *
 * Injects custom SVG icon styling for the top-level admin navigation sidebar item
 * and injects the live subscription plan status banner at the top of the flyout menu panel.
 */
class NavEnhancer extends Template
{
    public const BILLING_URL = 'https://dashboard.clusterify.ai/billing';

    /**
     * Path to template file
     */
    protected $_template = 'ClusterifyAI_ChatBot::menu/nav_enhancer.phtml';

    /**
     * @param Context              $context         Backend template context
     * @param PlanService          $planService     Plan verification service
     * @param Config               $config          Configuration provider
     * @param array                $data            Additional block data
     * @param JsonHelper|null      $jsonHelper      JSON helper
     * @param DirectoryHelper|null $directoryHelper Directory helper
     */
    public function __construct(
        Context $context,
        private readonly PlanService $planService,
        private readonly Config $config,
        array $data = [],
        ?JsonHelper $jsonHelper = null,
        ?DirectoryHelper $directoryHelper = null
    ) {
        parent::__construct($context, $data, $jsonHelper, $directoryHelper);
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
     * Check if current plan permits URL Knowledge Base synchronization.
     *
     * @return bool
     */
    public function isUrlKnowledgeAllowed(): bool
    {
        return $this->planService->isUrlKnowledgeAllowed();
    }

    /**
     * Retrieve current human-readable plan name.
     *
     * @return string
     */
    public function getPlanName(): string
    {
        return $this->planService->getPlanName();
    }

    /**
     * Retrieve current plan ID.
     *
     * @return int
     */
    public function getPlanId(): int
    {
        return $this->planService->getPlanId();
    }

    /**
     * Retrieve Clusterify Billing upgrade URL.
     *
     * @return string
     */
    public function getBillingUrl(): string
    {
        return self::BILLING_URL;
    }

    /**
     * Retrieve the SVG data URI for reliable inline mask rendering.
     *
     * @return string
     */
    public function getSvgDataUri(): string
    {
        $path = BP . '/app/code/ClusterifyAI/ChatBot/view/adminhtml/web/images/chatbot-icon.svg';
        if (file_exists($path)) {
            $svg = trim((string) file_get_contents($path));
            $clean = preg_replace('/\s+/', ' ', $svg) ?? $svg;
            return 'data:image/svg+xml;utf8,' . rawurlencode($clean);
        }

        return '';
    }
}
