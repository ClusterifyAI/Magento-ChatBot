<?php
/**
 * ClusterifyAI ChatBot category page data provider
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Model\Sync\Provider;

use ClusterifyAI\ChatBot\Model\Config;
use ClusterifyAI\ChatBot\Model\Sync\DataProviderInterface;
use ClusterifyAI\ChatBot\Model\Sync\HtmlToMarkdown;
use ClusterifyAI\ChatBot\Model\Sync\SyncItem;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Psr\Log\LoggerInterface;

/**
 * Class CategoryDataProvider
 *
 * Extracts category hierarchy, descriptions, and metadata into structured Markdown knowledge.
 */
class CategoryDataProvider implements DataProviderInterface
{
    public const ENTITY_TYPE = 'category';

    /**
     * @param CategoryRepositoryInterface $categoryRepository Category repository
     * @param HtmlToMarkdown              $htmlConverter      HTML to Markdown converter
     * @param Config                      $config             Configuration service
     * @param LoggerInterface             $logger             Logger
     */
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly HtmlToMarkdown $htmlConverter,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * @inheritdoc
     */
    public function getEntityType(): string
    {
        return self::ENTITY_TYPE;
    }

    /**
     * @inheritdoc
     */
    public function extract(int $entityId, int $storeId): ?SyncItem
    {
        try {
            /** @var Category $category */
            $category = $this->categoryRepository->get($entityId, $storeId);

            // Skip root categories without public catalog representation (level <= 1)
            if ((int) $category->getLevel() <= 1) {
                return null;
            }

            $url = (string) $category->getUrl();
            $url = strtok($url, '?');
            $url = trim(strtok((string) $url, '#'));
            if ($url === '') {
                return null;
            }

            // If category is disabled, issue delete action to purge URL from Clusterify
            if (!(bool) $category->getIsActive()) {
                return new SyncItem(
                    entityType: self::ENTITY_TYPE,
                    entityId: $entityId,
                    storeId: $storeId,
                    url: $url,
                    content: '',
                    isEnabled: false,
                    action: SyncItem::ACTION_DELETE
                );
            }

            $name = trim((string) $category->getName());
            $rawDescription = (string) ($category->getData('description') ?? '');
            $markdownDescription = $this->htmlConverter->convert($rawDescription);

            // Extract child subcategories
            $subcategories = [];
            $children = $category->getChildrenCategories();
            foreach ($children as $child) {
                if ($child->getIsActive()) {
                    $childName = trim((string) $child->getName());
                    if ($childName !== '') {
                        $subcategories[] = $childName;
                    }
                }
            }

            $markdown = sprintf("# %s\n", $name);

            if (!empty($subcategories)) {
                $markdown .= sprintf("- **Subcategories**: %s\n", implode(', ', $subcategories));
            }

            // Custom AI training knowledge attribute
            $customKnowledge = trim((string) ($category->getData('clusterify_chatbot_knowledge') ?? ''));
            if ($customKnowledge !== '' && mb_strlen($customKnowledge) > 20000) {
                $customKnowledge = mb_substr($customKnowledge, 0, 20000);
            }

            $prioritizeCustomKnowledge = $this->config->isCustomKnowledgeOnlySyncEnabled($storeId);

            if ($prioritizeCustomKnowledge && $customKnowledge !== '') {
                // When custom knowledge is present and prioritized, sync it instead of core description
                $markdown .= sprintf("\n## AI Knowledge & Context\n%s\n", $customKnowledge);
            } else {
                // Standard category description (and fallback if custom knowledge is empty)
                if ($markdownDescription !== '') {
                    $markdown .= sprintf("\n## Overview\n%s\n", $markdownDescription);
                }

                if (!$prioritizeCustomKnowledge && $customKnowledge !== '') {
                    $markdown .= sprintf("\n## AI Knowledge & Context\n%s\n", $customKnowledge);
                }
            }

            return new SyncItem(
                entityType: self::ENTITY_TYPE,
                entityId: $entityId,
                storeId: $storeId,
                url: $url,
                content: trim($markdown),
                isEnabled: true,
                action: SyncItem::ACTION_UPSERT
            );
        } catch (\Throwable $e) {
            $this->logger->warning(sprintf(
                'Clusterify CategoryDataProvider failed to extract category ID %d for store ID %d: %s',
                $entityId,
                $storeId,
                $e->getMessage()
            ));
            return null;
        }
    }

    /**
     * @inheritdoc
     */
    public function extractBatch(array $entityIds, int $storeId): array
    {
        $items = [];
        foreach ($entityIds as $id) {
            $item = $this->extract((int) $id, $storeId);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }
}
