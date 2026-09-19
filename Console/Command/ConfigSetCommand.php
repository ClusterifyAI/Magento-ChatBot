<?php
/**
 * ClusterifyAI ChatBot CLI config set command
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
use ClusterifyAI\ChatBot\Service\ClientFactory;
use InvalidArgumentException;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Console\Cli;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Class ConfigSetCommand
 *
 * Sets a specific Clusterify.AI ChatBot configuration value across Default, Website, or Store View scopes.
 */
class ConfigSetCommand extends Command
{
    /**
     * CLI command name
     */
    public const COMMAND_NAME = 'clusterify:chatbot:config:set';

    /**
     * Argument name for the configuration field
     */
    public const ARG_FIELD = 'field';

    /**
     * Argument name for the configuration value
     */
    public const ARG_VALUE = 'value';

    /**
     * Option name for configuration scope
     */
    public const OPTION_SCOPE = 'scope';

    /**
     * Option name for scope code (website or store code)
     */
    public const OPTION_SCOPE_CODE = 'scope-code';

    /**
     * Configuration field: enabled
     */
    public const FIELD_ENABLED = 'enabled';

    /**
     * Configuration field: show_on_storefront
     */
    public const FIELD_SHOW_ON_STOREFRONT = 'show_on_storefront';

    /**
     * Configuration field: public_uuid
     */
    public const FIELD_PUBLIC_UUID = 'public_uuid';

    /**
     * Configuration field: public_key
     */
    public const FIELD_PUBLIC_KEY = 'public_key';

    /**
     * Configuration field: secret_key
     */
    public const FIELD_SECRET_KEY = 'secret_key';

    /**
     * Configuration field: api_base_url
     */
    public const FIELD_API_BASE_URL = 'api_base_url';

    /**
     * Configuration field: sync_enabled
     */
    public const FIELD_SYNC_ENABLED = 'sync_enabled';

    /**
     * Configuration field: sync_cms
     */
    public const FIELD_SYNC_CMS = 'sync_cms';

    /**
     * Configuration field: sync_categories
     */
    public const FIELD_SYNC_CATEGORIES = 'sync_categories';

    /**
     * Configuration field: sync_products
     */
    public const FIELD_SYNC_PRODUCTS = 'sync_products';

    /**
     * Configuration field: sync_in_stock_only
     */
    public const FIELD_SYNC_IN_STOCK_ONLY = 'sync_in_stock_only';

    /**
     * Configuration field: custom_knowledge_only
     */
    public const FIELD_SYNC_CUSTOM_KNOWLEDGE_ONLY = 'custom_knowledge_only';

    /**
     * Configuration field: sync_product_price
     */
    public const FIELD_SYNC_PRODUCT_PRICE = 'sync_product_price';

    /**
     * Configuration field: sync_product_availability
     */
    public const FIELD_SYNC_PRODUCT_AVAILABILITY = 'sync_product_availability';

    /**
     * List of all supported configuration field keys
     */
    public const ALLOWED_FIELDS = [
        self::FIELD_ENABLED,
        self::FIELD_SHOW_ON_STOREFRONT,
        self::FIELD_PUBLIC_UUID,
        self::FIELD_PUBLIC_KEY,
        self::FIELD_SECRET_KEY,
        self::FIELD_API_BASE_URL,
        self::FIELD_SYNC_ENABLED,
        self::FIELD_SYNC_CMS,
        self::FIELD_SYNC_CATEGORIES,
        self::FIELD_SYNC_PRODUCTS,
        self::FIELD_SYNC_CUSTOM_KNOWLEDGE_ONLY,
        self::FIELD_SYNC_PRODUCT_PRICE,
        self::FIELD_SYNC_PRODUCT_AVAILABILITY,
        self::FIELD_SYNC_IN_STOCK_ONLY,
    ];

    /**
     * @param WriterInterface       $configWriter    Configuration storage writer
     * @param EncryptorInterface    $encryptor       Magento encryption provider for secret keys
     * @param TypeListInterface     $cacheTypeList   Cache manager for clearing config and FPC caches
     * @param StoreManagerInterface $storeManager    Store manager for resolving scopes
     * @param ClientFactory         $clientFactory   Client factory for URL validation
     * @param string|null           $name            Command name
     */
    public function __construct(
        private readonly WriterInterface $configWriter,
        private readonly EncryptorInterface $encryptor,
        private readonly TypeListInterface $cacheTypeList,
        private readonly StoreManagerInterface $storeManager,
        private readonly ClientFactory $clientFactory,
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
            ->setDescription('Set a Clusterify.AI ChatBot configuration value.')
            ->addArgument(
                self::ARG_FIELD,
                InputArgument::REQUIRED,
                sprintf(
                    'Configuration key to update (%s)',
                    implode(', ', self::ALLOWED_FIELDS)
                )
            )
            ->addArgument(
                self::ARG_VALUE,
                InputArgument::OPTIONAL,
                'Value to set for the specified configuration key'
            )
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
            );

        parent::configure();
    }

    /**
     * Execute the config:set command.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $field = strtolower(trim((string) $input->getArgument(self::ARG_FIELD)));
            $value = $input->getArgument(self::ARG_VALUE);

            $scopeInput = strtolower(trim((string) $input->getOption(self::OPTION_SCOPE)));
            $scopeCodeInput = $input->getOption(self::OPTION_SCOPE_CODE);

            [$scopeType, $scopeId, $scopeDisplay] = $this->resolveScope($scopeInput, $scopeCodeInput);

            // Handle interactive hidden input for secret_key if value was omitted
            if ($value === null && $field === self::FIELD_SECRET_KEY) {
                if ($input->isInteractive()) {
                    $helper = $this->getHelper('question');
                    $question = new Question('Enter Clusterify API Secret Key: ');
                    $question->setHidden(true);
                    $question->setHiddenFallback(false);
                    $value = (string) $helper->ask($input, $output, $question);
                } else {
                    throw new InvalidArgumentException('Value argument is required in non-interactive mode.');
                }
            } elseif ($value === null) {
                throw new InvalidArgumentException(sprintf('Value argument is required for field "%s".', $field));
            }

            // Security warning when passing secrets via command-line arguments
            if ($field === self::FIELD_SECRET_KEY && $input->getArgument(self::ARG_VALUE) !== null) {
                $output->writeln('<comment>[SECURITY NOTICE] Passing secret keys as CLI arguments may expose them in OS process listings and shell history. In interactive mode, omit the value argument to enter it securely.</comment>');
            }

            $value = (string) $value;
            [$path, $sanitizedValue, $displayValue] = $this->processFieldValue($field, $value);

            $this->configWriter->save($path, $sanitizedValue, $scopeType, $scopeId);

            // Invalidate config and full-page caches
            $this->cacheTypeList->cleanType('config');
            $this->cacheTypeList->cleanType('full_page');
            $this->cacheTypeList->cleanType('block_html');

            $output->writeln('');
            $output->writeln(sprintf('<info>[SUCCESS] Updated configuration successfully!</info>'));
            $output->writeln(sprintf('  Field:       <comment>%s</comment>', $field));
            $output->writeln(sprintf('  Scope:       <comment>%s</comment>', $scopeDisplay));
            $output->writeln(sprintf('  Saved Value: <info>%s</info>', $displayValue));
            $output->writeln('  Config, full-page, and block HTML caches have been automatically refreshed.');
            $output->writeln('');

            return Cli::RETURN_SUCCESS;
        } catch (InvalidArgumentException $e) {
            $output->writeln(sprintf('<error>Validation Error: %s</error>', $e->getMessage()));
            return Cli::RETURN_FAILURE;
        }
    }

    /**
     * Validate and process the field name and value before saving.
     *
     * @param string $field
     * @param string $value
     * @return array{0: string, 1: string, 2: string} [configPath, valueToSave, displayValue]
     * @throws InvalidArgumentException
     */
    private function processFieldValue(string $field, string $value): array
    {
        switch ($field) {
            case self::FIELD_ENABLED:
                $normalized = $this->normalizeBoolean($value);
                return [
                    Config::XML_PATH_ENABLED,
                    $normalized,
                    $normalized === '1' ? 'Enabled (1)' : 'Disabled (0)',
                ];

            case self::FIELD_SHOW_ON_STOREFRONT:
                $normalized = $this->normalizeBoolean($value);
                return [
                    Config::XML_PATH_SHOW_ON_STOREFRONT,
                    $normalized,
                    $normalized === '1' ? 'Show on Storefront (1)' : 'Hidden from Storefront (0)',
                ];

            case self::FIELD_PUBLIC_UUID:
                $trimmed = trim($value);
                if ($trimmed === '') {
                    throw new InvalidArgumentException('Public UUID cannot be empty.');
                }
                if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $trimmed)) {
                    throw new InvalidArgumentException(
                        sprintf('Invalid Public UUID format "%s". Expected standard UUID format (e.g. 11111111-2222-3333-4444-555555555555).', $trimmed)
                    );
                }
                return [Config::XML_PATH_PUBLIC_UUID, $trimmed, $trimmed];

            case self::FIELD_PUBLIC_KEY:
                $trimmed = trim($value);
                if ($trimmed === '') {
                    throw new InvalidArgumentException('API Public Key cannot be empty.');
                }
                if (!preg_match('/^pk_(live|test)_[a-zA-Z0-9]+$/', $trimmed)) {
                    throw new InvalidArgumentException(
                        'Invalid Public Key format. Key must start with pk_live_ or pk_test_ (e.g. pk_live_abc123xyz456).'
                    );
                }
                return [Config::XML_PATH_PUBLIC_KEY, $trimmed, $trimmed];

            case self::FIELD_SECRET_KEY:
                $trimmed = trim($value);
                if ($trimmed === '') {
                    throw new InvalidArgumentException('API Secret Key cannot be empty.');
                }
                if (!preg_match('/^sk_(live|test)_[a-zA-Z0-9]+$/', $trimmed)) {
                    throw new InvalidArgumentException(
                        'Invalid Secret Key format. Key must start with sk_live_ or sk_test_ (e.g. sk_live_sec789secretkey).'
                    );
                }
                $encrypted = $this->encryptor->encrypt($trimmed);
                $masked = substr($trimmed, 0, 8) . str_repeat('•', max(0, strlen($trimmed) - 12)) . substr($trimmed, -4);
                return [Config::XML_PATH_SECRET_KEY, $encrypted, $masked];

            case self::FIELD_API_BASE_URL:
                $validated = $this->clientFactory->validateBaseUrl($value);
                return [Config::XML_PATH_API_BASE_URL, $validated, $validated];

            case self::FIELD_SYNC_ENABLED:
                $normalized = $this->normalizeBoolean($value);
                return [Config::XML_PATH_SYNC_ENABLED, $normalized, $normalized === '1' ? 'Enabled (1)' : 'Disabled (0)'];

            case self::FIELD_SYNC_CMS:
                $normalized = $this->normalizeBoolean($value);
                return [Config::XML_PATH_SYNC_CMS, $normalized, $normalized === '1' ? 'Enabled (1)' : 'Disabled (0)'];

            case self::FIELD_SYNC_CATEGORIES:
                $normalized = $this->normalizeBoolean($value);
                return [Config::XML_PATH_SYNC_CATEGORIES, $normalized, $normalized === '1' ? 'Enabled (1)' : 'Disabled (0)'];

            case self::FIELD_SYNC_PRODUCTS:
                $normalized = $this->normalizeBoolean($value);
                return [Config::XML_PATH_SYNC_PRODUCTS, $normalized, $normalized === '1' ? 'Enabled (1)' : 'Disabled (0)'];

            case self::FIELD_SYNC_CUSTOM_KNOWLEDGE_ONLY:
                $normalized = $this->normalizeBoolean($value);
                return [Config::XML_PATH_SYNC_CUSTOM_KNOWLEDGE_ONLY, $normalized, $normalized === '1' ? 'Custom Knowledge Prioritized (1)' : 'Standard Descriptions (0)'];

            case self::FIELD_SYNC_PRODUCT_PRICE:
                $normalized = $this->normalizeBoolean($value);
                return [Config::XML_PATH_SYNC_PRODUCT_PRICE, $normalized, $normalized === '1' ? 'Price Synced (1)' : 'Price Omitted (0)'];

            case self::FIELD_SYNC_PRODUCT_AVAILABILITY:
                $normalized = $this->normalizeBoolean($value);
                return [Config::XML_PATH_SYNC_PRODUCT_AVAILABILITY, $normalized, $normalized === '1' ? 'Availability Synced (1)' : 'Availability Omitted (0)'];

            case self::FIELD_SYNC_IN_STOCK_ONLY:
                $normalized = $this->normalizeBoolean($value);
                return [Config::XML_PATH_SYNC_IN_STOCK_ONLY, $normalized, $normalized === '1' ? 'In-Stock Only (1)' : 'All Products (0)'];

            default:
                throw new InvalidArgumentException(
                    sprintf(
                        'Unknown configuration field "%s". Allowed fields: %s',
                        $field,
                        implode(', ', self::ALLOWED_FIELDS)
                    )
                );
        }
    }

    /**
     * Normalize boolean string input into '0' or '1'.
     *
     * @param string $value
     * @return string
     * @throws InvalidArgumentException
     */
    private function normalizeBoolean(string $value): string
    {
        $val = strtolower(trim($value));

        if (in_array($val, ['1', 'true', 'yes', 'enable', 'on'], true)) {
            return '1';
        }

        if (in_array($val, ['0', 'false', 'no', 'disable', 'off'], true)) {
            return '0';
        }

        throw new InvalidArgumentException(
            sprintf('Invalid boolean value "%s". Use 1/0, yes/no, true/false, or enable/disable.', $value)
        );
    }

    /**
     * Resolve scope string and code to Magento writer scope parameters.
     *
     * @param string      $scopeInput
     * @param string|null $scopeCodeInput
     * @return array{0: string, 1: int, 2: string} [scopeType, scopeId, displayLabel]
     * @throws InvalidArgumentException
     */
    private function resolveScope(string $scopeInput, ?string $scopeCodeInput): array
    {
        if ($scopeInput === 'default' || $scopeInput === 'global') {
            return [ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0, 'Default Config'];
        }

        if ($scopeInput === 'website') {
            if ($scopeCodeInput === null || $scopeCodeInput === '') {
                throw new InvalidArgumentException('Option --scope-code is required when --scope=website.');
            }
            $website = $this->storeManager->getWebsite($scopeCodeInput);
            return [
                ScopeInterface::SCOPE_WEBSITES,
                (int) $website->getId(),
                sprintf('Website: %s (%s)', $website->getName(), $website->getCode()),
            ];
        }

        if ($scopeInput === 'store') {
            if ($scopeCodeInput === null || $scopeCodeInput === '') {
                throw new InvalidArgumentException('Option --scope-code is required when --scope=store.');
            }
            $store = $this->storeManager->getStore($scopeCodeInput);
            return [
                ScopeInterface::SCOPE_STORES,
                (int) $store->getId(),
                sprintf('Store View: %s (%s)', $store->getName(), $store->getCode()),
            ];
        }

        throw new InvalidArgumentException(sprintf('Invalid scope "%s". Allowed scopes: default, website, store.', $scopeInput));
    }
}
