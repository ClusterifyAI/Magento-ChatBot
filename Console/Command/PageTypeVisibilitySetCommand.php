<?php
/**
 * ClusterifyAI ChatBot CLI pagetype-visibility:set command
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Console\Command;

use ClusterifyAI\ChatBot\Service\PageVisibility;
use InvalidArgumentException;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Console\Cli;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class PageTypeVisibilitySetCommand
 *
 * Sets visibility status (enable/disable) for a specific page type, an entire category, or all page types.
 */
class PageTypeVisibilitySetCommand extends Command
{
    /**
     * Command identifier
     */
    public const COMMAND_NAME = 'clusterify:chatbot:config:pagetype-visibility:set';

    /**
     * Argument name for target page type handle or category
     */
    public const ARG_PAGETYPE = 'pagetype';

    /**
     * Argument name for desired visibility state
     */
    public const ARG_STATE = 'state';

    /**
     * Option name for configuration scope
     */
    public const OPTION_SCOPE = 'scope';

    /**
     * Option name for scope code
     */
    public const OPTION_SCOPE_CODE = 'scope-code';

    /**
     * Option flag to treat pagetype as category key
     */
    public const OPTION_BY_CATEGORY = 'by-category';

    /**
     * Option flag to update all page types simultaneously
     */
    public const OPTION_ALL = 'all';

    /**
     * @param PageVisibility        $pageVisibilityService Page visibility service
     * @param WriterInterface       $configWriter          Configuration writer
     * @param TypeListInterface     $cacheTypeList         Cache manager
     * @param StoreManagerInterface $storeManager          Store manager
     * @param string|null           $name                  Command name
     */
    public function __construct(
        private readonly PageVisibility $pageVisibilityService,
        private readonly WriterInterface $configWriter,
        private readonly TypeListInterface $cacheTypeList,
        private readonly StoreManagerInterface $storeManager,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * Configure command arguments and options.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription('Enable or disable ChatBot visibility for a specific page type, category, or all page types.')
            ->addArgument(
                self::ARG_PAGETYPE,
                InputArgument::REQUIRED,
                'Page type handle (e.g. "checkout_cart_index"), category key when using --by-category, or "all" when using --all'
            )
            ->addArgument(
                self::ARG_STATE,
                InputArgument::REQUIRED,
                'Visibility state (enable/disable, 1/0, yes/no)'
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
                'Website or Store View code'
            )
            ->addOption(
                self::OPTION_BY_CATEGORY,
                null,
                InputOption::VALUE_NONE,
                'Treat the pagetype argument as a category key (landing, product, checkout, customer, search, custom)'
            )
            ->addOption(
                self::OPTION_ALL,
                null,
                InputOption::VALUE_NONE,
                'Apply visibility state to all discovered page types'
            );

        parent::configure();
    }

    /**
     * Execute the pagetype-visibility:set command.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $pageTypeArg = trim((string) $input->getArgument(self::ARG_PAGETYPE));
            $stateArg = trim((string) $input->getArgument(self::ARG_STATE));
            $isByCategory = (bool) $input->getOption(self::OPTION_BY_CATEGORY);
            $isAll = (bool) $input->getOption(self::OPTION_ALL);

            $scopeInput = strtolower(trim((string) $input->getOption(self::OPTION_SCOPE)));
            $scopeCodeInput = $input->getOption(self::OPTION_SCOPE_CODE);

            [$scopeType, $scopeId, $scopeDisplay] = $this->resolveScope($scopeInput, $scopeCodeInput);
            $stateInt = $this->normalizeBoolean($stateArg);

            $allPageTypes = $this->pageVisibilityService->getAllPageTypes();
            $categories = $this->pageVisibilityService->getCategorizedPageTypes();
            $currentConfigured = $this->pageVisibilityService->getConfiguredPages(
                $scopeInput === 'default' ? null : $scopeCodeInput,
                $scopeType
            );

            $affectedCount = 0;

            if ($isAll || strtolower($pageTypeArg) === 'all') {
                foreach (array_keys($allPageTypes) as $code) {
                    $currentConfigured[$code] = $stateInt;
                    $affectedCount++;
                }
                $message = sprintf('Updated all %d page types to %s.', $affectedCount, $stateInt === 1 ? 'Enabled' : 'Disabled');
            } elseif ($isByCategory) {
                $catKey = strtolower($pageTypeArg);
                if (!isset($categories[$catKey])) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'Unknown category "%s". Allowed categories: %s',
                            $catKey,
                            implode(', ', array_keys($categories))
                        )
                    );
                }

                foreach (array_keys($categories[$catKey]['items']) as $code) {
                    $currentConfigured[$code] = $stateInt;
                    $affectedCount++;
                }
                $message = sprintf('Updated all %d page types in category "%s" to %s.', $affectedCount, $catKey, $stateInt === 1 ? 'Enabled' : 'Disabled');
            } else {
                if (!isset($allPageTypes[$pageTypeArg])) {
                    $output->writeln(sprintf('<comment>Warning: "%s" is not a standard registered page type. Setting rule anyway.</comment>', $pageTypeArg));
                }
                $currentConfigured[$pageTypeArg] = $stateInt;
                $affectedCount = 1;
                $message = sprintf('Updated page type "%s" to %s.', $pageTypeArg, $stateInt === 1 ? 'Enabled' : 'Disabled');
            }

            // Save JSON encoded array to core_config_data
            $this->configWriter->save(
                PageVisibility::XML_PATH_ALLOWED_PAGES,
                json_encode($currentConfigured),
                $scopeType,
                $scopeId
            );

            $this->cacheTypeList->cleanType('config');
            $this->cacheTypeList->cleanType('full_page');
            $this->cacheTypeList->cleanType('block_html');

            $output->writeln('');
            $output->writeln(sprintf('<info>[SUCCESS] %s</info>', $message));
            $output->writeln(sprintf('  Scope:           <comment>%s</comment>', $scopeDisplay));
            $output->writeln(sprintf('  Affected Count:  <info>%d</info>', $affectedCount));
            $output->writeln('  Config, full-page, and block HTML caches have been automatically refreshed.');
            $output->writeln('');

            return Cli::RETURN_SUCCESS;
        } catch (InvalidArgumentException $e) {
            $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
            return Cli::RETURN_FAILURE;
        }
    }

    /**
     * Normalize boolean string input to 1 or 0 integer.
     *
     * @param string $value
     * @return int
     * @throws InvalidArgumentException
     */
    private function normalizeBoolean(string $value): int
    {
        $val = strtolower(trim($value));

        if (in_array($val, ['1', 'true', 'yes', 'enable', 'on'], true)) {
            return 1;
        }

        if (in_array($val, ['0', 'false', 'no', 'disable', 'off'], true)) {
            return 0;
        }

        throw new InvalidArgumentException(
            sprintf('Invalid state value "%s". Use enable/disable, 1/0, or yes/no.', $value)
        );
    }

    /**
     * Resolve scope string and code to Magento writer scope parameters.
     *
     * @param string      $scopeInput
     * @param string|null $scopeCodeInput
     * @return array{0: string, 1: int, 2: string}
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
