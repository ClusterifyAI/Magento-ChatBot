<?php
/**
 * ClusterifyAI ChatBot external API keys redirect action
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Controller\Adminhtml\External;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;

/**
 * Class ApiKeys
 *
 * Redirects admin user to the external Clusterify.AI API Keys management page.
 */
class ApiKeys extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'ClusterifyAI_ChatBot::status';
    public const TARGET_URL = 'https://dashboard.clusterify.ai/api-key';

    /**
     * Execute redirect.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        return $resultRedirect->setUrl(self::TARGET_URL);
    }
}
