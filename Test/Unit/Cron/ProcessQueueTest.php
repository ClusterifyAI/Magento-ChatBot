<?php
/**
 * ClusterifyAI ChatBot process queue cron unit test
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Test\Unit\Cron;

use ClusterifyAI\ChatBot\Cron\ProcessQueue;
use ClusterifyAI\ChatBot\Model\Config;
use ClusterifyAI\ChatBot\Model\Queue\QueueProcessor;
use ClusterifyAI\ChatBot\Service\PlanService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Class ProcessQueueTest
 *
 * Tests the automated background queue processing cron job.
 */
class ProcessQueueTest extends TestCase
{
    /**
     * Test execute exits early when extension or sync is disabled.
     *
     * @return void
     */
    public function testExecuteExitsWhenDisabled(): void
    {
        $configStub = $this->createStub(Config::class);
        $configStub->method('isEnabled')->willReturn(false);

        $queueProcessorMock = $this->createMock(QueueProcessor::class);
        $queueProcessorMock->expects($this->never())->method('processAllQueues');

        $cron = new ProcessQueue(
            $queueProcessorMock,
            $configStub,
            $this->createStub(PlanService::class),
            $this->createStub(LoggerInterface::class)
        );

        $cron->execute();
    }

    /**
     * Test execute exits early when queue cron is disabled.
     *
     * @return void
     */
    public function testExecuteExitsWhenQueueCronDisabled(): void
    {
        $configStub = $this->createStub(Config::class);
        $configStub->method('isEnabled')->willReturn(true);
        $configStub->method('isSyncEnabled')->willReturn(true);
        $configStub->method('isQueueCronEnabled')->willReturn(false);

        $queueProcessorMock = $this->createMock(QueueProcessor::class);
        $queueProcessorMock->expects($this->never())->method('processAllQueues');

        $cron = new ProcessQueue(
            $queueProcessorMock,
            $configStub,
            $this->createStub(PlanService::class),
            $this->createStub(LoggerInterface::class)
        );

        $cron->execute();
    }

    /**
     * Test execute exits early when plan does not allow URL knowledge.
     *
     * @return void
     */
    public function testExecuteExitsWhenPlanNotAllowed(): void
    {
        $configStub = $this->createStub(Config::class);
        $configStub->method('isEnabled')->willReturn(true);
        $configStub->method('isSyncEnabled')->willReturn(true);
        $configStub->method('isQueueCronEnabled')->willReturn(true);

        $planServiceStub = $this->createStub(PlanService::class);
        $planServiceStub->method('isUrlKnowledgeAllowed')->willReturn(false);

        $queueProcessorMock = $this->createMock(QueueProcessor::class);
        $queueProcessorMock->expects($this->never())->method('processAllQueues');

        $cron = new ProcessQueue(
            $queueProcessorMock,
            $configStub,
            $planServiceStub,
            $this->createStub(LoggerInterface::class)
        );

        $cron->execute();
    }

    /**
     * Test execute processes queues and logs when tasks were executed.
     *
     * @return void
     */
    public function testExecuteProcessesQueuesSuccessfully(): void
    {
        $configStub = $this->createStub(Config::class);
        $configStub->method('isEnabled')->willReturn(true);
        $configStub->method('isSyncEnabled')->willReturn(true);
        $configStub->method('isQueueCronEnabled')->willReturn(true);
        $configStub->method('getQueueBatchSize')->willReturn(50);

        $planServiceStub = $this->createStub(PlanService::class);
        $planServiceStub->method('isUrlKnowledgeAllowed')->willReturn(true);

        $queueProcessorMock = $this->createMock(QueueProcessor::class);
        $queueProcessorMock->expects($this->once())
            ->method('processAllQueues')
            ->with(50)
            ->willReturn(['cms' => 5, 'category' => 10, 'product' => 15]);

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->once())->method('info');

        $cron = new ProcessQueue(
            $queueProcessorMock,
            $configStub,
            $planServiceStub,
            $loggerMock
        );

        $cron->execute();
    }
}
