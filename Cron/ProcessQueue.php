<?php
/**
 * ClusterifyAI ChatBot queue processing cron job
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Cron;

use ClusterifyAI\ChatBot\Model\Config;
use ClusterifyAI\ChatBot\Model\Queue\QueueProcessor;
use ClusterifyAI\ChatBot\Service\PlanService;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * Class ProcessQueue
 *
 * Cron job executing every minute to drain pending RabbitMQ messages into Clusterify.AI,
 * ensuring synchronization progresses even when no persistent daemon is running.
 */
class ProcessQueue
{
    /**
     * @param QueueProcessor  $queueProcessor Queue processing service
     * @param Config          $config         Configuration provider
     * @param PlanService     $planService    Plan verification service
     * @param LoggerInterface $logger         System logger
     */
    public function __construct(
        private readonly QueueProcessor $queueProcessor,
        private readonly Config $config,
        private readonly PlanService $planService,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Execute cron job.
     *
     * @return void
     */
    public function execute(): void
    {
        if (!$this->config->isEnabled() || !$this->config->isSyncEnabled()) {
            return;
        }

        if (!$this->config->isQueueCronEnabled()) {
            return;
        }

        if (!$this->planService->isUrlKnowledgeAllowed()) {
            return;
        }

        try {
            $batchSize = $this->config->getQueueBatchSize();
            $results = $this->queueProcessor->processAllQueues($batchSize);

            $total = array_sum($results);
            if ($total > 0) {
                $this->logger->info(sprintf(
                    'Clusterify Cron processed %d sync tasks (CMS: %d, Category: %d, Product: %d).',
                    $total,
                    $results['cms'] ?? 0,
                    $results['category'] ?? 0,
                    $results['product'] ?? 0
                ));
            }
        } catch (Exception $e) {
            $this->logger->error('Clusterify Cron ProcessQueue failed: ' . $e->getMessage());
        }
    }
}
