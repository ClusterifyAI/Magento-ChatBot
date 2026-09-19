<?php
/**
 * ClusterifyAI ChatBot CLI pagetype-visibility:reset command
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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class PageTypeVisibilityResetCommand
 *
 * Resets all page type visibility rules back to default settings (Checkout pages disabled, all others enabled).
 */
class PageTypeVisibilityResetCommand extends Command
{
    /**
     * Command identifier
     */
    public const COMMAND_NAME = 'clusterify:chatbot:config:pagetype-visibility:reset';

    /**
     * Option name for configuration scope
     */
    public const OPTION_SCOPE = 'scope';

    /**
     * Option name for scope code
     */
    public const OPTION_SCOPE_CODE = 'scope-code';

    /**
     * Option flag to clear custom override and restore inheritance
     */
    public const OPTION_CLEAR_OVERRIDE = 'clear-override';

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
     * Configure command options and description.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription('Reset ChatBot page visibility rules to default behavior (Checkout pages disabled, others enabled).')
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
                self::OPTION_CLEAR_OVERRIDE,
                null,
                InputOption::VALUE_NONE,
                'Delete custom scope override entirely to restore inheritance from parent scope'
            );

        parent::configure();
    }

    /**
     * Execute the reset command.
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
            $clearOverride = (bool) $input->getOption(self::OPTION_CLEAR_OVERRIDE);

            [$scopeType, $scopeId, $scopeDisplay] = $this->resolveScope($scopeInput, $scopeCodeInput);

            if ($clearOverride) {
                $this->configWriter->delete(
                    PageVisibility::XML_PATH_ALLOWED_PAGES,
                    $scopeType,
                    $scopeId
                );
                $actionMessage = 'Cleared custom scope override. Inheriting from parent scope.';
            } else {
                // Construct default mapping
                $allPages = $this->pageVisibilityService->getAllPageTypes();
                $defaultMap = [];
                foreach (array_keys($allPages) as $code) {
                    $defaultMap[$code] = $this->pageVisibilityService->getDefaultStatus($code) ? 1 : 0;
                }

                $this->configWriter->save(
                    PageVisibility::XML_PATH_ALLOWED_PAGES,
                    json_encode($defaultMap),
                    $scopeType,
                    $scopeId
                );
                $actionMessage = sprintf('Reset %d page types to defaults (Checkout pages: Disabled, Others: Enabled).', count($defaultMap));
            }

            $this->cacheTypeList->cleanType('config');
            $this->cacheTypeList->cleanType('full_page');
            $this->cacheTypeList->cleanType('block_html');

            $output->writeln('');
            $output->writeln(sprintf('<info>[SUCCESS] %s</info>', $actionMessage));
            $output->writeln(sprintf('  Scope: <comment>%s</comment>', $scopeDisplay));
            $output->writeln('  Config, full-page, and block HTML caches have been automatically refreshed.');
            $output->writeln('');

            return Cli::RETURN_SUCCESS;
        } catch (InvalidArgumentException $e) {
            $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
            return Cli::RETURN_FAILURE;
        }
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
