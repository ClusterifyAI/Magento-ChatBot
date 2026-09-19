<?php
/**
 * ClusterifyAI ChatBot CLI config show command
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Console\Command;

use ClusterifyAI\ChatBot\Model\Config;
use ClusterifyAI\ChatBot\Service\PageVisibility;
use InvalidArgumentException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Console\Cli;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ConfigShowCommand
 *
 * Displays the current Clusterify.AI ChatBot configuration values for a given scope in table or JSON format.
 */
class ConfigShowCommand extends Command
{
    /**
     * Command identifier
     */
    public const COMMAND_NAME = 'clusterify:chatbot:config:show';

    /**
     * Option name for configuration scope
     */
    public const OPTION_SCOPE = 'scope';

    /**
     * Option name for scope code
     */
    public const OPTION_SCOPE_CODE = 'scope-code';

    /**
     * Option name for output format
     */
    public const OPTION_FORMAT = 'format';

    /**
     * Option flag to reveal unmasked secrets
     */
    public const OPTION_SHOW_SECRETS = 'show-secrets';

    /**
     * @param Config                $config                Module configuration service
     * @param PageVisibility        $pageVisibilityService Dynamic page types visibility service
     * @param StoreManagerInterface $storeManager          Store manager for resolving scopes
     * @param string|null           $name                  Command name
     */
    public function __construct(
        private readonly Config $config,
        private readonly PageVisibility $pageVisibilityService,
        private readonly StoreManagerInterface $storeManager,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * Configure command parameters and documentation.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription('Display current Clusterify.AI ChatBot configuration across scopes.')
            ->addOption(
                self::OPTION_SCOPE,
                's',
                InputOption::VALUE_OPTIONAL,
                'Configuration scope (default, website, store)',
                'default'
            )
            ->addOption(
                self::OPTION_SCOPE_CODE,
                'c',
                InputOption::VALUE_OPTIONAL,
                'Website or Store View code (e.g. "base" or "default")'
            )
            ->addOption(
                self::OPTION_FORMAT,
                'f',
                InputOption::VALUE_OPTIONAL,
                'Output format (table or json)',
                'table'
            )
            ->addOption(
                self::OPTION_SHOW_SECRETS,
                null,
                InputOption::VALUE_NONE,
                'Reveal unmasked API Secret Key in output'
            );

        parent::configure();
    }

    /**
     * Execute the config:show command.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $scopeInput = strtolower(trim((string) $input->getOption(self::OPTION_SCOPE)));
            $scopeCodeInput = $input->getOption(self::OPTION_SCOPE_CODE);
            $format = strtolower(trim((string) $input->getOption(self::OPTION_FORMAT)));
            $showSecrets = (bool) $input->getOption(self::OPTION_SHOW_SECRETS);

            [$scopeType, $scopeCode, $scopeDisplay] = $this->resolveScope($scopeInput, $scopeCodeInput);

            $isEnabled = $this->config->isEnabled($scopeCode, $scopeType);
            $isShowOnStorefront = $this->config->isShowOnStorefront($scopeCode, $scopeType);
            $publicUuid = $this->config->getPublicUuid($scopeCode, $scopeType);
            $publicKey = $this->config->getPublicKey($scopeCode, $scopeType);
            $secretKey = $this->config->getSecretKey($scopeCode, $scopeType);
            $apiBaseUrl = $this->config->getApiBaseUrl($scopeCode, $scopeType);

            // Compute page visibility statistics
            $allPages = $this->pageVisibilityService->getAllPageTypes();
            $configuredPages = $this->pageVisibilityService->getConfiguredPages($scopeCode, $scopeType);
            $enabledCount = 0;
            $disabledCount = 0;

            foreach (array_keys($allPages) as $pageCode) {
                if ($this->pageVisibilityService->isPageAllowed($pageCode, $scopeCode, $scopeType)) {
                    $enabledCount++;
                } else {
                    $disabledCount++;
                }
            }

            $maskedSecret = $this->maskSecret($secretKey, $showSecrets);

            if ($format === 'json') {
                $payload = [
                    'extension_version' => $this->config->getExtensionVersion(),
                    'scope' => $scopeInput,
                    'scope_code' => $scopeCode,
                    'scope_label' => $scopeDisplay,
                    'configuration' => [
                        'enabled' => $isEnabled,
                        'show_on_storefront' => $isShowOnStorefront,
                        'public_uuid' => $publicUuid,
                        'public_key' => $publicKey,
                        'secret_key' => $maskedSecret,
                        'api_base_url' => $apiBaseUrl,
                    ],
                    'url_knowledge_sync' => [
                        'sync_enabled' => $this->config->isSyncEnabled($scopeCode, $scopeType),
                        'sync_cms' => $this->config->isCmsSyncEnabled($scopeCode, $scopeType),
                        'sync_categories' => $this->config->isCategorySyncEnabled($scopeCode, $scopeType),
                        'sync_products' => $this->config->isProductSyncEnabled($scopeCode, $scopeType),
                        'custom_knowledge_only' => $this->config->isCustomKnowledgeOnlySyncEnabled($scopeCode, $scopeType),
                        'sync_product_price' => $this->config->isProductPriceSyncEnabled($scopeCode, $scopeType),
                        'sync_product_availability' => $this->config->isProductAvailabilitySyncEnabled($scopeCode, $scopeType),
                        'in_stock_only' => $this->config->isInStockOnlySyncEnabled($scopeCode, $scopeType),
                    ],
                    'page_visibility_summary' => [
                        'total_page_types' => count($allPages),
                        'allowed_page_types' => $enabledCount,
                        'blocked_page_types' => $disabledCount,
                        'custom_overrides_count' => count($configuredPages),
                    ],
                ];

                $output->writeln((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                return Cli::RETURN_SUCCESS;
            }

            $table = new Table($output);
            $table->setHeaders(['Configuration Item', 'Scope', 'Value']);
            $table->addRows([
                ['Extension Version', 'Global', '<info>v' . $this->config->getExtensionVersion() . '</info>'],
                ['Extension Enabled', $scopeDisplay, $isEnabled ? '<info>Yes (1)</info>' : '<comment>No (0)</comment>'],
                ['Show Chatbot on Storefront', $scopeDisplay, $isShowOnStorefront ? '<info>Yes (1)</info>' : '<comment>No (0)</comment>'],
                ['ChatBot Public UUID', $scopeDisplay, $publicUuid !== '' ? $publicUuid : '<comment>(not configured)</comment>'],
                ['API Public Key', $scopeDisplay, $publicKey !== '' ? $publicKey : '<comment>(not configured)</comment>'],
                ['API Secret Key', $scopeDisplay, $maskedSecret !== '' ? $maskedSecret : '<comment>(not configured)</comment>'],
                ['API Base URL', $scopeDisplay, $apiBaseUrl],
                ['URL Knowledge Base Sync', $scopeDisplay, $this->config->isSyncEnabled($scopeCode, $scopeType) ? '<info>Enabled (1)</info>' : '<comment>Disabled (0)</comment>'],
                ['Custom Knowledge Priority', $scopeDisplay, $this->config->isCustomKnowledgeOnlySyncEnabled($scopeCode, $scopeType) ? '<info>Yes (1)</info>' : '<comment>No (0)</comment>'],
                ['Sync Product Price', $scopeDisplay, $this->config->isProductPriceSyncEnabled($scopeCode, $scopeType) ? '<info>Yes (1)</info>' : '<comment>No (0)</comment>'],
                ['Sync Product Availability', $scopeDisplay, $this->config->isProductAvailabilitySyncEnabled($scopeCode, $scopeType) ? '<info>Yes (1)</info>' : '<comment>No (0)</comment>'],
                ['Page Visibility Summary', $scopeDisplay, sprintf('%d total (%d allowed, %d blocked)', count($allPages), $enabledCount, $disabledCount)],
            ]);

            $output->writeln('');
            $output->writeln(sprintf('<info>Clusterify.AI ChatBot Configuration [%s]</info>', $scopeDisplay));
            $table->render();
            $output->writeln('');

            return Cli::RETURN_SUCCESS;
        } catch (InvalidArgumentException $e) {
            if (($input->getOption(self::OPTION_FORMAT) ?? '') === 'json') {
                $output->writeln((string) json_encode([
                    'success' => false,
                    'error' => 'INVALID_ARGUMENT',
                    'message' => $e->getMessage(),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
            }
            return Cli::RETURN_FAILURE;
        }
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

    /**
     * Mask sensitive API secret key unless explicitly permitted to display.
     *
     * @param string $secretKey
     * @param bool   $showSecrets
     * @return string
     */
    private function maskSecret(string $secretKey, bool $showSecrets): string
    {
        if ($secretKey === '' || $showSecrets) {
            return $secretKey;
        }

        $length = strlen($secretKey);
        if ($length <= 8) {
            return '••••••••';
        }

        return substr($secretKey, 0, 8) . str_repeat('•', max(0, $length - 12)) . substr($secretKey, -4);
    }
}
