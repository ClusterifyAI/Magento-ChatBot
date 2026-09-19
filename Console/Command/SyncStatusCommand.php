<?php
/**
 * ClusterifyAI ChatBot CLI sync status command
 *
 * @category  ClusterifyAI
 * @package   ClusterifyAI_ChatBot
 * @author    Clusterify AI <support@clusterify.ai>
 * @copyright Copyright (c) 2026 Clusterify AI (https://clusterify.ai)
 * @license   MIT License
 */

declare(strict_types=1);

namespace ClusterifyAI\ChatBot\Console\Command;

use Clusterify\Exceptions\ClusterifyException;
use ClusterifyAI\ChatBot\Model\Config;
use ClusterifyAI\ChatBot\Model\Indexer\Category as CategoryIndexer;
use ClusterifyAI\ChatBot\Model\Indexer\Cms as CmsIndexer;
use ClusterifyAI\ChatBot\Model\Indexer\Product as ProductIndexer;
use ClusterifyAI\ChatBot\Service\ClientFactory;
use ClusterifyAI\ChatBot\Service\PlanService;
use Exception;
use Magento\Framework\Console\Cli;
use Magento\Framework\Indexer\IndexerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SyncStatusCommand
 *
 * Displays current URL Knowledge Base synchronization status, indexer states,
 * and live Clusterify API URL quota usage.
 */
class SyncStatusCommand extends Command
{
    /**
     * Command identifier
     */
    public const COMMAND_NAME = 'clusterify:chatbot:sync:status';

    /**
     * Option name for output format
     */
    public const OPTION_FORMAT = 'format';

    /**
     * @param IndexerRegistry $indexerRegistry Magento indexer registry
     * @param Config          $config          Module configuration service
     * @param ClientFactory   $clientFactory   Clusterify SDK client factory
     * @param PlanService     $planService     Plan verification service
     * @param string|null     $name            Command name
     */
    public function __construct(
        private readonly IndexerRegistry $indexerRegistry,
        private readonly Config $config,
        private readonly ClientFactory $clientFactory,
        private readonly PlanService $planService,
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
            ->setDescription('Display URL Knowledge Base synchronization settings, indexer states, and API quota.')
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
     * Execute sync status command.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = strtolower(trim((string) $input->getOption(self::OPTION_FORMAT)));

        $indexers = [
            CmsIndexer::INDEXER_ID => 'CMS Pages Indexer',
            CategoryIndexer::INDEXER_ID => 'Categories Indexer',
            ProductIndexer::INDEXER_ID => 'Products Indexer',
        ];

        $indexerRows = [];
        $jsonIndexers = [];

        foreach ($indexers as $id => $title) {
            try {
                $indexer = $this->indexerRegistry->get($id);
                $status = $indexer->getStatus();
                $isScheduled = $indexer->isScheduled();
                $latestUpdated = (string) $indexer->getLatestUpdated();

                $statusLabel = match ($status) {
                    \Magento\Framework\Indexer\StateInterface::STATUS_VALID => 'Ready',
                    \Magento\Framework\Indexer\StateInterface::STATUS_INVALID => 'Needs Reindex',
                    \Magento\Framework\Indexer\StateInterface::STATUS_WORKING => 'Processing',
                    default => 'Unknown',
                };

                $indexerRows[] = [
                    $title,
                    $id,
                    $statusLabel,
                    $isScheduled ? 'Update by Schedule (Cron)' : 'Update on Save',
                    $latestUpdated !== '' ? $latestUpdated : 'Never',
                ];

                $jsonIndexers[$id] = [
                    'title' => $title,
                    'status' => $statusLabel,
                    'is_scheduled' => $isScheduled,
                    'last_updated' => $latestUpdated,
                ];
            } catch (Exception $e) {
                $indexerRows[] = [$title, $id, 'Error loading indexer', 'N/A', 'N/A'];
            }
        }

        // Fetch live Clusterify API stats if credentials are configured
        $quotaStats = 'Unable to check (Credentials not configured)';
        $jsonQuota = null;

        if ($this->config->getPublicKey() !== '' && $this->config->getSecretKey() !== '') {
            try {
                $client = $this->clientFactory->create();
                $stats = $client->knowledgeUrl()->stats();
                $planName = $this->planService->getPlanName();
                $quotaStats = sprintf('%d / %d URLs used (%s)', $stats->total, $stats->maxUrls, $planName);
                $jsonQuota = [
                    'used_urls' => $stats->total,
                    'max_urls' => $stats->maxUrls,
                    'plan_name' => $planName,
                ];
            } catch (ClusterifyException $e) {
                $quotaStats = sprintf('API Check Failed: %s', $e->getMessage());
            } catch (Exception $e) {
                $quotaStats = sprintf('API Check Failed: %s', $e->getMessage());
            }
        }

        if ($format === 'json') {
            $payload = [
                'sync_settings' => [
                    'master_sync_enabled' => $this->config->isSyncEnabled(),
                    'sync_cms_pages' => $this->config->isCmsSyncEnabled(),
                    'sync_category_pages' => $this->config->isCategorySyncEnabled(),
                    'sync_product_pages' => $this->config->isProductSyncEnabled(),
                    'in_stock_products_only' => $this->config->isInStockOnlySyncEnabled(),
                ],
                'quota' => $jsonQuota,
                'indexers' => $jsonIndexers,
            ];
            $output->writeln((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Cli::RETURN_SUCCESS;
        }

        $output->writeln('');
        $output->writeln('<info>Clusterify.AI URL Knowledge Base Synchronization Status</info>');
        $output->writeln('');

        // Settings table
        $settingsTable = new Table($output);
        $settingsTable->setHeaders(['Sync Setting', 'Status']);
        $settingsTable->addRows([
            ['Master Knowledge Base Sync', $this->config->isSyncEnabled() ? '<info>Enabled</info>' : '<comment>Disabled</comment>'],
            ['CMS Pages Sync', $this->config->isCmsSyncEnabled() ? '<info>Enabled</info>' : '<comment>Disabled</comment>'],
            ['Category Pages Sync', $this->config->isCategorySyncEnabled() ? '<info>Enabled</info>' : '<comment>Disabled</comment>'],
            ['Product Pages Sync', $this->config->isProductSyncEnabled() ? '<info>Enabled</info>' : '<comment>Disabled</comment>'],
            ['Filter In-Stock Only', $this->config->isInStockOnlySyncEnabled() ? '<info>Yes</info>' : '<comment>No</comment>'],
            ['Clusterify API Quota', $quotaStats],
        ]);
        $settingsTable->render();

        $output->writeln('');
        $output->writeln('<info>Dedicated Sync Indexers</info>');
        $indexTable = new Table($output);
        $indexTable->setHeaders(['Indexer Name', 'Indexer Code', 'Status', 'Mode', 'Last Updated']);
        $indexTable->setRows($indexerRows);
        $indexTable->render();
        $output->writeln('');

        return Cli::RETURN_SUCCESS;
    }
}
