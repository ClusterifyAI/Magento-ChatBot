<?php
/**
 * ClusterifyAI ChatBot message queue publisher service
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
use InvalidArgumentException;
use Magento\Framework\MessageQueue\PublisherInterface;
use Psr\Log\LoggerInterface;

/**
 * Class Publisher
 *
 * Dispatches entity synchronization events asynchronously to RabbitMQ topics.
 */
class Publisher
{
    public const TOPIC_CMS = 'clusterify.chatbot.sync.cms';
    public const TOPIC_CATEGORY = 'clusterify.chatbot.sync.category';
    public const TOPIC_PRODUCT = 'clusterify.chatbot.sync.product';

    /**
     * @param PublisherInterface          $publisher      Magento message queue publisher
     * @param SyncMessageInterfaceFactory $messageFactory Factory for sync messages
     * @param LoggerInterface             $logger         System logger
     */
    public function __construct(
        private readonly PublisherInterface $publisher,
        private readonly SyncMessageInterfaceFactory $messageFactory,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Publish a single entity synchronization message.
     *
     * @param string      $entityType Entity type code (cms, category, product)
     * @param int         $entityId   Magento entity ID
     * @param int         $storeId    Store view ID
     * @param string      $action     Sync action (upsert or delete)
     * @param string|null $url        Explicit entity URL for direct purge or synchronization
     * @return void
     * @throws InvalidArgumentException
     */
    public function publish(
        string $entityType,
        int $entityId,
        int $storeId,
        string $action = 'upsert',
        ?string $url = null
    ): void {
        if ($storeId <= 0 || $entityId <= 0) {
            $this->logger->warning(sprintf(
                'Clusterify Publisher rejected message for "%s" with invalid entity ID %d or store ID %d.',
                $entityType,
                $entityId,
                $storeId
            ));
            return;
        }

        $topicName = $this->resolveTopicName($entityType);

        /** @var SyncMessageInterface $message */
        $message = $this->messageFactory->create();
        $message->setEntityType($entityType);
        $message->setEntityId($entityId);
        $message->setStoreId($storeId);
        $message->setAction($action);
        if ($url !== null) {
            $message->setUrl($url);
        }

        $this->publisher->publish($topicName, $message);
    }

    /**
     * Publish a batch of entity IDs.
     *
     * @param string    $entityType Entity type code (cms, category, product)
     * @param list<int> $entityIds  List of entity IDs
     * @param int       $storeId    Store view ID
     * @param string    $action     Sync action (upsert or delete)
     * @return void
     */
    public function publishBatch(
        string $entityType,
        array $entityIds,
        int $storeId,
        string $action = 'upsert'
    ): void {
        if ($storeId <= 0) {
            $this->logger->warning(sprintf(
                'Clusterify Publisher rejected batch for "%s" with invalid store ID %d.',
                $entityType,
                $storeId
            ));
            return;
        }

        if (empty($entityIds)) {
            return;
        }

        foreach ($entityIds as $id) {
            $this->publish($entityType, (int) $id, $storeId, $action);
        }
    }

    /**
     * Resolve topic name from entity type.
     *
     * @param string $entityType
     * @return string
     * @throws InvalidArgumentException
     */
    private function resolveTopicName(string $entityType): string
    {
        return match ($entityType) {
            'cms' => self::TOPIC_CMS,
            'category' => self::TOPIC_CATEGORY,
            'product' => self::TOPIC_PRODUCT,
            default => throw new InvalidArgumentException(sprintf('Unsupported sync entity type: "%s".', $entityType)),
        };
    }
}
