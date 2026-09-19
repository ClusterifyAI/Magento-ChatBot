<?php
/**
 * ClusterifyAI ChatBot product queue sync consumer
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
 * Class ProductConsumer
 *
 * Consumer worker for processing product synchronization messages from RabbitMQ.
 */
class ProductConsumer extends AbstractSyncConsumer
{
    /**
     * Consume a product sync message.
     *
     * @param SyncMessageInterface $message
     * @return void
     */
    public function process(SyncMessageInterface $message): void
    {
        $this->processMessage($message);
    }
}
