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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'ai-suite:process-tasks',
    description: 'Polls the AI server for status of CLI-handled background tasks and persists finished results.',
)]
class ProcessTasksCommand extends Command
{
    use CliBackendBootstrapTrait;

    public function __construct(
        protected readonly BackgroundTaskService $backgroundTaskService,
    ) {
        parent::__construct();
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->initializeFakeRequest();
        $this->initializeBackendAuthentication();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Task status update');

        $result = $this->backgroundTaskService->updateAllTaskStatuses();

        if ($result['success']) {
            $io->success($result['message']);

            return Command::SUCCESS;
        }

        $io->error($result['message']);

        return Command::FAILURE;
    }
}
