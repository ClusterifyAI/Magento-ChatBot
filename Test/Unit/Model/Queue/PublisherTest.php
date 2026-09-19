<?php
/**
 * ClusterifyAI ChatBot queue publisher unit test
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
use ClusterifyAI\ChatBot\Model\Queue\Publisher;
use ClusterifyAI\ChatBot\Model\Queue\SyncMessage;
use InvalidArgumentException;
use Magento\Framework\MessageQueue\PublisherInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Class PublisherTest
 *
 * Unit tests for ClusterifyAI\ChatBot\Model\Queue\Publisher.
 */
#[AllowMockObjectsWithoutExpectations]
class PublisherTest extends TestCase
{
    private PublisherInterface|MockObject $publisherMock;
    private SyncMessageInterfaceFactory|MockObject $messageFactoryMock;
    private LoggerInterface $loggerStub;
    private Publisher $publisher;

    /**
     * Set up test instances.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->publisherMock = $this->createMock(PublisherInterface::class);
        $this->messageFactoryMock = $this->createMock(SyncMessageInterfaceFactory::class);
        $this->loggerStub = $this->createStub(LoggerInterface::class);

        $this->messageFactoryMock->method('create')->willReturnCallback(function () {
            return new SyncMessage();
        });

        $this->publisher = new Publisher(
            $this->publisherMock,
            $this->messageFactoryMock,
            $this->loggerStub
        );
    }

    /**
     * Test single message publish routes to correct topic and attaches URL.
     *
     * @return void
     */
    public function testPublishRoutesToCorrectTopicWithUrl(): void
    {
        $this->publisherMock->expects($this->once())
            ->method('publish')
            ->with(
                Publisher::TOPIC_PRODUCT,
                $this->callback(function (SyncMessageInterface $msg) {
                    return $msg->getEntityId() === 42
                        && $msg->getEntityType() === 'product'
                        && $msg->getStoreId() === 1
                        && $msg->getAction() === 'delete'
                        && $msg->getUrl() === 'http://magento.test/deleted.html';
                })
            );

        $this->publisher->publish('product', 42, 1, 'delete', 'http://magento.test/deleted.html');
    }

    /**
     * Test unknown entity type throws exception.
     *
     * @return void
     */
    public function testPublishThrowsExceptionOnUnknownEntity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported sync entity type: "unknown"');

        $this->publisher->publish('unknown', 1, 1);
    }

    /**
     * Test single message publish rejects non-positive entity or store ID and logs warning.
     *
     * @return void
     */
    public function testPublishRejectsNonPositiveEntityOrStoreId(): void
    {
        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->exactly(2))->method('warning');

        $publisher = new Publisher(
            $this->publisherMock,
            $this->messageFactoryMock,
            $loggerMock
        );

        $this->publisherMock->expects($this->never())->method('publish');

        // Invalid entityId (0)
        $publisher->publish('product', 0, 1);

        // Invalid storeId (0)
        $publisher->publish('product', 10, 0);
    }

    /**
     * Test batch publish rejects non-positive store ID and logs warning.
     *
     * @return void
     */
    public function testPublishBatchRejectsNonPositiveStoreId(): void
    {
        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('rejected batch for "product" with invalid store ID 0'));

        $publisher = new Publisher(
            $this->publisherMock,
            $this->messageFactoryMock,
            $loggerMock
        );

        $this->publisherMock->expects($this->never())->method('publish');

        $publisher->publishBatch('product', [1, 2, 3], 0);
    }

    /**
     * Test batch publish iterates and publishes all valid messages.
     *
     * @return void
     */
    public function testPublishBatchPublishesEachEntity(): void
    {
        $this->publisherMock->expects($this->exactly(2))
            ->method('publish')
            ->with(
                Publisher::TOPIC_PRODUCT,
                $this->isInstanceOf(SyncMessageInterface::class)
            );

        $this->publisher->publishBatch('product', [10, 20], 1);
    }
}
