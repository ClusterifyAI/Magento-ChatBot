<?php
/**
 * ClusterifyAI ChatBot category queue sync consumer
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Model\Queue\Consumer;

use ClusterifyAI\ChatBot\Api\Data\SyncMessageInterface;

/**
 * Class CategoryConsumer
 *
 * Consumer worker for processing category synchronization messages from RabbitMQ.
 */
class CategoryConsumer extends AbstractSyncConsumer
{
    /**
     * Consume a category sync message.
     *
     * @param SyncMessageInterface $message
     * @return void
     */
    public function process(SyncMessageInterface $message): void
    {
        $this->processMessage($message);
    }
}
