<?php
/**
 * ClusterifyAI ChatBot sync consume CLI command unit test
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Test\Unit\Console\Command;

use ClusterifyAI\ChatBot\Console\Command\SyncConsumeCommand;
use ClusterifyAI\ChatBot\Model\Config;
use ClusterifyAI\ChatBot\Model\Queue\QueueProcessor;
use ClusterifyAI\ChatBot\Service\PlanService;
use Magento\Framework\App\State as AppState;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Class SyncConsumeCommandTest
 *
 * Tests the CLI command for consuming and draining RabbitMQ messages.
 */
class SyncConsumeCommandTest extends TestCase
{
    /**
     * Test command fails when sync is disabled.
     *
     * @return void
     */
    public function testExecuteFailsWhenSyncDisabled(): void
    {
        $configStub = $this->createStub(Config::class);
        $configStub->method('isEnabled')->willReturn(true);
        $configStub->method('isSyncEnabled')->willReturn(false);

        $command = new SyncConsumeCommand(
            $this->createStub(QueueProcessor::class),
            $configStub,
            $this->createStub(PlanService::class),
            $this->createStub(AppState::class)
        );

        $tester = new CommandTester($command);
        $status = $tester->execute([]);
        $this->assertSame(Command::FAILURE, $status);
        $this->assertStringContainsString('disabled', $tester->getDisplay());
    }

    /**
     * Test command executes and drains all queues.
     *
     * @return void
     */
    public function testExecuteDrainsAllQueues(): void
    {
        $configStub = $this->createStub(Config::class);
        $configStub->method('isEnabled')->willReturn(true);
        $configStub->method('isSyncEnabled')->willReturn(true);

        $planServiceStub = $this->createStub(PlanService::class);
        $planServiceStub->method('isUrlKnowledgeAllowed')->willReturn(true);

        $queueProcessorMock = $this->createMock(QueueProcessor::class);
        $queueProcessorMock->expects($this->once())
            ->method('processAllQueues')
            ->with(50)
            ->willReturn(['cms' => 2, 'category' => 4, 'product' => 6]);

        $command = new SyncConsumeCommand(
            $queueProcessorMock,
            $configStub,
            $planServiceStub,
            $this->createStub(AppState::class)
        );

        $tester = new CommandTester($command);
        $status = $tester->execute(['--entity' => 'all']);
        $this->assertSame(Command::SUCCESS, $status);
        $this->assertStringContainsString('Total tasks processed: 12', $tester->getDisplay());
    }
}
