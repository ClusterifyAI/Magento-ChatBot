<?php
/**
 * ClusterifyAI ChatBot queue sync message model
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
use Magento\Framework\DataObject;

/**
 * Class SyncMessage
 *
 * Message data object for asynchronous queue communication.
 */
class SyncMessage extends DataObject implements SyncMessageInterface
{
    /**
     * @inheritdoc
     */
    public function getEntityId(): int
    {
        return (int) $this->getData(self::ENTITY_ID);
    }

    /**
     * @inheritdoc
     */
    public function setEntityId(int $entityId): self
    {
        return $this->setData(self::ENTITY_ID, $entityId);
    }

    /**
     * @inheritdoc
     */
    public function getEntityType(): string
    {
        return (string) $this->getData(self::ENTITY_TYPE);
    }

    /**
     * @inheritdoc
     */
    public function setEntityType(string $entityType): self
    {
        return $this->setData(self::ENTITY_TYPE, $entityType);
    }

    /**
     * @inheritdoc
     */
    public function getStoreId(): int
    {
        return (int) $this->getData(self::STORE_ID);
    }

    /**
     * @inheritdoc
     */
    public function setStoreId(int $storeId): self
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    /**
     * @inheritdoc
     */
    public function getAction(): string
    {
        return (string) ($this->getData(self::ACTION) ?? 'upsert');
    }

    /**
     * @inheritdoc
     */
    public function setAction(string $action): self
    {
        return $this->setData(self::ACTION, $action);
    }

    /**
     * @inheritdoc
     */
    public function getUrl(): ?string
    {
        $url = $this->getData(self::URL);
        return $url !== null ? (string) $url : null;
    }

    /**
     * @inheritdoc
     */
    public function setUrl(?string $url): self
    {
        return $this->setData(self::URL, $url);
    }
}
