<?php
/**
 * ClusterifyAI ChatBot sync data provider interface
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Model\Sync;

/**
 * Interface DataProviderInterface
 *
 * Contract for entity data providers extracting structured Markdown knowledge for Clusterify.AI.
 * Designed to be modular and extendable for custom entities and attributes.
 */
interface DataProviderInterface
{
    /**
     * Retrieve the entity type code this provider handles (e.g. 'cms', 'category', 'product').
     *
     * @return string
     */
    public function getEntityType(): string;

    /**
     * Extract a single entity into a SyncItem DTO.
     *
     * @param int $entityId Magento entity ID
     * @param int $storeId  Store view ID
     * @return SyncItem|null Returns null if entity does not exist or has no URL
     */
    public function extract(int $entityId, int $storeId): ?SyncItem;

    /**
     * Extract a batch of entities into an array of SyncItem DTOs.
     *
     * @param list<int> $entityIds List of Magento entity IDs
     * @param int       $storeId   Store view ID
     * @return list<SyncItem>
     */
    public function extractBatch(array $entityIds, int $storeId): array;
}
