<?php
/**
 * ClusterifyAI ChatBot CMS consumer unit test
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Test\Unit\Model\Queue\Consumer;

use Clusterify\ClusterifyClient;
use ClusterifyAI\ChatBot\Api\Data\SyncMessageInterface;
use ClusterifyAI\ChatBot\Model\Config;
use ClusterifyAI\ChatBot\Model\Queue\Consumer\CmsConsumer;
use ClusterifyAI\ChatBot\Model\Sync\ProviderPool;
use ClusterifyAI\ChatBot\Model\Sync\SyncItem;
use ClusterifyAI\ChatBot\Service\ClientFactory;
use ClusterifyAI\ChatBot\Service\PlanService;
use Magento\Framework\App\CacheInterface;
use Magento\Store\Model\App\Emulation;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Class CmsConsumerTest
 *
 * Tests the queue consumer execution, plan gating, and error resilience.
 */
class CmsConsumerTest extends TestCase
{
    /**
     * Test processMessage exits early when planService canSyncEntity returns false.
     *
     * @return void
     */
    public function testProcessExitsEarlyWhenCanSyncReturnsFalse(): void
    {
        $planServiceStub = $this->createStub(PlanService::class);
        $planServiceStub->method('canSyncEntity')->willReturn(false);

        $clientFactoryMock = $this->createMock(ClientFactory::class);
        $clientFactoryMock->expects($this->never())->method('create');

        $consumer = new CmsConsumer(
            $clientFactoryMock,
            $this->createStub(Config::class),
            $this->createStub(ProviderPool::class),
            $this->createStub(Emulation::class),
            $this->createStub(CacheInterface::class),
            $planServiceStub,
            $this->createStub(LoggerInterface::class)
        );

        $messageStub = $this->createStub(SyncMessageInterface::class);
        $messageStub->method('getEntityType')->willReturn('cms');
        $messageStub->method('getEntityId')->willReturn(5);
        $messageStub->method('getStoreId')->willReturn(1);

        $consumer->process($messageStub);
    }

    /**
     * Test processMessage catches Throwable and logs error without crashing.
     *
     * @return void
     */
    public function testProcessCatchesThrowableAndLogsError(): void
    {
        $planServiceStub = $this->createStub(PlanService::class);
        $planServiceStub->method('canSyncEntity')->willThrowException(new \RuntimeException('Fatal runtime outage'));

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->once())->method('error');

        $consumer = new CmsConsumer(
            $this->createStub(ClientFactory::class),
            $this->createStub(Config::class),
            $this->createStub(ProviderPool::class),
            $this->createStub(Emulation::class),
            $this->createStub(CacheInterface::class),
            $planServiceStub,
            $loggerMock
        );

        $messageStub = $this->createStub(SyncMessageInterface::class);
        $messageStub->method('getEntityType')->willReturn('cms');
        $messageStub->method('getEntityId')->willReturn(9);
        $messageStub->method('getStoreId')->willReturn(1);

        // Must not throw or crash
        $consumer->process($messageStub);
    }

    /**
     * Test processMessage catches client initialization error and logs without crashing.
     *
     * @return void
     */
    public function testProcessCatchesClientInitializationErrorAndLogs(): void
    {
        $planServiceStub = $this->createStub(PlanService::class);
        $planServiceStub->method('canSyncEntity')->willReturn(true);

        $cacheStub = $this->createStub(CacheInterface::class);
        $cacheStub->method('load')->willReturn(false);

        $clientFactoryStub = $this->createStub(ClientFactory::class);
        $clientFactoryStub->method('create')->willThrowException(new \RuntimeException('Connection to Clusterify API refused'));

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->once())->method('error');

        $consumer = new CmsConsumer(
            $clientFactoryStub,
            $this->createStub(Config::class),
            $this->createStub(ProviderPool::class),
            $this->createStub(Emulation::class),
            $cacheStub,
            $planServiceStub,
            $loggerMock
        );

        $messageStub = $this->createStub(SyncMessageInterface::class);
        $messageStub->method('getEntityType')->willReturn('cms');
        $messageStub->method('getEntityId')->willReturn(12);
        $messageStub->method('getStoreId')->willReturn(1);
        $messageStub->method('getAction')->willReturn(SyncItem::ACTION_DELETE);
        $messageStub->method('getUrl')->willReturn('http://magento.test/deleted-page');

        $consumer->process($messageStub);
    }
}
