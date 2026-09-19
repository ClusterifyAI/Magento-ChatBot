<?php
/**
 * ClusterifyAI ChatBot product form modifier for knowledge attribute
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Ui\DataProvider\Product\Form\Modifier;

use ClusterifyAI\ChatBot\Block\Adminhtml\Product\Helper\KnowledgeNotice;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier;
use Magento\Framework\Stdlib\ArrayManager;
use Magento\Framework\View\LayoutInterface;

/**
 * Class KnowledgeAttributeNotice
 *
 * Injects training instructions notice and 20k characters validation into the Product Edit form.
 */
class KnowledgeAttributeNotice extends AbstractModifier
{
    public const ATTRIBUTE_CODE = 'clusterify_chatbot_knowledge';
    public const NOTICE_CONTAINER_NAME = 'clusterify_chatbot_knowledge_notice';

    /**
     * @param ArrayManager    $arrayManager Array manager
     * @param LayoutInterface $layout       Layout service
     */
    public function __construct(
        private readonly ArrayManager $arrayManager,
        private readonly LayoutInterface $layout
    ) {}

    /**
     * Modify product form data.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function modifyData(array $data): array
    {
        return $data;
    }

    /**
     * Modify product form metadata.
     *
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    public function modifyMeta(array $meta): array
    {
        $fieldPath = $this->arrayManager->findPath(self::ATTRIBUTE_CODE, $meta, null, 'children');

        if (!$fieldPath) {
            return $meta;
        }

        // Configure 20,000 max character validation and comfortable textarea rows
        $meta = $this->arrayManager->merge($fieldPath . '/arguments/data/config', $meta, [
            'validation' => [
                'max_text_length' => 20000,
            ],
            'rows' => 12,
        ]);

        // Locate parent container and group level children
        $containerPath = $this->arrayManager->slicePath($fieldPath, 0, -2);
        if (str_contains($containerPath, 'container_')) {
            $groupChildrenPath = $this->arrayManager->slicePath($fieldPath, 0, -3);
            // Ensure field container has higher sortOrder so it renders AFTER the notice
            $meta = $this->arrayManager->merge($containerPath . '/arguments/data/config', $meta, [
                'sortOrder' => 20,
            ]);
        } else {
            $groupChildrenPath = $this->arrayManager->slicePath($fieldPath, 0, -1);
            $meta = $this->arrayManager->merge($fieldPath . '/arguments/data/config', $meta, [
                'sortOrder' => 20,
            ]);
        }

        $noticePath = $groupChildrenPath . '/' . self::NOTICE_CONTAINER_NAME;

        if (!$this->arrayManager->exists($noticePath, $meta)) {
            /** @var KnowledgeNotice $noticeBlock */
            $noticeBlock = $this->layout->createBlock(KnowledgeNotice::class);
            $noticeHtml = $noticeBlock->toHtml();

            $noticeMeta = [
                'arguments' => [
                    'data' => [
                        'config' => [
                            'componentType' => 'container',
                            'component' => 'Magento_Ui/js/form/components/html',
                            'additionalClasses' => 'admin__fieldset-note-wrap',
                            'content' => $noticeHtml,
                            'sortOrder' => 10,
                        ],
                    ],
                ],
            ];

            $meta = $this->arrayManager->set($noticePath, $meta, $noticeMeta);
        }

        return $meta;
    }
}
