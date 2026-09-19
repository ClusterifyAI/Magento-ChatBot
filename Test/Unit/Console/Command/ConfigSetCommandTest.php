<?php
/**
 * ClusterifyAI ChatBot CLI config set command unit test
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Test\Unit\Console\Command;

use ClusterifyAI\ChatBot\Console\Command\ConfigSetCommand;
use ClusterifyAI\ChatBot\Model\Config;
use ClusterifyAI\ChatBot\Service\ClientFactory;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Console\Cli;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Class ConfigSetCommandTest
 *
 * Unit tests for ClusterifyAI\ChatBot\Console\Command\ConfigSetCommand.
 */
#[AllowMockObjectsWithoutExpectations]
class ConfigSetCommandTest extends TestCase
{
    private WriterInterface|MockObject $configWriterMock;
    private EncryptorInterface $encryptorStub;
    private TypeListInterface|MockObject $cacheTypeListMock;
    private StoreManagerInterface $storeManagerStub;
    private ClientFactory $clientFactoryStub;
    private ConfigSetCommand $command;
    private CommandTester $commandTester;

    /**
     * Set up command tester and mocks.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->configWriterMock = $this->createMock(WriterInterface::class);
        $this->encryptorStub = $this->createStub(EncryptorInterface::class);
        $this->cacheTypeListMock = $this->createMock(TypeListInterface::class);
        $this->storeManagerStub = $this->createStub(StoreManagerInterface::class);
        $this->clientFactoryStub = $this->createStub(ClientFactory::class);

        $this->command = new ConfigSetCommand(
            $this->configWriterMock,
            $this->encryptorStub,
            $this->cacheTypeListMock,
            $this->storeManagerStub,
            $this->clientFactoryStub
        );

        $this->commandTester = new CommandTester($this->command);
    }

    /**
     * Test successful update of enabled field.
     *
     * @return void
     */
    public function testExecuteSetsEnabledSuccessfully(): void
    {
        $this->configWriterMock->expects($this->once())
            ->method('save')
            ->with(Config::XML_PATH_ENABLED, '1', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0);

        $cleanedTypes = [];
        $this->cacheTypeListMock->expects($this->exactly(3))
            ->method('cleanType')
            ->willReturnCallback(function (string $type) use (&$cleanedTypes) {
                $cleanedTypes[] = $type;
                return $this->cacheTypeListMock;
            });

        $statusCode = $this->commandTester->execute([
            'field' => 'enabled',
            'value' => 'yes',
        ]);

        $this->assertSame(Cli::RETURN_SUCCESS, $statusCode);
        $this->assertContains('config', $cleanedTypes);
        $this->assertContains('full_page', $cleanedTypes);
        $this->assertContains('block_html', $cleanedTypes);
        $this->assertStringContainsString('[SUCCESS] Updated configuration successfully!', $this->commandTester->getDisplay());
    }

    /**
     * Test rejection of invalid UUID.
     *
     * @return void
     */
    public function testExecuteRejectsInvalidUuid(): void
    {
        $statusCode = $this->commandTester->execute([
            'field' => 'public_uuid',
            'value' => 'invalid-not-a-uuid',
        ]);

        $this->assertSame(Cli::RETURN_FAILURE, $statusCode);
        $this->assertStringContainsString('Invalid Public UUID format', $this->commandTester->getDisplay());
    }

    /**
     * Test rejection of invalid public key.
     *
     * @return void
     */
    public function testExecuteRejectsInvalidPublicKey(): void
    {
        $statusCode = $this->commandTester->execute([
            'field' => 'public_key',
            'value' => 'invalid_prefix_123',
        ]);

        $this->assertSame(Cli::RETURN_FAILURE, $statusCode);
        $this->assertStringContainsString('Invalid Public Key format', $this->commandTester->getDisplay());
    }

    /**
     * Test rejection of invalid secret key.
     *
     * @return void
     */
    public function testExecuteRejectsInvalidSecretKey(): void
    {
        $statusCode = $this->commandTester->execute([
            'field' => 'secret_key',
            'value' => 'bad_secret_key',
        ]);

        $this->assertSame(Cli::RETURN_FAILURE, $statusCode);
        $this->assertStringContainsString('Invalid Secret Key format', $this->commandTester->getDisplay());
    }

    /**
     * Test rejection of invalid or private IP API Base URL.
     *
     * @return void
     */
    public function testExecuteRejectsInvalidApiBaseUrl(): void
    {
        $this->clientFactoryStub->method('validateBaseUrl')
            ->willThrowException(new \InvalidArgumentException('API Base URL cannot point to internal or private IP addresses.'));

        $statusCode = $this->commandTester->execute([
            'field' => 'api_base_url',
            'value' => 'https://10.0.0.1',
        ]);

        $this->assertSame(Cli::RETURN_FAILURE, $statusCode);
        $this->assertStringContainsString('Validation Error: API Base URL cannot point to internal or private IP addresses.', $this->commandTester->getDisplay());
    }

    /**
     * Test rejection of unknown configuration field key.
     *
     * @return void
     */
    public function testExecuteRejectsUnknownField(): void
    {
        $statusCode = $this->commandTester->execute([
            'field' => 'non_existent_key',
            'value' => '1',
        ]);

        $this->assertSame(Cli::RETURN_FAILURE, $statusCode);
        $display = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Unknown configuration field "non_existent_key"', $display);
        $this->assertStringContainsString('Allowed fields:', $display);
    }

    /**
     * Test setting sync_product_price and sync_product_availability.
     *
     * @return void
     */
    public function testExecuteSetsProductPriceAndAvailabilityToggles(): void
    {
        $this->configWriterMock->expects($this->exactly(2))
            ->method('save')
            ->willReturnMap([
                [Config::XML_PATH_SYNC_PRODUCT_PRICE, '1', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0],
                [Config::XML_PATH_SYNC_PRODUCT_AVAILABILITY, '1', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0],
            ]);

        $statusPrice = $this->commandTester->execute([
            'field' => 'sync_product_price',
            'value' => 'yes',
        ]);
        $this->assertSame(Cli::RETURN_SUCCESS, $statusPrice);

        $statusAvail = $this->commandTester->execute([
            'field' => 'sync_product_availability',
            'value' => '1',
        ]);
        $this->assertSame(Cli::RETURN_SUCCESS, $statusAvail);
    }
}
