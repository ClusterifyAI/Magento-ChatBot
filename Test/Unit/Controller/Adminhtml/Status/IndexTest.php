<?php
/**
 * ClusterifyAI ChatBot admin status index controller unit test
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Test\Unit\Controller\Adminhtml\Status;

use ClusterifyAI\ChatBot\Controller\Adminhtml\Status\Index;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page as BackendPage;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Framework\View\Page\Title;
use Magento\Framework\View\Result\PageFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class IndexTest
 *
 * Unit tests for ClusterifyAI\ChatBot\Controller\Adminhtml\Status\Index.
 */
#[AllowMockObjectsWithoutExpectations]
class IndexTest extends TestCase
{
    private Context $contextStub;
    private PageFactory|MockObject $resultPageFactoryMock;
    private BackendPage|MockObject $resultPageMock;
    private PageConfig|MockObject $pageConfigMock;
    private Title|MockObject $titleMock;
    private Index $controller;

    /**
     * Set up test instances.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->contextStub = $this->createStub(Context::class);
        $this->resultPageFactoryMock = $this->createMock(PageFactory::class);
        $this->resultPageMock = $this->createMock(BackendPage::class);
        $this->pageConfigMock = $this->createMock(PageConfig::class);
        $this->titleMock = $this->createMock(Title::class);

        $this->pageConfigMock->method('getTitle')->willReturn($this->titleMock);
        $this->resultPageMock->method('getConfig')->willReturn($this->pageConfigMock);
        $this->resultPageFactoryMock->method('create')->willReturn($this->resultPageMock);

        $this->controller = new Index(
            $this->contextStub,
            $this->resultPageFactoryMock
        );
    }

    /**
     * Test execute initializes page title, menu, and returns result page.
     *
     * @return void
     */
    public function testExecuteInitializesStatusPage(): void
    {
        $this->resultPageMock->expects($this->once())
            ->method('setActiveMenu')
            ->with(Index::ACTIVE_MENU);

        $this->titleMock->expects($this->once())
            ->method('prepend');

        $result = $this->controller->execute();
        $this->assertSame($this->resultPageMock, $result);
    }

    /**
     * Test admin resource authorization constant.
     *
     * @return void
     */
    public function testAdminResourceConstant(): void
    {
        $this->assertSame('ClusterifyAI_ChatBot::status', Index::ADMIN_RESOURCE);
    }
}
