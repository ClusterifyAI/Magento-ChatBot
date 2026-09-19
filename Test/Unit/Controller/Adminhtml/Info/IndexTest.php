<?php
/**
 * ClusterifyAI ChatBot admin info index controller unit test
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Test\Unit\Controller\Adminhtml\Info;

use ClusterifyAI\ChatBot\Controller\Adminhtml\Info\Index;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page as BackendPage;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Framework\View\Page\Title;
use Magento\Framework\View\Result\PageFactory;
use PHPUnit\Framework\TestCase;

/**
 * Class IndexTest
 *
 * Unit tests for ClusterifyAI\ChatBot\Controller\Adminhtml\Info\Index.
 */
class IndexTest extends TestCase
{
    /**
     * Test execute configures page title and active menu.
     *
     * @return void
     */
    public function testExecuteConfiguresPageTitleAndMenu(): void
    {
        $titleMock = $this->createMock(Title::class);
        $titleMock->expects($this->once())
            ->method('prepend')
            ->with($this->isInstanceOf(\Magento\Framework\Phrase::class));

        $pageConfigStub = $this->createStub(PageConfig::class);
        $pageConfigStub->method('getTitle')->willReturn($titleMock);

        $resultPageMock = $this->createMock(BackendPage::class);
        $resultPageMock->expects($this->once())
            ->method('setActiveMenu')
            ->with('ClusterifyAI_ChatBot::clusterify_root')
            ->willReturnSelf();
        $resultPageMock->method('getConfig')->willReturn($pageConfigStub);

        $pageFactoryStub = $this->createStub(PageFactory::class);
        $pageFactoryStub->method('create')->willReturn($resultPageMock);

        $controller = new Index(
            $this->createStub(Context::class),
            $pageFactoryStub
        );

        $result = $controller->execute();
        $this->assertSame($resultPageMock, $result);
    }
}
