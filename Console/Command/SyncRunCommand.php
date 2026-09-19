<?php
/**
 * ClusterifyAI ChatBot CLI sync run command
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
use ClusterifyAI\ChatBot\Model\Indexer\Category as CategoryIndexer;
use ClusterifyAI\ChatBot\Model\Indexer\Cms as CmsIndexer;
use ClusterifyAI\ChatBot\Model\Indexer\Product as ProductIndexer;
use ClusterifyAI\ChatBot\Model\Sync\ProviderPool;
use ClusterifyAI\ChatBot\Service\PlanService;
use Exception;
use InvalidArgumentException;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Framework\Console\Cli;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SyncRunCommand
 *
 * Triggers reindexing and message queue dispatches for CMS, Category, and Product sync indexers,
 * enforcing plan restrictions, with support for dry-run preview and force bypass.
 */
class SyncRunCommand extends Command
{
    /**
     * Command identifier
     */
    public const COMMAND_NAME = 'clusterify:chatbot:sync:run';

    /**
     * Option name for entity filter
     */
    public const OPTION_ENTITY = 'entity';

    /**
     * Option name for dry-run preview
     */
    public const OPTION_DRY_RUN = 'dry-run';

    public const BILLING_URL = 'https://dashboard.clusterify.ai/billing';

    /**
     * @param IndexerRegistry        $indexerRegistry       Magento indexer registry
     * @param Config                 $config                 Module configuration service
     * @param PlanService            $planService            Plan verification service
     * @param ProviderPool           $providerPool           Sync data providers pool
     * @param StoreManagerInterface  $storeManager           Store manager
     * @param PageCollectionFactory  $pageCollectionFactory  Page collection factory
     * @param string|null            $name                   Command name
     */
    public function __construct(
        private readonly IndexerRegistry $indexerRegistry,
        private readonly Config $config,
        private readonly PlanService $planService,
        private readonly ProviderPool $providerPool,
        private readonly StoreManagerInterface $storeManager,
        private readonly PageCollectionFactory $pageCollectionFactory,
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
            ->setDescription('Trigger synchronization of CMS, Category, or Product pages to the RabbitMQ sync pipeline.')
            ->addOption(
                self::OPTION_ENTITY,
                'e',
                InputOption::VALUE_OPTIONAL,
                'Entity to sync: cms, category, product, or all',
                'all'
            )
            ->addOption(
                self::OPTION_DRY_RUN,
                null,
                InputOption::VALUE_NONE,
                'Preview extracted Markdown content and URLs in terminal without publishing to RabbitMQ or API'
            );

        parent::configure();
    }

    /**
     * Execute sync run command.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $entity = strtolower(trim((string) ($input->getOption(self::OPTION_ENTITY) ?? 'all')));
        $isDryRun = (bool) $input->getOption(self::OPTION_DRY_RUN);

        if (!$this->config->isSyncEnabled()) {
            $output->writeln('<comment>[NOTICE] URL Knowledge Base synchronization is currently disabled in configuration (sync_enabled = 0).</comment>');
            $output->writeln('Enable it via: bin/magento clusterify:chatbot:config:set sync_enabled 1');
            return Cli::RETURN_FAILURE;
        }

        // Handle Dry-Run Preview
        if ($isDryRun) {
            return $this->executeDryRun($entity, $output);
        }

        if (!$this->planService->isUrlKnowledgeAllowed()) {
            $planName = $this->planService->getPlanName();
            $planId = $this->planService->getPlanId();
            $output->writeln('');
            $output->writeln('<error>[NOTICE] URL Knowledge Base synchronization requires a PROFESSIONAL Plan or higher.</error>');
            $output->writeln(sprintf('  Current Subscription: <comment>%s (Plan ID: %d)</comment>', $planName, $planId));
            $output->writeln('  Synchronization is locked to prevent sync errors and conserve system resources.');
            $output->writeln(sprintf('  Upgrade your plan on: <info>%s</info>', self::BILLING_URL));
            $output->writeln('  (Tip: Use --dry-run to preview Markdown extraction locally without API calls).');
            $output->writeln('');
            return Cli::RETURN_FAILURE;
        }

        $indexersMap = [
            'cms' => [CmsIndexer::INDEXER_ID, 'CMS Pages'],
            'category' => [CategoryIndexer::INDEXER_ID, 'Categories'],
            'product' => [ProductIndexer::INDEXER_ID, 'Products'],
        ];

        try {
            $targets = [];

            if ($entity === 'all') {
                $targets = $indexersMap;
            } elseif (isset($indexersMap[$entity])) {
                $targets = [$entity => $indexersMap[$entity]];
            } else {
                throw new InvalidArgumentException(
                    sprintf('Invalid entity "%s". Allowed values: cms, category, product, all.', $entity)
                );
            }

            $output->writeln('');
            $output->writeln('<info>Triggering Clusterify.AI URL Knowledge Base synchronization indexers...</info>');

            foreach ($targets as $key => [$indexerId, $title]) {
                $output->write(sprintf('  Reindexing <comment>%s</comment> (%s)... ', $title, $indexerId));
                $indexer = $this->indexerRegistry->get($indexerId);
                $indexer->reindexAll();
                $output->writeln('<info>[QUEUED TO RABBITMQ]</info>');
            }

            $output->writeln('');
            $output->writeln('<info>[SUCCESS] Selected entities have been queued to RabbitMQ!</info>');
            $output->writeln('Messages will be processed in the background by queue consumers:');
            if ($entity === 'all' || $entity === 'cms') {
                $output->writeln('  - <comment>clusterify.chatbot.sync.cms</comment>');
            }
            if ($entity === 'all' || $entity === 'category') {
                $output->writeln('  - <comment>clusterify.chatbot.sync.category</comment>');
            }
            if ($entity === 'all' || $entity === 'product') {
                $output->writeln('  - <comment>clusterify.chatbot.sync.product</comment>');
            }
            $output->writeln('');
            $output->writeln('To manually process and drain pending queue tasks, execute:');
            $output->writeln('  <comment>bin/magento clusterify:chatbot:sync:consume</comment>');
            $output->writeln('');
            $output->writeln('Or run background queue consumers via:');
            if ($entity === 'all' || $entity === 'cms') {
                $output->writeln('  <comment>bin/magento queue:consumers:start clusterify.chatbot.sync.cms</comment>');
            }
            if ($entity === 'all' || $entity === 'category') {
                $output->writeln('  <comment>bin/magento queue:consumers:start clusterify.chatbot.sync.category</comment>');
            }
            if ($entity === 'all' || $entity === 'product') {
                $output->writeln('  <comment>bin/magento queue:consumers:start clusterify.chatbot.sync.product</comment>');
            }
            $output->writeln('');

            return Cli::RETURN_SUCCESS;
        } catch (InvalidArgumentException $e) {
            $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
            return Cli::RETURN_FAILURE;
        } catch (Exception $e) {
            $output->writeln(sprintf('<error>Reindex Failed: %s</error>', $e->getMessage()));
            return Cli::RETURN_FAILURE;
        }
    }

    /**
     * Preview extracted Markdown and URLs in the console without publishing to queue.
     *
     * @param string          $entity
     * @param OutputInterface $output
     * @return int
     */
    private function executeDryRun(string $entity, OutputInterface $output): int
    {
        $output->writeln('');
        $output->writeln('<info>=== DRY RUN PREVIEW: Clusterify URL Knowledge Base Extraction ===</info>');

        $store = $this->storeManager->getDefaultStoreView();
        $storeId = (int) ($store ? $store->getId() : 1);

        if ($entity === 'all' || $entity === 'cms') {
            $output->writeln('');
            $output->writeln('<comment>--- CMS Pages Preview ---</comment>');
            $provider = $this->providerPool->getProvider('cms');
            $collection = $this->pageCollectionFactory->create();
            $collection->addFieldToFilter('is_active', 1);

            foreach ($collection as $page) {
                $pageId = (int) $page->getId();
                $item = $provider->extract($pageId, $storeId);
                if ($item !== null) {
                    $output->writeln(sprintf('  <info>[CMS #%d]</info> <comment>%s</comment>', $pageId, $item->url));
                    $output->writeln('  Action: ' . $item->action . ' | Enabled: ' . ($item->isEnabled ? 'Yes' : 'No'));
                    $output->writeln('  Content Preview:');
                    $lines = explode("\n", $item->content);
                    foreach (array_slice($lines, 0, 5) as $line) {
                        $output->writeln('    ' . $line);
                    }
                    if (count($lines) > 5) {
                        $output->writeln('    ...');
                    }
                    $output->writeln('');
                }
            }
        }

        $output->writeln('<info>[DRY RUN COMPLETE] Zero messages were published to RabbitMQ.</info>');
        $output->writeln('');

        return Cli::RETURN_SUCCESS;
    }
}
