<?php
/**
 * ClusterifyAI ChatBot category data provider unit test
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Test\Unit\Model\Sync\Provider;

use ClusterifyAI\ChatBot\Model\Config;
use ClusterifyAI\ChatBot\Model\Sync\HtmlToMarkdown;
use ClusterifyAI\ChatBot\Model\Sync\Provider\CategoryDataProvider;
use ClusterifyAI\ChatBot\Model\Sync\SyncItem;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Class CategoryDataProviderTest
 *
 * Unit tests for ClusterifyAI\ChatBot\Model\Sync\Provider\CategoryDataProvider.
 */
class CategoryDataProviderTest extends TestCase
{
    private CategoryRepositoryInterface $categoryRepositoryStub;
    private StoreManagerInterface $storeManagerStub;
    private HtmlToMarkdown $htmlConverter;
    private Config $configStub;
    private LoggerInterface $loggerStub;
    private CategoryDataProvider $provider;

    /**
     * Set up test instances.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->categoryRepositoryStub = $this->createStub(CategoryRepositoryInterface::class);
        $this->htmlConverter = new HtmlToMarkdown();
        $this->configStub = $this->createStub(Config::class);
        $this->loggerStub = $this->createStub(LoggerInterface::class);

        $this->provider = new CategoryDataProvider(
            $this->categoryRepositoryStub,
            $this->htmlConverter,
            $this->configStub,
            $this->loggerStub
        );
    }

    /**
     * Test root category (level <= 1) returns null.
     *
     * @return void
     */
    public function testExtractReturnsNullForRootCategory(): void
    {
        $categoryStub = $this->createStub(Category::class);
        $categoryStub->method('getLevel')->willReturn(1);

        $this->categoryRepositoryStub->method('get')->willReturn($categoryStub);

        $result = $this->provider->extract(2, 1);
        $this->assertNull($result);
    }

    /**
     * Test inactive category emits ACTION_DELETE.
     *
     * @return void
     */
    public function testExtractInactiveCategoryEmitsDelete(): void
    {
        $categoryStub = $this->createStub(Category::class);
        $categoryStub->method('getLevel')->willReturn(2);
        $categoryStub->method('getUrl')->willReturn('http://magento.test/gear.html');
        $categoryStub->method('getIsActive')->willReturn(false);

        $this->categoryRepositoryStub->method('get')->willReturn($categoryStub);

        $result = $this->provider->extract(3, 1);
        $this->assertInstanceOf(SyncItem::class, $result);
        $this->assertSame(SyncItem::ACTION_DELETE, $result->action);
        $this->assertFalse($result->isEnabled);
    }

    /**
     * Test active category emits ACTION_UPSERT with structured Markdown.
     *
     * @return void
     */
    public function testExtractActiveCategoryEmitsUpsert(): void
    {
        $categoryStub = $this->createStub(Category::class);
        $categoryStub->method('getLevel')->willReturn(2);
        $categoryStub->method('getName')->willReturn('Gear');
        $categoryStub->method('getUrl')->willReturn('http://magento.test/gear.html');
        $categoryStub->method('getIsActive')->willReturn(true);
        $categoryStub->method('getData')->willReturnCallback(function (string $key) {
            return $key === 'description' ? '<p>Explore our premium fitness gear.</p>' : null;
        });
        $categoryStub->method('getChildrenCategories')->willReturn([]);

        $this->categoryRepositoryStub->method('get')->willReturn($categoryStub);

        $result = $this->provider->extract(3, 1);
        $this->assertInstanceOf(SyncItem::class, $result);
        $this->assertSame(SyncItem::ACTION_UPSERT, $result->action);
        $this->assertSame('http://magento.test/gear.html', $result->url);
        $this->assertStringContainsString('# Gear', $result->content);
        $this->assertStringContainsString('Explore our premium fitness gear.', $result->content);
    }

    /**
     * Test active category with custom knowledge attribute appends AI context section.
     *
     * @return void
     */
    public function testExtractActiveCategoryIncludesCustomKnowledge(): void
    {
        $categoryStub = $this->createStub(Category::class);
        $categoryStub->method('getLevel')->willReturn(2);
        $categoryStub->method('getName')->willReturn('Fitness Equipment');
        $categoryStub->method('getUrl')->willReturn('http://magento.test/gear/fitness-equipment.html');
        $categoryStub->method('getIsActive')->willReturn(true);
        $categoryStub->method('getData')->willReturnCallback(function (string $key) {
            return match ($key) {
                'description' => '<p>Home fitness equipment.</p>',
                'clusterify_chatbot_knowledge' => "Buyer guide: Ideal for home gyms.\nUse case: Resistance bands for recovery.",
                default => null,
            };
        });
        $categoryStub->method('getChildrenCategories')->willReturn([]);

        $this->categoryRepositoryStub->method('get')->willReturn($categoryStub);

        $result = $this->provider->extract(5, 1);
        $this->assertInstanceOf(SyncItem::class, $result);
        $this->assertStringContainsString('## AI Knowledge & Context', $result->content);
        $this->assertStringContainsString('Buyer guide: Ideal for home gyms.', $result->content);
        $this->assertStringContainsString('Use case: Resistance bands for recovery.', $result->content);
    }

    /**
     * Test extract returns null and logs warning when repository throws an exception.
     *
     * @return void
     */
    public function testExtractReturnsNullAndLogsWarningOnThrowable(): void
    {
        $this->categoryRepositoryStub->method('get')->willThrowException(new \RuntimeException('Category DB error'));

        $loggerMock = $this->createMock(\Psr\Log\LoggerInterface::class);
        $loggerMock->expects($this->once())->method('warning');

        $provider = new CategoryDataProvider(
            $this->categoryRepositoryStub,
            $this->htmlConverter,
            $this->configStub,
            $loggerMock
        );

        $result = $provider->extract(99, 1);
        $this->assertNull($result);
    }
}
