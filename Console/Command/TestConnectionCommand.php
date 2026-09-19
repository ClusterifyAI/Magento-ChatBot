<?php
/**
 * ClusterifyAI ChatBot CLI test connection command
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Console\Command;

use Clusterify\Exceptions\AuthenticationException;
use Clusterify\Exceptions\AuthorizationException;
use Clusterify\Exceptions\ClusterifyException;
use Clusterify\Exceptions\NetworkException;
use Clusterify\Exceptions\RateLimitExceededException;
use ClusterifyAI\ChatBot\Model\Config;
use ClusterifyAI\ChatBot\Service\ClientFactory;
use Exception;
use InvalidArgumentException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Console\Cli;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class TestConnectionCommand
 *
 * Executes a live healthcheck and credential validation test against Clusterify.AI API via the PHP SDK.
 */
class TestConnectionCommand extends Command
{
    /**
     * Primary command name
     */
    public const COMMAND_NAME = 'clusterify:chatbot:config:test';

    /**
     * Command alias
     */
    public const COMMAND_ALIAS = 'clusterify:chatbot:test';

    /**
     * Option name for scope
     */
    public const OPTION_SCOPE = 'scope';

    /**
     * Option name for scope code
     */
    public const OPTION_SCOPE_CODE = 'scope-code';

    /**
     * Option name for public key override
     */
    public const OPTION_PUBLIC_KEY = 'public-key';

    /**
     * Option name for secret key override
     */
    public const OPTION_SECRET_KEY = 'secret-key';

    /**
     * Option name for API base URL override
     */
    public const OPTION_API_BASE_URL = 'api-base-url';

    /**
     * Option name for output format
     */
    public const OPTION_FORMAT = 'format';

    /**
     * Clusterify Dashboard API keys URL
     */
    public const DASHBOARD_API_KEY_URL = 'https://dashboard.clusterify.ai/api-key';

    /**
     * @param ClientFactory         $clientFactory Client factory service
     * @param Config                $config        Configuration provider
     * @param StoreManagerInterface $storeManager  Store manager
     * @param string|null           $name          Command name
     */
    public function __construct(
        private readonly ClientFactory $clientFactory,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * Configure command options and description.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->setName(self::COMMAND_NAME)
            ->setAliases([self::COMMAND_ALIAS])
            ->setDescription('Test Clusterify.AI API connectivity and credentials using live ping.')
            ->addOption(
                self::OPTION_SCOPE,
                's',
                InputOption::VALUE_OPTIONAL,
                'Configuration scope to test (default, website, store)',
                'default'
            )
            ->addOption(
                self::OPTION_SCOPE_CODE,
                'c',
                InputOption::VALUE_OPTIONAL,
                'Website or Store View code'
            )
            ->addOption(
                self::OPTION_PUBLIC_KEY,
                null,
                InputOption::VALUE_OPTIONAL,
                'Override API Public Key for this test'
            )
            ->addOption(
                self::OPTION_SECRET_KEY,
                null,
                InputOption::VALUE_OPTIONAL,
                'Override API Secret Key for this test'
            )
            ->addOption(
                self::OPTION_API_BASE_URL,
                null,
                InputOption::VALUE_OPTIONAL,
                'Override API Base URL for this test'
            )
            ->addOption(
                self::OPTION_FORMAT,
                'f',
                InputOption::VALUE_OPTIONAL,
                'Output format (text or json)',
                'text'
            );

        parent::configure();
    }

    /**
     * Execute the test command.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = strtolower(trim((string) $input->getOption(self::OPTION_FORMAT)));

        try {
            $scopeInput = strtolower(trim((string) $input->getOption(self::OPTION_SCOPE)));
            $scopeCodeInput = $input->getOption(self::OPTION_SCOPE_CODE);

            [$scopeType, $scopeCode, $scopeDisplay] = $this->resolveScope($scopeInput, $scopeCodeInput);

            $publicKey = (string) ($input->getOption(self::OPTION_PUBLIC_KEY) ?? $this->config->getPublicKey($scopeCode, $scopeType));
            $secretKey = (string) ($input->getOption(self::OPTION_SECRET_KEY) ?? $this->config->getSecretKey($scopeCode, $scopeType));
            $apiBaseUrl = (string) ($input->getOption(self::OPTION_API_BASE_URL) ?? $this->config->getApiBaseUrl($scopeCode, $scopeType));

            if (trim($publicKey) === '' || trim($secretKey) === '') {
                throw new InvalidArgumentException(
                    'Both API Public Key and API Secret Key are required. Provide them in configuration or via --public-key and --secret-key.'
                );
            }

            $client = $this->clientFactory->create(
                publicKey: $publicKey,
                secretKey: $secretKey,
                apiBaseUrl: $apiBaseUrl,
                scopeCode: $scopeCode,
                scopeType: $scopeType
            );

            $ping = $client->ping();

            if ($format === 'json') {
                $payload = [
                    'success' => true,
                    'status' => $ping->status,
                    'authenticated' => $ping->authenticated,
                    'timestamp' => $ping->timestamp,
                    'endpoint' => $apiBaseUrl,
                    'scope' => $scopeDisplay,
                ];
                $output->writeln((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                return Cli::RETURN_SUCCESS;
            }

            $output->writeln('');
            $output->writeln('<info>[SUCCESS] Connection test successful!</info>');
            $output->writeln(sprintf('  Scope:           <comment>%s</comment>', $scopeDisplay));
            $output->writeln(sprintf('  Endpoint:        <comment>%s</comment>', $apiBaseUrl));
            $output->writeln(sprintf('  Status:          <info>%s</info>', $ping->status));
            $output->writeln(sprintf('  Authenticated:   <info>%s</info>', $ping->authenticated ? 'Yes' : 'No'));
            $output->writeln(sprintf('  Server Time:     %s', $ping->timestamp));
            $output->writeln('');

            return Cli::RETURN_SUCCESS;
        } catch (AuthenticationException $e) {
            $msg = 'Authentication failed (401): Invalid API keys. Please verify your Public Key and Secret Key on the Clusterify.AI Dashboard: ' . self::DASHBOARD_API_KEY_URL;
            return $this->handleError($output, $format, 401, 'INVALID_API_KEYS', $msg);
        } catch (AuthorizationException $e) {
            $msg = 'Access forbidden (403): Your API Key is currently disabled. Please enable it on the Clusterify.AI Dashboard: ' . self::DASHBOARD_API_KEY_URL;
            return $this->handleError($output, $format, 403, 'API_KEY_DISABLED', $msg);
        } catch (RateLimitExceededException $e) {
            $msg = sprintf('API rate limit reached (429). Retry after %d seconds.', $e->getRetryAfter() ?? 60);
            return $this->handleError($output, $format, 429, 'RATE_LIMIT_EXCEEDED', $msg);
        } catch (NetworkException $e) {
            $msg = sprintf('Network error: Unable to connect to Clusterify API endpoint. (%s)', $e->getMessage());
            return $this->handleError($output, $format, 0, 'NETWORK_ERROR', $msg);
        } catch (ClusterifyException $e) {
            $msg = sprintf('Clusterify API Error [%s]: %s', $e->getErrorCode(), $e->getMessage());
            return $this->handleError($output, $format, 500, $e->getErrorCode(), $msg);
        } catch (Exception $e) {
            return $this->handleError($output, $format, 500, 'ERROR', $e->getMessage());
        }
    }

    /**
     * Render formatted error output in text or JSON.
     *
     * @param OutputInterface $output
     * @param string          $format
     * @param int             $code
     * @param string          $errorKey
     * @param string          $message
     * @return int
     */
    private function handleError(OutputInterface $output, string $format, int $code, string $errorKey, string $message): int
    {
        if ($format === 'json') {
            $payload = [
                'success' => false,
                'status_code' => $code,
                'error' => $errorKey,
                'message' => $message,
            ];
            $output->writeln((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $output->writeln('');
            $output->writeln(sprintf('<error>[FAILURE] %s</error>', $message));
            $output->writeln('');
        }

        return Cli::RETURN_FAILURE;
    }

    /**
     * Resolve scope string and code to Magento scope parameters.
     *
     * @param string      $scopeInput
     * @param string|null $scopeCodeInput
     * @return array{0: string, 1: int|string|null, 2: string} [scopeType, scopeCode, displayLabel]
     * @throws InvalidArgumentException
     */
    private function resolveScope(string $scopeInput, ?string $scopeCodeInput): array
    {
        if ($scopeInput === 'default' || $scopeInput === 'global') {
            return [ScopeConfigInterface::SCOPE_TYPE_DEFAULT, null, 'Default Config'];
        }

        if ($scopeInput === 'website') {
            if ($scopeCodeInput === null || $scopeCodeInput === '') {
                throw new InvalidArgumentException('Option --scope-code is required when --scope=website.');
            }
            $website = $this->storeManager->getWebsite($scopeCodeInput);
            return [
                ScopeInterface::SCOPE_WEBSITE,
                $website->getCode(),
                sprintf('Website: %s (%s)', $website->getName(), $website->getCode()),
            ];
        }

        if ($scopeInput === 'store') {
            if ($scopeCodeInput === null || $scopeCodeInput === '') {
                throw new InvalidArgumentException('Option --scope-code is required when --scope=store.');
            }
            $store = $this->storeManager->getStore($scopeCodeInput);
            return [
                ScopeInterface::SCOPE_STORE,
                (int) $store->getId(),
                sprintf('Store View: %s (%s)', $store->getName(), $store->getCode()),
            ];
        }

        throw new InvalidArgumentException(sprintf('Invalid scope "%s". Allowed scopes: default, website, store.', $scopeInput));
    }
}
