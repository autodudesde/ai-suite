<?php

declare(strict_types=1);

/*
 *
 * This file is part of the "ai_suite" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *
 */

namespace AutoDudes\AiSuite\Command;

use AutoDudes\AiSuite\Command\Trait\CliBackendBootstrapTrait;
use AutoDudes\AiSuite\Service\BackgroundTaskService;
use AutoDudes\AiSuite\Service\LibraryService;
use AutoDudes\AiSuite\Service\WorkflowProcessingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'ai-suite:retry-tasks',
    description: 'Retries CLI background tasks. Currently limited to tasks with status: task-error.',
)]
class RetryTasksCommand extends Command
{
    use CliBackendBootstrapTrait;

    private const TYPE_ALL = 'all';

    public function __construct(
        protected readonly BackgroundTaskService $backgroundTaskService,
        protected readonly LibraryService $libraryService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'type',
                't',
                InputOption::VALUE_OPTIONAL,
                'Filter by task type ('.implode(', ', array_keys(WorkflowProcessingService::WORKFLOW_TYPES)).')'
            )
            ->addOption(
                'model',
                'm',
                InputOption::VALUE_OPTIONAL,
                'Override the AI model used for the retried tasks (uses the task\'s original model when omitted)'
            )
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->initializeFakeRequest();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Retry failed background tasks');

        $config = ['status' => 'failed'];
        $type = $this->resolveType($input, $io);
        if (false === $type) {
            return Command::FAILURE;
        }
        if (null !== $type) {
            $config['type'] = $type;
        }
        $model = $this->resolveModel($input, $io, $type);
        if (null !== $model) {
            $config['model'] = $model;
        }

        $result = $this->backgroundTaskService->retryFailedTasks($config);

        if ($result['success']) {
            $io->success($result['message']);

            return Command::SUCCESS;
        }

        $io->error($result['message']);

        return Command::FAILURE;
    }

    private function resolveType(InputInterface $input, SymfonyStyle $io): false|string|null
    {
        $type = $input->getOption('type');
        if (null !== $type) {
            if (!array_key_exists($type, WorkflowProcessingService::WORKFLOW_TYPES)) {
                $io->error('Invalid type. Valid types are: '.implode(', ', array_keys(WorkflowProcessingService::WORKFLOW_TYPES)));

                return false;
            }

            return $type;
        }

        if (!$input->isInteractive()) {
            return null;
        }

        $choices = [self::TYPE_ALL => 'All task types'] + WorkflowProcessingService::WORKFLOW_TYPES;
        $selected = (string) $io->choice('Filter by task type', $choices, $choices[self::TYPE_ALL]);
        // ChoiceQuestion returns the key for associative arrays.
        if (self::TYPE_ALL === $selected) {
            return null;
        }

        return $selected;
    }

    private function resolveModel(InputInterface $input, SymfonyStyle $io, ?string $type): ?string
    {
        $model = $input->getOption('model');
        if (null !== $model && '' !== $model) {
            return (string) $model;
        }

        if (!$input->isInteractive()) {
            return null;
        }

        if (!$io->confirm('Override the AI model used for the retried tasks?', false)) {
            return null;
        }

        if (null !== $type) {
            try {
                $availableModels = $this->libraryService->findModelsForWorkflowType($type);
            } catch (\RuntimeException $e) {
                $io->warning('Could not fetch available models: '.$e->getMessage());
                $availableModels = [];
            }
            if (!empty($availableModels)) {
                return (string) $io->choice('Select model', $availableModels);
            }
        }

        $answer = $io->ask('Enter model identifier (e.g. "anthropic-3.5")');

        return (null === $answer || '' === $answer) ? null : (string) $answer;
    }
}
