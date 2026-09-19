<?php
/**
 * ClusterifyAI ChatBot knowledge attributes data patch
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Setup\Patch\Data;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Class AddChatbotKnowledgeAttributes
 *
 * Installs custom EAV attribute 'clusterify_chatbot_knowledge' for products and categories,
 * placing it inside a dedicated 'Clusterify AI ChatBot' attribute group across all attribute sets.
 */
class AddChatbotKnowledgeAttributes implements DataPatchInterface
{
    public const ATTRIBUTE_CODE = 'clusterify_chatbot_knowledge';
    public const ATTRIBUTE_GROUP_NAME = 'Clusterify AI ChatBot';

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup Setup interface
     * @param EavSetupFactory          $eavSetupFactory EAV setup factory
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory
    ) {}

    /**
     * Apply data patch.
     *
     * @return $this
     */
    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        // 1. Add Product Attribute
        $eavSetup->addAttribute(
            Product::ENTITY,
            self::ATTRIBUTE_CODE,
            [
                'type' => 'text',
                'label' => 'ChatBot Knowledge & AI Context',
                'input' => 'textarea',
                'required' => false,
                'sort_order' => 10,
                'global' => ScopedAttributeInterface::SCOPE_STORE,
                'visible' => true,
                'user_defined' => true,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => false,
                'unique' => false,
                'apply_to' => '',
                'group' => self::ATTRIBUTE_GROUP_NAME,
                'note' => 'Dedicated AI context synchronized to Clusterify.AI (max 20,000 characters). Used to train the assistant on product highlights, buyer recommendations, sizing/fit advice, and sales arguments.',
            ]
        );

        // Assign to 'Clusterify AI ChatBot' attribute group across ALL product attribute sets
        $productEntityTypeId = $eavSetup->getEntityTypeId(Product::ENTITY);
        $productAttributeSetIds = $eavSetup->getAllAttributeSetIds($productEntityTypeId);

        foreach ($productAttributeSetIds as $attributeSetId) {
            $groupId = $eavSetup->getAttributeGroupId($productEntityTypeId, (int) $attributeSetId, self::ATTRIBUTE_GROUP_NAME);
            if (!$groupId) {
                $eavSetup->addAttributeGroup($productEntityTypeId, (int) $attributeSetId, self::ATTRIBUTE_GROUP_NAME, 55);
            }
            $eavSetup->addAttributeToGroup(
                $productEntityTypeId,
                (int) $attributeSetId,
                self::ATTRIBUTE_GROUP_NAME,
                self::ATTRIBUTE_CODE,
                10
            );
        }

        // 2. Add Category Attribute
        $eavSetup->addAttribute(
            Category::ENTITY,
            self::ATTRIBUTE_CODE,
            [
                'type' => 'text',
                'label' => 'ChatBot Knowledge & AI Context',
                'input' => 'textarea',
                'required' => false,
                'sort_order' => 10,
                'global' => ScopedAttributeInterface::SCOPE_STORE,
                'visible' => true,
                'user_defined' => true,
                'group' => self::ATTRIBUTE_GROUP_NAME,
                'note' => 'Dedicated category-level AI context synchronized to Clusterify.AI (max 20,000 characters). Used to train the assistant on category overviews, buyer selection advice, common use cases, and cross-category navigation.',
            ]
        );

        $categoryEntityTypeId = $eavSetup->getEntityTypeId(Category::ENTITY);
        $categoryAttributeSetIds = $eavSetup->getAllAttributeSetIds($categoryEntityTypeId);

        foreach ($categoryAttributeSetIds as $catSetId) {
            $catGroupId = $eavSetup->getAttributeGroupId($categoryEntityTypeId, (int) $catSetId, self::ATTRIBUTE_GROUP_NAME);
            if (!$catGroupId) {
                $eavSetup->addAttributeGroup($categoryEntityTypeId, (int) $catSetId, self::ATTRIBUTE_GROUP_NAME, 55);
            }
            $eavSetup->addAttributeToGroup(
                $categoryEntityTypeId,
                (int) $catSetId,
                self::ATTRIBUTE_GROUP_NAME,
                self::ATTRIBUTE_CODE,
                10
            );
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    /**
     * Retrieve aliases.
     *
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * Retrieve dependencies.
     *
     * @return string[]
     */
    public static function getDependencies(): array
    {
        return [];
    }
}
