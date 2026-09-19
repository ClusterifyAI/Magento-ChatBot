<?php
/**
 * ClusterifyAI ChatBot CLI pagetype-visibility:list command
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
 * Class PageTypeVisibilityListCommand
 *
 * Lists all dynamically discovered Magento page types and their effective visibility status.
 */
class PageTypeVisibilityListCommand extends Command
{
    /**
     * Command identifier
     */
    public const COMMAND_NAME = 'clusterify:chatbot:config:pagetype-visibility:list';

    /**
     * Option name for configuration scope
     */
    public const OPTION_SCOPE = 'scope';

    /**
     * Option name for scope code
     */
    public const OPTION_SCOPE_CODE = 'scope-code';

    /**
     * Option name for keyword filter
     */
    public const OPTION_FILTER = 'filter';

    /**
     * Option name for category filter
     */
    public const OPTION_CATEGORY = 'category';

    /**
     * Option name for output format
     */
    public const OPTION_FORMAT = 'format';

    /**
     * @param PageVisibility        $pageVisibilityService Page visibility discovery service
     * @param StoreManagerInterface $storeManager          Store manager
     * @param string|null           $name                  Command name
     */
    public function __construct(
        private readonly PageVisibility $pageVisibilityService,
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
            ->setDescription('List all dynamic page types and their current ChatBot visibility status.')
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
                self::OPTION_FILTER,
                null,
                InputOption::VALUE_OPTIONAL,
                'Filter page types by keyword or handle'
            )
            ->addOption(
                self::OPTION_CATEGORY,
                null,
                InputOption::VALUE_OPTIONAL,
                'Filter by category (landing, product, checkout, customer, search, custom)'
            )
            ->addOption(
                self::OPTION_FORMAT,
                'f',
                InputOption::VALUE_OPTIONAL,
                'Output format (table or json)',
                'table'
            );

        parent::configure();
    }

    /**
     * Execute the list command.
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
            $filter = strtolower(trim((string) ($input->getOption(self::OPTION_FILTER) ?? '')));
            $categoryFilter = strtolower(trim((string) ($input->getOption(self::OPTION_CATEGORY) ?? '')));
            $format = strtolower(trim((string) $input->getOption(self::OPTION_FORMAT)));

            [$scopeType, $scopeCode, $scopeDisplay] = $this->resolveScope($scopeInput, $scopeCodeInput);

            $categories = $this->pageVisibilityService->getCategorizedPageTypes();
            $configuredPages = $this->pageVisibilityService->getConfiguredPages($scopeCode, $scopeType);

            $dataRows = [];
            $jsonCategories = [];

            foreach ($categories as $catKey => $category) {
                if ($categoryFilter !== '' && $categoryFilter !== $catKey) {
                    continue;
                }

                $jsonCategoryItems = [];

                foreach ($category['items'] as $code => $label) {
                    if ($filter !== '' && !str_contains(strtolower($code), $filter) && !str_contains(strtolower($label), $filter)) {
                        continue;
                    }

                    $isAllowed = $this->pageVisibilityService->isPageAllowed($code, $scopeCode, $scopeType);
                    $hasOverride = array_key_exists($code, $configuredPages);

                    $dataRows[] = [
                        $category['title'],
                        $label,
                        $code,
                        $isAllowed ? '<info>Enabled</info>' : '<comment>Disabled</comment>',
                        $hasOverride ? 'Custom' : 'Default',
                    ];

                    $jsonCategoryItems[] = [
                        'code' => $code,
                        'name' => $label,
                        'is_allowed' => $isAllowed,
                        'has_override' => $hasOverride,
                    ];
                }

                if (!empty($jsonCategoryItems)) {
                    $jsonCategories[$catKey] = [
                        'title' => $category['title'],
                        'items' => $jsonCategoryItems,
                    ];
                }
            }

            if ($format === 'json') {
                $payload = [
                    'scope' => $scopeDisplay,
                    'categories' => $jsonCategories,
                ];
                $output->writeln((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                return Cli::RETURN_SUCCESS;
            }

            if (empty($dataRows)) {
                $output->writeln('<comment>No page types found matching criteria.</comment>');
                return Cli::RETURN_SUCCESS;
            }

            $output->writeln('');
            $output->writeln(sprintf('<info>Clusterify.AI ChatBot Page Type Visibility [%s]</info>', $scopeDisplay));

            $table = new Table($output);
            $table->setHeaders(['Category', 'Page Name', 'Layout Handle / Code', 'Status', 'Setting Type']);
            $table->setRows($dataRows);
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
     * @return array{0: string, 1: int|string|null, 2: string}
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
