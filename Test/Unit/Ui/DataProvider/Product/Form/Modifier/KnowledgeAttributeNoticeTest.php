<?php
/**
 * ClusterifyAI ChatBot product form modifier unit test
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Test\Unit\Ui\DataProvider\Product\Form\Modifier;

use ClusterifyAI\ChatBot\Block\Adminhtml\Product\Helper\KnowledgeNotice;
use ClusterifyAI\ChatBot\Ui\DataProvider\Product\Form\Modifier\KnowledgeAttributeNotice;
use Magento\Framework\Stdlib\ArrayManager;
use Magento\Framework\View\LayoutInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class KnowledgeAttributeNoticeTest
 *
 * Tests the product form modifier that configures validation and guidance notices for the knowledge attribute.
 */
class KnowledgeAttributeNoticeTest extends TestCase
{
    private ArrayManager $arrayManager;
    private LayoutInterface $layoutStub;
    private KnowledgeAttributeNotice $modifier;

    /**
     * Set up dependencies.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->arrayManager = new ArrayManager();
        $this->layoutStub = $this->createStub(LayoutInterface::class);

        $this->modifier = new KnowledgeAttributeNotice(
            $this->arrayManager,
            $this->layoutStub
        );
    }

    /**
     * Test modifyData returns input data unmodified.
     *
     * @return void
     */
    public function testModifyDataReturnsInputUnmodified(): void
    {
        $data = ['1' => ['product' => ['name' => 'Sample Product']]];
        $this->assertSame($data, $this->modifier->modifyData($data));
    }

    /**
     * Test modifyMeta returns unchanged meta when knowledge attribute is not present.
     *
     * @return void
     */
    public function testModifyMetaReturnsUnchangedWhenAttributeNotPresent(): void
    {
        $meta = [
            'general' => [
                'children' => [
                    'name' => ['arguments' => ['data' => ['config' => []]]],
                ],
            ],
        ];

        $result = $this->modifier->modifyMeta($meta);
        $this->assertSame($meta, $result);
    }

    /**
     * Test modifyMeta injects validation and notice when knowledge attribute is present.
     *
     * @return void
     */
    public function testModifyMetaInjectsValidationAndNotice(): void
    {
        $layoutMock = $this->createMock(LayoutInterface::class);
        $modifier = new KnowledgeAttributeNotice($this->arrayManager, $layoutMock);

        $noticeBlockStub = $this->createStub(KnowledgeNotice::class);
        $noticeBlockStub->method('toHtml')->willReturn('<div class="test-notice">Guide</div>');

        $layoutMock->expects($this->once())
            ->method('createBlock')
            ->with(KnowledgeNotice::class)
            ->willReturn($noticeBlockStub);

        $meta = [
            'clusterify-ai-chatbot' => [
                'children' => [
                    'clusterify_chatbot_knowledge' => [
                        'arguments' => [
                            'data' => [
                                'config' => [
                                    'rows' => 4,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $modifier->modifyMeta($meta);

        // Verify rows and max_text_length validation
        $config = $result['clusterify-ai-chatbot']['children']['clusterify_chatbot_knowledge']['arguments']['data']['config'];
        $this->assertSame(12, $config['rows']);
        $this->assertSame(20000, $config['validation']['max_text_length']);

        // Verify notice container injection
        $noticeContainer = $result['clusterify-ai-chatbot']['children']['clusterify_chatbot_knowledge_notice'];
        $this->assertNotNull($noticeContainer);
        $noticeConfig = $noticeContainer['arguments']['data']['config'];
        $this->assertSame('container', $noticeConfig['componentType']);
        $this->assertSame('Magento_Ui/js/form/components/html', $noticeConfig['component']);
        $this->assertSame('<div class="test-notice">Guide</div>', $noticeConfig['content']);
        $this->assertSame(10, $noticeConfig['sortOrder']);
    }
}
