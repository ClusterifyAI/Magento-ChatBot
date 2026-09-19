<?php
/**
 * ClusterifyAI ChatBot product consumer unit test
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Test\Unit\Model\Queue\Consumer;

use ClusterifyAI\ChatBot\Api\Data\SyncMessageInterface;
use ClusterifyAI\ChatBot\Model\Config;
use ClusterifyAI\ChatBot\Model\Queue\Consumer\ProductConsumer;
use ClusterifyAI\ChatBot\Model\Sync\ProviderPool;
use ClusterifyAI\ChatBot\Service\ClientFactory;
use ClusterifyAI\ChatBot\Service\PlanService;
use Magento\Framework\App\CacheInterface;
use Magento\Store\Model\App\Emulation;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Class ProductConsumerTest
 *
 * Tests the product queue consumer execution and error resilience.
 */
class ProductConsumerTest extends TestCase
{
    /**
     * Test process delegates to processMessage and exits when sync is disallowed.
     *
     * @return void
     */
    public function testProcessExitsWhenCanSyncReturnsFalse(): void
    {
        $planServiceStub = $this->createStub(PlanService::class);
        $planServiceStub->method('canSyncEntity')->willReturn(false);

        $clientFactoryMock = $this->createMock(ClientFactory::class);
        $clientFactoryMock->expects($this->never())->method('create');

        $consumer = new ProductConsumer(
            $clientFactoryMock,
            $this->createStub(Config::class),
            $this->createStub(ProviderPool::class),
            $this->createStub(Emulation::class),
            $this->createStub(CacheInterface::class),
            $planServiceStub,
            $this->createStub(LoggerInterface::class)
        );

        $messageStub = $this->createStub(SyncMessageInterface::class);
        $messageStub->method('getEntityType')->willReturn('product');
        $messageStub->method('getEntityId')->willReturn(20);
        $messageStub->method('getStoreId')->willReturn(1);

        $consumer->process($messageStub);
    }
}
