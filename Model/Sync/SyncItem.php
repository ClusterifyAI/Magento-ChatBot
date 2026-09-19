<?php
/**
 * ClusterifyAI ChatBot URL knowledge sync item DTO
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
 * Class SyncItem
 *
 * Immutable data transfer object representing a processed entity ready for Clusterify URL knowledge synchronization.
 */
final readonly class SyncItem
{
    public const ACTION_UPSERT = 'upsert';
    public const ACTION_DELETE = 'delete';

    /**
     * @param string $entityType Entity type (cms, category, product)
     * @param int    $entityId   Entity ID in Magento
     * @param int    $storeId    Store view ID
     * @param string $url        Full storefront URL
     * @param string $content    Generated Markdown documentation content
     * @param bool   $isEnabled  Whether the knowledge record is enabled
     * @param string $action     Sync action (upsert or delete)
     */
    public function __construct(
        public string $entityType,
        public int $entityId,
        public int $storeId,
        public string $url,
        public string $content = '',
        public bool $isEnabled = true,
        public string $action = self::ACTION_UPSERT
    ) {}

    /**
     * Convert DTO to Clusterify batchUpsert payload format.
     *
     * @return array<string, mixed>
     */
    public function toBatchUpsertPayload(): array
    {
        return [
            'url' => $this->url,
            'content' => $this->content,
            'is_enabled' => $this->isEnabled,
        ];
    }
}
