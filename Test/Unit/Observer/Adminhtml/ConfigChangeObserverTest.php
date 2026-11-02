<?php
/**
 * ClusterifyAI ChatBot admin configuration change observer unit test
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Test\Unit\Observer\Adminhtml;

use ClusterifyAI\ChatBot\Observer\Adminhtml\ConfigChangeObserver;
use ClusterifyAI\ChatBot\Service\PlanService;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Event\Observer;
use PHPUnit\Framework\TestCase;

/**
 * Class ConfigChangeObserverTest
 *
 * Tests that saving admin system configuration invalidates plan cache and block/page caches.
 */
class ConfigChangeObserverTest extends TestCase
{
    /**
     * Test execute clears plan cache and cleans block_html and full_page caches.
     *
     * @return void
     */
    public function testExecuteClearsPlanAndPageCaches(): void
    {
        $planServiceMock = $this->createMock(PlanService::class);
        $planServiceMock->expects($this->once())->method('clearPlanCache');

        $cleanedTypes = [];
        $cacheTypeListMock = $this->createMock(TypeListInterface::class);
        $cacheTypeListMock->expects($this->exactly(2))
            ->method('cleanType')
            ->willReturnCallback(function (string $type) use (&$cleanedTypes, $cacheTypeListMock) {
                $cleanedTypes[] = $type;
                return $cacheTypeListMock;
            });

        $observer = new ConfigChangeObserver($planServiceMock, $cacheTypeListMock);
        $eventObserverStub = $this->createStub(Observer::class);

        $observer->execute($eventObserverStub);

        $this->assertContains('block_html', $cleanedTypes);
        $this->assertContains('full_page', $cleanedTypes);
    }
}
