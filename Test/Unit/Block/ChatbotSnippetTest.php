<?php
/**
 * ClusterifyAI ChatBot storefront snippet block unit test
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Test\Unit\Block;

use ClusterifyAI\ChatBot\Block\ChatbotSnippet;
use ClusterifyAI\ChatBot\Model\Config;
use ClusterifyAI\ChatBot\Service\PageVisibility;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\View\Layout\ProcessorInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class ChatbotSnippetTest
 *
 * Unit tests for ClusterifyAI\ChatBot\Block\ChatbotSnippet.
 */
#[AllowMockObjectsWithoutExpectations]
class ChatbotSnippetTest extends TestCase
{
    private Context $contextStub;
    private HttpRequest|MockObject $requestMock;
    private Config|MockObject $configMock;
    private PageVisibility|MockObject $pageVisibilityMock;
    private StoreManagerInterface|MockObject $storeManagerMock;
    private StoreInterface $storeStub;
    private LayoutInterface $layoutStub;
    private ProcessorInterface $updateStub;
    private ChatbotSnippet $block;

    /**
     * Set up test instances.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->contextStub = $this->createStub(Context::class);
        $this->requestMock = $this->createMock(HttpRequest::class);
        $this->configMock = $this->createMock(Config::class);
        $this->pageVisibilityMock = $this->createMock(PageVisibility::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->storeStub = $this->createStub(StoreInterface::class);
        $this->layoutStub = $this->createStub(LayoutInterface::class);
        $this->updateStub = $this->createStub(ProcessorInterface::class);

        $this->storeStub->method('getId')->willReturn(1);
        $this->storeManagerMock->method('getStore')->willReturn($this->storeStub);
        $this->layoutStub->method('getUpdate')->willReturn($this->updateStub);

        $this->contextStub->method('getStoreManager')->willReturn($this->storeManagerMock);
        $this->contextStub->method('getRequest')->willReturn($this->requestMock);
        $this->contextStub->method('getLayout')->willReturn($this->layoutStub);

        $this->block = new ChatbotSnippet(
            $this->contextStub,
            $this->configMock,
            $this->pageVisibilityMock
        );
    }

    /**
     * Test getHostSystem delegates to Config model.
     *
     * @return void
     */
    public function testGetHostSystem(): void
    {
        $this->configMock->expects($this->once())
            ->method('getHostSystem')
            ->willReturn('magento2');

        $this->assertSame('magento2', $this->block->getHostSystem());
    }

    /**
     * Test getPublicUuid delegates to Config model with store ID.
     *
     * @return void
     */
    public function testGetPublicUuid(): void
    {
        $this->configMock->expects($this->once())
            ->method('getPublicUuid')
            ->with(1, ScopeInterface::SCOPE_STORE)
            ->willReturn('test-uuid-1234');

        $this->assertSame('test-uuid-1234', $this->block->getPublicUuid());
    }

    /**
     * Test getBundleScriptUrl delegates to Config model.
     *
     * @return void
     */
    public function testGetBundleScriptUrl(): void
    {
        $this->configMock->expects($this->once())
            ->method('getBundleScriptUrl')
            ->willReturn(Config::DEFAULT_BUNDLE_SCRIPT_URL);

        $this->assertSame(Config::DEFAULT_BUNDLE_SCRIPT_URL, $this->block->getBundleScriptUrl());
    }

    /**
     * Test canShowChatbot returns false when disabled.
     *
     * @return void
     */
    public function testCanShowChatbotReturnsFalseWhenDisabled(): void
    {
        $this->configMock->method('isEnabled')->willReturn(false);

        $this->assertFalse($this->block->canShowChatbot());
    }

    /**
     * Test canShowChatbot returns true when all conditions pass.
     *
     * @return void
     */
    public function testCanShowChatbotReturnsTrueWhenAllowed(): void
    {
        $this->configMock->method('isEnabled')->willReturn(true);
        $this->configMock->method('isShowOnStorefront')->willReturn(true);
        $this->configMock->method('getPublicUuid')->willReturn('valid-uuid');

        $this->requestMock->method('getFullActionName')->willReturn('cms_index_index');
        $this->updateStub->method('getHandles')->willReturn(['default', 'cms_index_index']);

        $this->pageVisibilityMock->expects($this->once())
            ->method('isPageAllowed')
            ->with('cms_index_index', 1, ScopeInterface::SCOPE_STORE, ['default', 'cms_index_index'])
            ->willReturn(true);

        $this->assertTrue($this->block->canShowChatbot());
    }
}
