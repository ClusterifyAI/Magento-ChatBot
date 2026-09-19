<?php
/**
 * ClusterifyAI ChatBot queue processor service
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Model\Queue;

use ClusterifyAI\ChatBot\Api\Data\SyncMessageInterface;
use ClusterifyAI\ChatBot\Api\Data\SyncMessageInterfaceFactory;
use ClusterifyAI\ChatBot\Model\Config;
use ClusterifyAI\ChatBot\Model\Queue\Consumer\CategoryConsumer;
use ClusterifyAI\ChatBot\Model\Queue\Consumer\CmsConsumer;
use ClusterifyAI\ChatBot\Model\Queue\Consumer\ProductConsumer;
use ClusterifyAI\ChatBot\Service\PlanService;
use Magento\Framework\Amqp\Config as AmqpConfig;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;

/**
 * Class QueueProcessor
 *
 * Provides on-demand and scheduled execution of pending RabbitMQ messages
 * without requiring persistent daemon processes.
 */
class QueueProcessor
{
    public const QUEUE_CMS = 'clusterify.chatbot.sync.cms';
    public const QUEUE_CATEGORY = 'clusterify.chatbot.sync.category';
    public const QUEUE_PRODUCT = 'clusterify.chatbot.sync.product';

    /**
     * @param AmqpConfig                  $amqpConfig      AMQP configuration provider
     * @param SyncMessageInterfaceFactory $messageFactory  Sync message factory
     * @param CmsConsumer                 $cmsConsumer     CMS consumer worker
     * @param CategoryConsumer            $categoryConsumer Category consumer worker
     * @param ProductConsumer             $productConsumer Product consumer worker
     * @param Config                      $config          Configuration service
     * @param PlanService                 $planService     Plan verification service
     * @param LoggerInterface             $logger          System logger
     */
    public function __construct(
        private readonly AmqpConfig $amqpConfig,
        private readonly SyncMessageInterfaceFactory $messageFactory,
        private readonly CmsConsumer $cmsConsumer,
        private readonly CategoryConsumer $categoryConsumer,
        private readonly ProductConsumer $productConsumer,
        private readonly Config $config,
        private readonly PlanService $planService,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Process pending messages for a single entity queue.
     *
     * @param string $entityType Entity type: cms, category, product
     * @param int    $limit      Maximum messages to process
     * @return int Number of messages successfully processed
     */
    public function processQueue(string $entityType, int $limit = 50): int
    {
        $queueName = $this->resolveQueueName($entityType);
        if ($queueName === null) {
            return 0;
        }

        try {
            $channel = $this->amqpConfig->getChannel();
        } catch (\Throwable $e) {
            $this->logger->error('Clusterify QueueProcessor failed to acquire AMQP channel: ' . $e->getMessage());
            return 0;
        }

        $processed = 0;

        for ($i = 0; $i < $limit; $i++) {
            try {
                /** @var AMQPMessage|null $amqpMessage */
                $amqpMessage = $channel->basic_get($queueName);
            } catch (\Throwable $e) {
                $this->logger->error(sprintf(
                    'Clusterify QueueProcessor connection error fetching from "%s": %s',
                    $queueName,
                    $e->getMessage()
                ));
                break;
            }

            if ($amqpMessage === null) {
                break; // Queue is empty
            }

            try {
                $payload = json_decode((string) $amqpMessage->body, true);
                if (!is_array($payload)) {
                    // Invalid JSON, acknowledge to remove from queue
                    $channel->basic_ack($amqpMessage->delivery_info['delivery_tag']);
                    continue;
                }

                /** @var SyncMessageInterface $message */
                $message = $this->messageFactory->create();
                $message->setEntityType((string) ($payload['entity_type'] ?? $entityType));
                $message->setEntityId((int) ($payload['entity_id'] ?? 0));
                $message->setStoreId((int) ($payload['store_id'] ?? 1));
                $message->setAction((string) ($payload['action'] ?? 'upsert'));
                if (isset($payload['url'])) {
                    $message->setUrl((string) $payload['url']);
                }

                // Dispatch to specific consumer
                $this->dispatchConsumer($entityType, $message);

                // Acknowledge processed message
                $channel->basic_ack($amqpMessage->delivery_info['delivery_tag']);
                $processed++;
            } catch (\Throwable $e) {
                $this->logger->error(sprintf(
                    'Clusterify QueueProcessor error processing message on "%s": %s',
                    $queueName,
                    $e->getMessage()
                ));
                try {
                    $deliveryTag = $amqpMessage->delivery_info['delivery_tag'] ?? null;
                    if ($deliveryTag !== null) {
                        $channel->basic_ack($deliveryTag);
                    }
                } catch (\Throwable $ackException) {
                    $this->logger->warning(sprintf(
                        'Clusterify QueueProcessor failed to basic_ack message: %s',
                        $ackException->getMessage()
                    ));
                }
            }
        }

        return $processed;
    }

    /**
     * Process pending messages across all 3 synchronization queues.
     *
     * @param int $limitPerQueue Maximum messages per queue
     * @return array<string, int>
     */
    public function processAllQueues(int $limitPerQueue = 50): array
    {
        return [
            'cms' => $this->processQueue('cms', $limitPerQueue),
            'category' => $this->processQueue('category', $limitPerQueue),
            'product' => $this->processQueue('product', $limitPerQueue),
        ];
    }

    /**
     * Dispatch message to appropriate consumer.
     *
     * @param string                $entityType
     * @param SyncMessageInterface $message
     * @return void
     */
    private function dispatchConsumer(string $entityType, SyncMessageInterface $message): void
    {
        match ($entityType) {
            'cms' => $this->cmsConsumer->process($message),
            'category' => $this->categoryConsumer->process($message),
            'product' => $this->productConsumer->process($message),
            default => null,
        };
    }

    /**
     * Resolve queue name from entity type.
     *
     * @param string $entityType
     * @return string|null
     */
    private function resolveQueueName(string $entityType): ?string
    {
        return match (strtolower(trim($entityType))) {
            'cms' => self::QUEUE_CMS,
            'category' => self::QUEUE_CATEGORY,
            'product' => self::QUEUE_PRODUCT,
            default => null,
        };
    }
}
