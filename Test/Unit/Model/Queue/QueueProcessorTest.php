<?php
/**
 * ClusterifyAI ChatBot queue processor unit test
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Test\Unit\Model\Queue;

use ClusterifyAI\ChatBot\Api\Data\SyncMessageInterface;
use ClusterifyAI\ChatBot\Api\Data\SyncMessageInterfaceFactory;
use ClusterifyAI\ChatBot\Model\Config;
use ClusterifyAI\ChatBot\Model\Queue\Consumer\CategoryConsumer;
use ClusterifyAI\ChatBot\Model\Queue\Consumer\CmsConsumer;
use ClusterifyAI\ChatBot\Model\Queue\Consumer\ProductConsumer;
use ClusterifyAI\ChatBot\Model\Queue\QueueProcessor;
use ClusterifyAI\ChatBot\Service\PlanService;
use Exception;
use Magento\Framework\Amqp\Config as AmqpConfig;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Class QueueProcessorTest
 *
 * Tests the on-demand and cron queue processor service.
 */
class QueueProcessorTest extends TestCase
{
    /**
     * Test processQueue returns 0 for invalid entity type.
     *
     * @return void
     */
    public function testProcessQueueReturnsZeroForInvalidEntity(): void
    {
        $processor = new QueueProcessor(
            $this->createStub(AmqpConfig::class),
            $this->createStub(SyncMessageInterfaceFactory::class),
            $this->createStub(CmsConsumer::class),
            $this->createStub(CategoryConsumer::class),
            $this->createStub(ProductConsumer::class),
            $this->createStub(Config::class),
            $this->createStub(PlanService::class),
            $this->createStub(LoggerInterface::class)
        );

        $this->assertSame(0, $processor->processQueue('invalid_entity', 10));
    }

    /**
     * Test processQueue handles AMQP channel exception gracefully.
     *
     * @return void
     */
    public function testProcessQueueHandlesChannelException(): void
    {
        $amqpConfigStub = $this->createStub(AmqpConfig::class);
        $amqpConfigStub->method('getChannel')->willThrowException(new Exception('Connection failed'));

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->once())->method('error');

        $processor = new QueueProcessor(
            $amqpConfigStub,
            $this->createStub(SyncMessageInterfaceFactory::class),
            $this->createStub(CmsConsumer::class),
            $this->createStub(CategoryConsumer::class),
            $this->createStub(ProductConsumer::class),
            $this->createStub(Config::class),
            $this->createStub(PlanService::class),
            $loggerMock
        );

        $this->assertSame(0, $processor->processQueue('cms', 10));
    }

    /**
     * Test processQueue returns 0 when queue is empty.
     *
     * @return void
     */
    public function testProcessQueueReturnsZeroWhenQueueIsEmpty(): void
    {
        $channelMock = $this->createMock(AMQPChannel::class);
        $channelMock->expects($this->once())
            ->method('basic_get')
            ->with(QueueProcessor::QUEUE_CMS)
            ->willReturn(null);

        $amqpConfigStub = $this->createStub(AmqpConfig::class);
        $amqpConfigStub->method('getChannel')->willReturn($channelMock);

        $processor = new QueueProcessor(
            $amqpConfigStub,
            $this->createStub(SyncMessageInterfaceFactory::class),
            $this->createStub(CmsConsumer::class),
            $this->createStub(CategoryConsumer::class),
            $this->createStub(ProductConsumer::class),
            $this->createStub(Config::class),
            $this->createStub(PlanService::class),
            $this->createStub(LoggerInterface::class)
        );

        $this->assertSame(0, $processor->processQueue('cms', 10));
    }

    /**
     * Test processQueue processes and acknowledges messages.
     *
     * @return void
     */
    public function testProcessQueueProcessesMessageSuccessfully(): void
    {
        $channelMock = $this->createMock(AMQPChannel::class);
        $msg = new AMQPMessage(json_encode([
            'entity_id' => 5,
            'entity_type' => 'cms',
            'store_id' => 1,
            'action' => 'upsert',
        ]));
        $msg->delivery_info = ['delivery_tag' => 'tag_123'];

        $channelMock->expects($this->exactly(2))
            ->method('basic_get')
            ->with(QueueProcessor::QUEUE_CMS)
            ->willReturnOnConsecutiveCalls($msg, null);

        $channelMock->expects($this->once())
            ->method('basic_ack')
            ->with('tag_123');

        $amqpConfigStub = $this->createStub(AmqpConfig::class);
        $amqpConfigStub->method('getChannel')->willReturn($channelMock);

        $messageStub = $this->createStub(SyncMessageInterface::class);
        $messageFactoryStub = $this->createStub(SyncMessageInterfaceFactory::class);
        $messageFactoryStub->method('create')->willReturn($messageStub);

        $cmsConsumerMock = $this->createMock(CmsConsumer::class);
        $cmsConsumerMock->expects($this->once())
            ->method('process')
            ->with($messageStub);

        $processor = new QueueProcessor(
            $amqpConfigStub,
            $messageFactoryStub,
            $cmsConsumerMock,
            $this->createStub(CategoryConsumer::class),
            $this->createStub(ProductConsumer::class),
            $this->createStub(Config::class),
            $this->createStub(PlanService::class),
            $this->createStub(LoggerInterface::class)
        );

        $count = $processor->processQueue('cms', 10);
        $this->assertSame(1, $count);
    }

    /**
     * Test processQueue acknowledges malformed JSON and continues without crashing.
     *
     * @return void
     */
    public function testProcessQueueAcksAndContinuesOnMalformedJson(): void
    {
        $channelMock = $this->createMock(AMQPChannel::class);
        $invalidMsg = new AMQPMessage('INVALID_NON_JSON{{{');
        $invalidMsg->delivery_info = ['delivery_tag' => 'tag_bad'];

        $channelMock->expects($this->exactly(2))
            ->method('basic_get')
            ->with(QueueProcessor::QUEUE_CMS)
            ->willReturnOnConsecutiveCalls($invalidMsg, null);

        $channelMock->expects($this->once())
            ->method('basic_ack')
            ->with('tag_bad');

        $amqpConfigStub = $this->createStub(AmqpConfig::class);
        $amqpConfigStub->method('getChannel')->willReturn($channelMock);

        $cmsConsumerMock = $this->createMock(CmsConsumer::class);
        $cmsConsumerMock->expects($this->never())->method('process');

        $processor = new QueueProcessor(
            $amqpConfigStub,
            $this->createStub(SyncMessageInterfaceFactory::class),
            $cmsConsumerMock,
            $this->createStub(CategoryConsumer::class),
            $this->createStub(ProductConsumer::class),
            $this->createStub(Config::class),
            $this->createStub(PlanService::class),
            $this->createStub(LoggerInterface::class)
        );

        $count = $processor->processQueue('cms', 10);
        $this->assertSame(0, $count);
    }

    /**
     * Test processQueue acknowledges poison message when consumer throws an error.
     *
     * @return void
     */
    public function testProcessQueueAcksPoisonMessageWhenConsumerThrows(): void
    {
        $channelMock = $this->createMock(AMQPChannel::class);
        $msg = new AMQPMessage(json_encode(['entity_id' => 99, 'entity_type' => 'cms']));
        $msg->delivery_info = ['delivery_tag' => 'tag_poison'];

        $channelMock->expects($this->exactly(2))
            ->method('basic_get')
            ->with(QueueProcessor::QUEUE_CMS)
            ->willReturnOnConsecutiveCalls($msg, null);

        $channelMock->expects($this->once())
            ->method('basic_ack')
            ->with('tag_poison');

        $amqpConfigStub = $this->createStub(AmqpConfig::class);
        $amqpConfigStub->method('getChannel')->willReturn($channelMock);

        $cmsConsumerMock = $this->createMock(CmsConsumer::class);
        $cmsConsumerMock->expects($this->once())
            ->method('process')
            ->willThrowException(new \RuntimeException('Consumer processing failed'));

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->once())->method('error');

        $messageStub = $this->createStub(SyncMessageInterface::class);
        $messageFactoryStub = $this->createStub(SyncMessageInterfaceFactory::class);
        $messageFactoryStub->method('create')->willReturn($messageStub);

        $processor = new QueueProcessor(
            $amqpConfigStub,
            $messageFactoryStub,
            $cmsConsumerMock,
            $this->createStub(CategoryConsumer::class),
            $this->createStub(ProductConsumer::class),
            $this->createStub(Config::class),
            $this->createStub(PlanService::class),
            $loggerMock
        );

        $count = $processor->processQueue('cms', 10);
        $this->assertSame(0, $count);
    }
}
