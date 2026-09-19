<?php
/**
 * ClusterifyAI ChatBot queue sync message interface
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Api\Data;

/**
 * Interface SyncMessageInterface
 *
 * Data contract for asynchronous entity synchronization messages published to RabbitMQ.
 */
interface SyncMessageInterface
{
    public const ENTITY_ID = 'entity_id';
    public const ENTITY_TYPE = 'entity_type';
    public const STORE_ID = 'store_id';
    public const ACTION = 'action';
    public const URL = 'url';

    /**
     * Retrieve the Magento entity ID.
     *
     * @return int
     */
    public function getEntityId(): int;

    /**
     * Set the Magento entity ID.
     *
     * @param int $entityId
     * @return $this
     */
    public function setEntityId(int $entityId): self;

    /**
     * Retrieve the entity type code (cms, category, product).
     *
     * @return string
     */
    public function getEntityType(): string;

    /**
     * Set the entity type code.
     *
     * @param string $entityType
     * @return $this
     */
    public function setEntityType(string $entityType): self;

    /**
     * Retrieve the store view ID.
     *
     * @return int
     */
    public function getStoreId(): int;

    /**
     * Set the store view ID.
     *
     * @param int $storeId
     * @return $this
     */
    public function setStoreId(int $storeId): self;

    /**
     * Retrieve the sync action (upsert or delete).
     *
     * @return string
     */
    public function getAction(): string;

    /**
     * Set the sync action.
     *
     * @param string $action
     * @return $this
     */
    public function setAction(string $action): self;

    /**
     * Retrieve the explicit target URL (essential for entity deletions).
     *
     * @return string|null
     */
    public function getUrl(): ?string;

    /**
     * Set the explicit target URL.
     *
     * @param string|null $url
     * @return $this
     */
    public function setUrl(?string $url): self;
}
