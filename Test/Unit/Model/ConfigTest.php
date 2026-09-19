<?php
/**
 * ClusterifyAI ChatBot configuration unit test
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Test\Unit\Model;

use ClusterifyAI\ChatBot\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class ConfigTest
 *
 * Unit tests for ClusterifyAI\ChatBot\Model\Config service.
 */
class ConfigTest extends TestCase
{
    /**
     * Test isEnabled method.
     *
     * @return void
     */
    public function testIsEnabled(): void
    {
        $scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $encryptorStub = $this->createStub(EncryptorInterface::class);
        $storeManagerStub = $this->createStub(StoreManagerInterface::class);
        $storeStub = $this->createStub(StoreInterface::class);

        $storeStub->method('getId')->willReturn(1);
        $storeManagerStub->method('getStore')->willReturn($storeStub);

        $scopeConfigMock->expects($this->once())
            ->method('isSetFlag')
            ->with(Config::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, 1)
            ->willReturn(true);

        $config = new Config($scopeConfigMock, $encryptorStub, $storeManagerStub);
        $this->assertTrue($config->isEnabled());
    }

    /**
     * Test isShowOnStorefront requires isEnabled to be true.
     *
     * @return void
     */
    public function testIsShowOnStorefrontReturnsFalseWhenDisabled(): void
    {
        $scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $encryptorStub = $this->createStub(EncryptorInterface::class);
        $storeManagerStub = $this->createStub(StoreManagerInterface::class);
        $storeStub = $this->createStub(StoreInterface::class);

        $storeStub->method('getId')->willReturn(1);
        $storeManagerStub->method('getStore')->willReturn($storeStub);

        $scopeConfigMock->expects($this->once())
            ->method('isSetFlag')
            ->with(Config::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, 1)
            ->willReturn(false);

        $config = new Config($scopeConfigMock, $encryptorStub, $storeManagerStub);
        $this->assertFalse($config->isShowOnStorefront());
    }

    /**
     * Test getPublicUuid returns trimmed string.
     *
     * @return void
     */
    public function testGetPublicUuid(): void
    {
        $uuid = '11111111-2222-3333-4444-555555555555';
        $scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $encryptorStub = $this->createStub(EncryptorInterface::class);
        $storeManagerStub = $this->createStub(StoreManagerInterface::class);
        $storeStub = $this->createStub(StoreInterface::class);

        $storeStub->method('getId')->willReturn(1);
        $storeManagerStub->method('getStore')->willReturn($storeStub);

        $scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_PUBLIC_UUID, ScopeInterface::SCOPE_STORE, 1)
            ->willReturn('  ' . $uuid . '  ');

        $config = new Config($scopeConfigMock, $encryptorStub, $storeManagerStub);
        $this->assertSame($uuid, $config->getPublicUuid());
    }

    /**
     * Test getSecretKey decrypts encrypted database value.
     *
     * @return void
     */
    public function testGetSecretKeyDecryptsValue(): void
    {
        $encrypted = 'enc_hash_123';
        $decrypted = 'sk_live_testsecretkey';

        $scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $encryptorMock = $this->createMock(EncryptorInterface::class);
        $storeManagerStub = $this->createStub(StoreManagerInterface::class);
        $storeStub = $this->createStub(StoreInterface::class);

        $storeStub->method('getId')->willReturn(1);
        $storeManagerStub->method('getStore')->willReturn($storeStub);

        $scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_SECRET_KEY, ScopeInterface::SCOPE_STORE, 1)
            ->willReturn($encrypted);

        $encryptorMock->expects($this->once())
            ->method('decrypt')
            ->with($encrypted)
            ->willReturn($decrypted);

        $config = new Config($scopeConfigMock, $encryptorMock, $storeManagerStub);
        $this->assertSame($decrypted, $config->getSecretKey());
    }

    /**
     * Test script snippet generation formats JSON correctly.
     *
     * @return void
     */
    public function testGenerateScriptSnippet(): void
    {
        $uuid = '11111111-2222-3333-4444-555555555555';
        $scopeConfigStub = $this->createStub(ScopeConfigInterface::class);
        $encryptorStub = $this->createStub(EncryptorInterface::class);
        $storeManagerStub = $this->createStub(StoreManagerInterface::class);

        $config = new Config($scopeConfigStub, $encryptorStub, $storeManagerStub);
        $snippet = $config->generateScriptSnippet($uuid);

        $this->assertStringContainsString('window.__clusterify.public_uuid = "11111111-2222-3333-4444-555555555555";', $snippet);
        $this->assertStringContainsString(Config::DEFAULT_BUNDLE_SCRIPT_URL, $snippet);
    }

    /**
     * Test isProductPriceSyncEnabled reads from scopeConfig.
     *
     * @return void
     */
    public function testIsProductPriceSyncEnabled(): void
    {
        $scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $scopeConfigMock->expects($this->once())
            ->method('isSetFlag')
            ->with(Config::XML_PATH_SYNC_PRODUCT_PRICE, ScopeInterface::SCOPE_STORE, 1)
            ->willReturn(true);

        $storeStub = $this->createStub(\Magento\Store\Api\Data\StoreInterface::class);
        $storeStub->method('getId')->willReturn(1);
        $storeManagerStub = $this->createStub(StoreManagerInterface::class);
        $storeManagerStub->method('getStore')->willReturn($storeStub);

        $config = new Config($scopeConfigMock, $this->createStub(EncryptorInterface::class), $storeManagerStub);
        $this->assertTrue($config->isProductPriceSyncEnabled(1));
    }

    /**
     * Test isProductAvailabilitySyncEnabled reads from scopeConfig.
     *
     * @return void
     */
    public function testIsProductAvailabilitySyncEnabled(): void
    {
        $scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $scopeConfigMock->expects($this->once())
            ->method('isSetFlag')
            ->with(Config::XML_PATH_SYNC_PRODUCT_AVAILABILITY, ScopeInterface::SCOPE_STORE, 1)
            ->willReturn(true);

        $storeStub = $this->createStub(\Magento\Store\Api\Data\StoreInterface::class);
        $storeStub->method('getId')->willReturn(1);
        $storeManagerStub = $this->createStub(StoreManagerInterface::class);
        $storeManagerStub->method('getStore')->willReturn($storeStub);

        $config = new Config($scopeConfigMock, $this->createStub(EncryptorInterface::class), $storeManagerStub);
        $this->assertTrue($config->isProductAvailabilitySyncEnabled(1));
    }

    /**
     * Test isCustomKnowledgeOnlySyncEnabled defaults to true when unconfigured.
     *
     * @return void
     */
    public function testIsCustomKnowledgeOnlySyncEnabledDefaultsToTrue(): void
    {
        $scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_SYNC_CUSTOM_KNOWLEDGE_ONLY, ScopeInterface::SCOPE_STORE, 1)
            ->willReturn(null);

        $storeStub = $this->createStub(\Magento\Store\Api\Data\StoreInterface::class);
        $storeStub->method('getId')->willReturn(1);
        $storeManagerStub = $this->createStub(StoreManagerInterface::class);
        $storeManagerStub->method('getStore')->willReturn($storeStub);

        $config = new Config($scopeConfigMock, $this->createStub(EncryptorInterface::class), $storeManagerStub);
        $this->assertTrue($config->isCustomKnowledgeOnlySyncEnabled(1));
    }
}
