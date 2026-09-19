<?php
/**
 * ClusterifyAI ChatBot product action plugin unit test
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Test\Unit\Plugin\Catalog;

use ClusterifyAI\ChatBot\Model\Queue\Publisher;
use ClusterifyAI\ChatBot\Plugin\Catalog\ProductActionPlugin;
use ClusterifyAI\ChatBot\Service\PlanService;
use Magento\Catalog\Model\Product\Action as ProductAction;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Class ProductActionPluginTest
 *
 * Tests the product mass attribute update interceptor.
 */
class ProductActionPluginTest extends TestCase
{
    /**
     * Test afterUpdateAttributes returns result immediately when productIds is empty.
     *
     * @return void
     */
    public function testAfterUpdateAttributesReturnsEarlyWhenEmpty(): void
    {
        $publisherMock = $this->createMock(Publisher::class);
        $publisherMock->expects($this->never())->method('publishBatch');

        $plugin = new ProductActionPlugin(
            $publisherMock,
            $this->createStub(PlanService::class),
            $this->createStub(StoreManagerInterface::class),
            $this->createStub(LoggerInterface::class)
        );

        $actionStub = $this->createStub(ProductAction::class);
        $result = $plugin->afterUpdateAttributes($actionStub, $actionStub, [], [], 0);

        $this->assertSame($actionStub, $result);
    }

    /**
     * Test afterUpdateAttributes dispatches batch for specific store view.
     *
     * @return void
     */
    public function testAfterUpdateAttributesDispatchesForSpecificStore(): void
    {
        $storeStub = $this->createStub(StoreInterface::class);
        $storeStub->method('getId')->willReturn(1);

        $storeManagerStub = $this->createStub(StoreManagerInterface::class);
        $storeManagerStub->method('getStore')->willReturn($storeStub);

        $planServiceStub = $this->createStub(PlanService::class);
        $planServiceStub->method('canSyncEntity')->willReturn(true);

        $publisherMock = $this->createMock(Publisher::class);
        $publisherMock->expects($this->once())
            ->method('publishBatch')
            ->with('product', [10, 20], 1);

        $plugin = new ProductActionPlugin(
            $publisherMock,
            $planServiceStub,
            $storeManagerStub,
            $this->createStub(LoggerInterface::class)
        );

        $actionStub = $this->createStub(ProductAction::class);
        $result = $plugin->afterUpdateAttributes($actionStub, $actionStub, [10, 0, -2, 20, 10], ['price' => 50], 1);

        $this->assertSame($actionStub, $result);
    }

    /**
     * Test afterUpdateAttributes returns early when all productIds are non-positive.
     *
     * @return void
     */
    public function testAfterUpdateAttributesReturnsEarlyWhenOnlyNonPositiveIds(): void
    {
        $publisherMock = $this->createMock(Publisher::class);
        $publisherMock->expects($this->never())->method('publishBatch');

        $plugin = new ProductActionPlugin(
            $publisherMock,
            $this->createStub(PlanService::class),
            $this->createStub(StoreManagerInterface::class),
            $this->createStub(LoggerInterface::class)
        );

        $actionStub = $this->createStub(ProductAction::class);
        $result = $plugin->afterUpdateAttributes($actionStub, $actionStub, [0, -1, -5], ['status' => 1], 1);

        $this->assertSame($actionStub, $result);
    }
}
