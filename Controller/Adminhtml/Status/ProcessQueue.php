<?php
/**
 * ClusterifyAI ChatBot admin process queue AJAX controller
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Controller\Adminhtml\Status;

use ClusterifyAI\ChatBot\Block\Adminhtml\Status\Dashboard as DashboardBlock;
use ClusterifyAI\ChatBot\Model\Config;
use ClusterifyAI\ChatBot\Model\Queue\QueueProcessor;
use ClusterifyAI\ChatBot\Service\PlanService;
use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\View\LayoutFactory;
use Psr\Log\LoggerInterface;

/**
 * Class ProcessQueue
 *
 * AJAX endpoint triggered from the Admin Status Dashboard to drain pending
 * RabbitMQ tasks on-demand without requiring terminal access.
 */
class ProcessQueue extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'ClusterifyAI_ChatBot::status';

    /**
     * @param Context          $context        Action context
     * @param JsonFactory      $resultJsonFactory JSON response factory
     * @param QueueProcessor   $queueProcessor Queue processing service
     * @param Config           $config         Configuration service
     * @param PlanService      $planService    Plan verification service
     * @param LayoutFactory    $layoutFactory  Layout factory
     * @param LoggerInterface  $logger         System logger
     */
    public function __construct(
        Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly QueueProcessor $queueProcessor,
        private readonly Config $config,
        private readonly PlanService $planService,
        private readonly LayoutFactory $layoutFactory,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * Process pending queue tasks on-demand.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();

        if (!$this->planService->isUrlKnowledgeAllowed()) {
            return $result->setData([
                'success' => false,
                'message' => (string) __('URL Knowledge Base synchronization is locked on your current plan.'),
            ]);
        }

        try {
            $batchSize = max(10, min(100, $this->config->getQueueBatchSize()));
            $results = $this->queueProcessor->processAllQueues($batchSize);
            $total = array_sum($results);

            // Fetch fresh queue counts
            $layout = $this->layoutFactory->create();
            /** @var DashboardBlock $dashboardBlock */
            $dashboardBlock = $layout->createBlock(DashboardBlock::class);
            $queues = $dashboardBlock->getQueueStatusList();

            $msg = $total > 0
                ? (string) __('Successfully processed %1 pending sync tasks.', $total)
                : (string) __('All synchronization queues are already up-to-date (0 pending).');

            return $result->setData([
                'success' => true,
                'total_processed' => $total,
                'processed' => $results,
                'queues' => $queues,
                'message' => $msg,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Clusterify Admin ProcessQueue AJAX error: ' . $e->getMessage());

            return $result->setData([
                'success' => false,
                'message' => (string) __('An error occurred while processing queue tasks: %1', $e->getMessage()),
            ]);
        }
    }
}
