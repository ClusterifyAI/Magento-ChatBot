<?php
/**
 * ClusterifyAI ChatBot admin info guide block unit test
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Test\Unit\Block\Adminhtml\Info;

use ClusterifyAI\ChatBot\Block\Adminhtml\Info\Guide;
use ClusterifyAI\ChatBot\Model\Config;
use Magento\Backend\Block\Template\Context;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class GuideTest
 *
 * Unit tests for ClusterifyAI\ChatBot\Block\Adminhtml\Info\Guide.
 */
class GuideTest extends TestCase
{
    private Config $configStub;
    private Context $contextStub;
    private JsonHelper $jsonHelperStub;
    private DirectoryHelper $directoryHelperStub;
    private Guide $block;

    /**
     * Set up test dependencies.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->configStub = $this->createStub(Config::class);
        $this->contextStub = $this->createStub(Context::class);
        $this->jsonHelperStub = $this->createStub(JsonHelper::class);
        $this->directoryHelperStub = $this->createStub(DirectoryHelper::class);

        $this->block = new Guide(
            $this->contextStub,
            $this->configStub,
            [],
            $this->jsonHelperStub,
            $this->directoryHelperStub
        );
    }

    /**
     * Test getExtensionVersion returns version from config.
     *
     * @return void
     */
    public function testGetExtensionVersion(): void
    {
        $this->configStub->method('getExtensionVersion')->willReturn('1.0.0');
        $this->assertSame('1.0.0', $this->block->getExtensionVersion());
    }

    /**
     * Test portal URLs return expected remote links.
     *
     * @return void
     */
    public function testPortalUrlsReturnExpectedDestinations(): void
    {
        $this->assertSame(Guide::URL_DASHBOARD, $this->block->getDashboardUrl());
        $this->assertSame(Guide::URL_CHATBOT, $this->block->getChatbotUrl());
        $this->assertSame(Guide::URL_API_KEYS, $this->block->getApiKeysUrl());
        $this->assertSame(Guide::URL_BUBBLE_BUILDER, $this->block->getBubbleBuilderUrl());
        $this->assertSame(Guide::URL_CHATBOT_BUILDER, $this->block->getChatbotBuilderUrl());
        $this->assertSame(Guide::URL_CHATBOT_BEHAVING, $this->block->getChatbotBehavingUrl());
        $this->assertSame(Guide::URL_BILLING, $this->block->getBillingUrl());
    }

    /**
     * Test admin navigation URLs generate expected paths.
     *
     * @return void
     */
    public function testAdminNavigationUrls(): void
    {
        $urlBuilderMock = $this->createMock(UrlInterface::class);
        $urlBuilderMock->expects($this->exactly(3))
            ->method('getUrl')
            ->willReturnMap([
                ['adminhtml/system_config/edit', ['section' => 'clusterify_chatbot'], 'http://magento.test/admin/config'],
                ['clusterify_chatbot/status/index', [], 'http://magento.test/admin/status'],
                ['indexer/indexer/list', [], 'http://magento.test/admin/indexers'],
            ]);

        $contextStub = $this->createStub(Context::class);
        $contextStub->method('getUrlBuilder')->willReturn($urlBuilderMock);

        $block = new Guide(
            $contextStub,
            $this->configStub,
            [],
            $this->jsonHelperStub,
            $this->directoryHelperStub
        );

        $this->assertSame('http://magento.test/admin/config', $block->getConfigUrl());
        $this->assertSame('http://magento.test/admin/status', $block->getStatusUrl());
        $this->assertSame('http://magento.test/admin/indexers', $block->getIndexManagementUrl());
    }
}
