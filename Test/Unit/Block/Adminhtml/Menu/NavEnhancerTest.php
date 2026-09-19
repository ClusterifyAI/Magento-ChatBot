<?php
/**
 * ClusterifyAI ChatBot admin navigation enhancer block unit test
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Test\Unit\Block\Adminhtml\Menu;

use ClusterifyAI\ChatBot\Block\Adminhtml\Menu\NavEnhancer;
use ClusterifyAI\ChatBot\Model\Config;
use ClusterifyAI\ChatBot\Service\PlanService;
use Magento\Backend\Block\Template\Context;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Class NavEnhancerTest
 *
 * Unit tests for ClusterifyAI\ChatBot\Block\Adminhtml\Menu\NavEnhancer.
 */
#[AllowMockObjectsWithoutExpectations]
class NavEnhancerTest extends TestCase
{
    private Context $contextStub;
    private PlanService $planServiceStub;
    private Config $configStub;
    private NavEnhancer $enhancer;

    /**
     * Set up test instances.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->contextStub = $this->createStub(Context::class);
        $this->planServiceStub = $this->createStub(PlanService::class);
        $this->configStub = $this->createStub(Config::class);

        $jsonHelperStub = $this->createStub(JsonHelper::class);
        $directoryHelperStub = $this->createStub(DirectoryHelper::class);

        $this->enhancer = new NavEnhancer(
            $this->contextStub,
            $this->planServiceStub,
            $this->configStub,
            [],
            $jsonHelperStub,
            $directoryHelperStub
        );
    }

    /**
     * Test hasApiCredentials returns true when keys are populated.
     *
     * @return void
     */
    public function testHasApiCredentials(): void
    {
        $this->configStub->method('getPublicKey')->willReturn('pk_live_123');
        $this->configStub->method('getSecretKey')->willReturn('sk_live_456');

        $this->assertTrue($this->enhancer->hasApiCredentials());
    }

    /**
     * Test plan methods delegate to PlanService.
     *
     * @return void
     */
    public function testPlanDelegation(): void
    {
        $this->planServiceStub->method('getPlanName')->willReturn('PROFESSIONAL Plan');
        $this->planServiceStub->method('getPlanId')->willReturn(2);
        $this->planServiceStub->method('isUrlKnowledgeAllowed')->willReturn(true);

        $this->assertSame('PROFESSIONAL Plan', $this->enhancer->getPlanName());
        $this->assertSame(2, $this->enhancer->getPlanId());
        $this->assertTrue($this->enhancer->isUrlKnowledgeAllowed());
        $this->assertSame(NavEnhancer::BILLING_URL, $this->enhancer->getBillingUrl());
    }
}
