<?php
/**
 * ClusterifyAI ChatBot provider pool unit test
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Test\Unit\Model\Sync;

use ClusterifyAI\ChatBot\Model\Sync\DataProviderInterface;
use ClusterifyAI\ChatBot\Model\Sync\ProviderPool;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Class ProviderPoolTest
 *
 * Unit tests for ClusterifyAI\ChatBot\Model\Sync\ProviderPool registry.
 */
class ProviderPoolTest extends TestCase
{
    /**
     * Test successful retrieval of registered provider.
     *
     * @return void
     */
    public function testGetProviderReturnsMatchingProvider(): void
    {
        $cmsProviderStub = $this->createStub(DataProviderInterface::class);
        $cmsProviderStub->method('getEntityType')->willReturn('cms');

        $pool = new ProviderPool(['cms' => $cmsProviderStub]);

        $this->assertTrue($pool->hasProvider('cms'));
        $this->assertSame($cmsProviderStub, $pool->getProvider('cms'));
    }

    /**
     * Test exception thrown on unregistered entity type.
     *
     * @return void
     */
    public function testGetProviderThrowsExceptionOnUnknownEntity(): void
    {
        $pool = new ProviderPool([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No sync data provider registered for entity type "unknown"');

        $pool->getProvider('unknown');
    }
}
