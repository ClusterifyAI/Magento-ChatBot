<?php
/**
 * ClusterifyAI ChatBot CLI sync consume command
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
use ClusterifyAI\ChatBot\Model\Queue\QueueProcessor;
use ClusterifyAI\ChatBot\Service\PlanService;
use Exception;
use InvalidArgumentException;
use Magento\Framework\App\Area;
use Magento\Framework\App\State as AppState;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SyncConsumeCommand
 *
 * CLI command to immediately process pending RabbitMQ tasks and terminate cleanly when finished.
 */
class SyncConsumeCommand extends Command
{
    public const COMMAND_NAME = 'clusterify:chatbot:sync:consume';
    public const OPTION_ENTITY = 'entity';
    public const OPTION_LIMIT = 'limit';

    /**
     * @param QueueProcessor $queueProcessor Queue processor service
     * @param Config         $config         Configuration service
     * @param PlanService    $planService    Plan verification service
     * @param AppState       $appState       Application state
     */
    public function __construct(
        private readonly QueueProcessor $queueProcessor,
        private readonly Config $config,
        private readonly PlanService $planService,
        private readonly AppState $appState
    ) {
        parent::__construct();
    }

    /**
     * Configure command options.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription('Process pending RabbitMQ synchronization tasks and exit when completed.')
            ->addOption(
                self::OPTION_ENTITY,
                'e',
                InputOption::VALUE_OPTIONAL,
                'Entity queue to process: cms, category, product, or all',
                'all'
            )
            ->addOption(
                self::OPTION_LIMIT,
                'l',
                InputOption::VALUE_OPTIONAL,
                'Maximum number of messages to process per queue',
                '50'
            );

        parent::configure();
    }

    /**
     * Execute consume command.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_FRONTEND);
        } catch (LocalizedException $e) {
            // Area code already initialized
        }

        $entity = strtolower(trim((string) ($input->getOption(self::OPTION_ENTITY) ?? 'all')));
        $limit = max(1, (int) ($input->getOption(self::OPTION_LIMIT) ?? 50));

        if (!$this->config->isEnabled() || !$this->config->isSyncEnabled()) {
            $output->writeln('<error>URL Knowledge Base synchronization is currently disabled in configuration.</error>');
            return Command::FAILURE;
        }

        if (!$this->planService->isUrlKnowledgeAllowed()) {
            $output->writeln('<error>URL Knowledge Base synchronization is locked on your current plan.</error>');
            return Command::FAILURE;
        }

        $validEntities = ['all', 'cms', 'category', 'product'];
        if (!in_array($entity, $validEntities, true)) {
            $output->writeln(sprintf('<error>Invalid entity "%s". Allowed values: all, cms, category, product.</error>', $entity));
            return Command::FAILURE;
        }

        $output->writeln('');
        $output->writeln('<info>Processing pending Clusterify.AI RabbitMQ queue tasks...</info>');

        try {
            if ($entity === 'all') {
                $results = $this->queueProcessor->processAllQueues($limit);
                $total = array_sum($results);

                $output->writeln(sprintf('  - CMS Pages Queue:    <comment>%d</comment> tasks processed', $results['cms'] ?? 0));
                $output->writeln(sprintf('  - Categories Queue:   <comment>%d</comment> tasks processed', $results['category'] ?? 0));
                $output->writeln(sprintf('  - Products Queue:     <comment>%d</comment> tasks processed', $results['product'] ?? 0));
                $output->writeln('');
                $output->writeln(sprintf('<info>[SUCCESS] Total tasks processed: %d</info>', $total));
            } else {
                $count = $this->queueProcessor->processQueue($entity, $limit);
                $output->writeln(sprintf('  - %s Queue: <comment>%d</comment> tasks processed', ucfirst($entity), $count));
                $output->writeln('');
                $output->writeln(sprintf('<info>[SUCCESS] Total tasks processed: %d</info>', $count));
            }

            return Command::SUCCESS;
        } catch (Exception $e) {
            $output->writeln(sprintf('<error>Error during queue processing: %s</error>', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}
