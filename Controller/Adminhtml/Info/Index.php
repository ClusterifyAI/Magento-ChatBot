<?php
/**
 * ClusterifyAI ChatBot admin important information & guide controller
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Controller\Adminhtml\Info;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * Class Index
 *
 * Displays the comprehensive merchant guide and important information documentation page.
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'ClusterifyAI_ChatBot::status';

    /**
     * @param Context     $context     Action context
     * @param PageFactory $resultPageFactory Page factory
     */
    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Execute information page action.
     *
     * @return Page
     */
    public function execute(): Page
    {
        /** @var Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('ClusterifyAI_ChatBot::clusterify_root');
        $resultPage->getConfig()->getTitle()->prepend(__('Important Information & Merchant Guide'));

        return $resultPage;
    }
}
