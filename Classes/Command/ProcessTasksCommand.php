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
use AutoDudes\AiSuite\Service\SystemDomainResolver;
use Psr\Log\LoggerInterface;
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
        protected readonly SystemDomainResolver $systemDomainResolver,
        protected readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->initializeFakeRequest($this->systemDomainResolver->resolveBaseUrl());
        $this->initializeBackendAuthentication();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Task status update');

        try {
            $result = $this->backgroundTaskService->updateAllTaskStatuses();
        } catch (\Throwable $e) {
            $this->logger->error('Task status update aborted', ['exception' => $e]);
            $io->error(sprintf('Task status update aborted with %s: %s', $e::class, $e->getMessage()));

            return Command::FAILURE;
        }

        if ($result['success']) {
            $io->success($result['message']);

            return Command::SUCCESS;
        }

        $io->error($result['message']);

        return Command::FAILURE;
    }
}
