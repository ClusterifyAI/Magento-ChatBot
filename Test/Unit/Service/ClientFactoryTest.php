<?php
/**
 * ClusterifyAI ChatBot client factory unit test
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Test\Unit\Service;

use ClusterifyAI\ChatBot\Model\Config;
use ClusterifyAI\ChatBot\Service\ClientFactory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Class ClientFactoryTest
 *
 * Unit tests for ClusterifyAI\ChatBot\Service\ClientFactory including SSRF and validation rules.
 */
class ClientFactoryTest extends TestCase
{
    private Config $configStub;
    private ClientFactory $factory;

    /**
     * Set up test instance.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->configStub = $this->createStub(Config::class);
        $this->factory = new ClientFactory($this->configStub);
    }

    /**
     * Test valid HTTPS URL passes validation.
     *
     * @return void
     */
    public function testValidateBaseUrlAcceptsHttps(): void
    {
        $url = 'https://api.clusterify.ai';
        $result = $this->factory->validateBaseUrl($url);
        $this->assertSame($url, $result);
    }

    /**
     * Test HTTP URL is rejected for remote hosts (enforcing HTTPS).
     *
     * @return void
     */
    public function testValidateBaseUrlRejectsHttpForRemoteHosts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('HTTPS is required for external Clusterify API endpoints');

        $this->factory->validateBaseUrl('http://api.remote-host.com');
    }

    /**
     * Test HTTP URL is accepted for localhost/loopback.
     *
     * @return void
     */
    public function testValidateBaseUrlAcceptsHttpForLocalhost(): void
    {
        $url = 'http://localhost:8000';
        $result = $this->factory->validateBaseUrl($url);
        $this->assertSame($url, $result);
    }

    /**
     * Test private IP ranges and link-local cloud metadata are rejected to prevent SSRF.
     *
     * @dataProvider privateIpDataProvider
     * @param string $url
     * @return void
     */
    #[DataProvider('privateIpDataProvider')]
    public function testValidateBaseUrlRejectsCloudMetadataAndPrivateIps(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('API Base URL cannot point to internal or private IP addresses.');

        $this->factory->validateBaseUrl($url);
    }

    /**
     * Data provider for private IP addresses and cloud metadata endpoints.
     *
     * @return array<string, array<string>>
     */
    public static function privateIpDataProvider(): array
    {
        return [
            'cloud_metadata' => ['https://169.254.169.254'],
            'private_10_network' => ['https://10.0.0.1'],
            'private_172_network' => ['https://172.20.0.5'],
            'private_192_network' => ['https://192.168.1.100'],
            'ipv6_link_local' => ['https://[fe80::1]'],
        ];
    }

    /**
     * Test empty credentials throw exception.
     *
     * @return void
     */
    public function testCreateThrowsExceptionOnEmptyPublicKey(): void
    {
        $this->configStub->method('getPublicKey')->willReturn('');
        $this->configStub->method('getSecretKey')->willReturn('sk_live_123');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Clusterify API Public Key is required.');

        $this->factory->create('', 'sk_live_123');
    }
}
