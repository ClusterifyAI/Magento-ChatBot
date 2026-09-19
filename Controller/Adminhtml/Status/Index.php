<?php
/**
 * ClusterifyAI ChatBot admin status dashboard action
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Controller\Adminhtml\Status;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * Class Index
 *
 * Controller action rendering the Clusterify.AI ChatBot & Assistant monitoring status dashboard.
 */
class Index extends Action implements HttpGetActionInterface
{
    /**
     * Authorization resource
     */
    public const ADMIN_RESOURCE = 'ClusterifyAI_ChatBot::status';

    /**
     * Active menu item ID
     */
    public const ACTIVE_MENU = 'ClusterifyAI_ChatBot::marketing_chatbot_status';

    /**
     * @param Context     $context           Backend action context
     * @param PageFactory $resultPageFactory Result page factory
     */
    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Execute status dashboard page.
     *
     * @return Page
     */
    public function execute(): Page
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu(self::ACTIVE_MENU);
        $resultPage->getConfig()->getTitle()->prepend((string) __('Clusterify.AI ChatBot & Assistant Status'));

        return $resultPage;
    }
}
