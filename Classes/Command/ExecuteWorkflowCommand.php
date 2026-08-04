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
use AutoDudes\AiSuite\Service\FolderSelectionService;
use AutoDudes\AiSuite\Service\LibraryService;
use AutoDudes\AiSuite\Service\SiteService;
use AutoDudes\AiSuite\Service\WorkflowProcessingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'ai-suite:execute-workflow',
    description: 'Executes an AI Suite workflow (metadata generation or translation) and stores tasks for later CLI processing.',
)]
class ExecuteWorkflowCommand extends Command
{
    use CliBackendBootstrapTrait;

    public function __construct(
        protected readonly WorkflowProcessingService $workflowProcessingService,
        protected readonly LibraryService $libraryService,
        protected readonly SiteService $siteService,
    ) {
        parent::__construct();
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->initializeFakeRequest();
        $this->initializeBackendAuthentication();
    }

    protected function configure(): void
    {
        $this
            ->addOption('type', 't', InputOption::VALUE_OPTIONAL, 'Type of workflow ('.implode(', ', array_keys(WorkflowProcessingService::WORKFLOW_TYPES)).')')
            ->addOption('model', 'm', InputOption::VALUE_OPTIONAL, 'AI model identifier to use')
            ->addOption('start-from-pid', null, InputOption::VALUE_OPTIONAL, 'Starting page ID (for page-based types)')
            ->addOption('page-type', null, InputOption::VALUE_OPTIONAL, 'Page type filter')
            ->addOption('depth', null, InputOption::VALUE_OPTIONAL, 'Traversal depth. For page based types the page tree depth, for file based types the number of sub folder levels below the given directories (0 = only the given directories, max 5)')
            ->addOption('column', null, InputOption::VALUE_OPTIONAL, 'Column to process')
            ->addOption('sys-language', null, InputOption::VALUE_OPTIONAL, 'System language (locale__id)')
            ->addOption('show-only-empty', null, InputOption::VALUE_NONE, 'Show only empty fields')
            ->addOption('source-language', null, InputOption::VALUE_OPTIONAL, 'Source language for translation (locale__id)')
            ->addOption('target-language', null, InputOption::VALUE_OPTIONAL, 'Target language for translation (locale__id)')
            ->addOption('translation-scope', null, InputOption::VALUE_OPTIONAL, 'Translation scope (all, metadata, content)')
            ->addOption('directory', null, InputOption::VALUE_OPTIONAL, 'Directories to process, comma separated for more than one. Accepts either a path relative to the default file storage root (e.g. "/", "/user_upload/", "/user_upload/images/") or a combined identifier targeting a specific storage (e.g. "1:/user_upload/"), for example "/user_upload/,1:/images/". Use a leading and trailing slash; pass "/" or leave empty to use the default storage root.')
            ->addOption('show-only-used', null, InputOption::VALUE_NONE, 'Show only used files')
            ->addOption('prompt', null, InputOption::VALUE_OPTIONAL, 'Own prompt for metadata generation. Replaces the predefined instruction of the selected field.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Execute AI Suite workflow');

        $config = $this->collectWorkflowConfiguration($input, $io);
        if (null === $config) {
            return Command::FAILURE;
        }

        $this->addTypeSpecificFilters($config['type'], $input, $io, $config);
        $config['customPrompt'] = trim((string) ($input->getOption('prompt') ?? ''));

        $io->text('Running workflow with configuration:');
        $io->table(
            ['Configuration', 'Value'],
            array_map(
                static fn ($key, $value): array => [
                    $key,
                    is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value,
                ],
                array_keys($config),
                $config,
            ),
        );

        $result = match ($config['type']) {
            'page' => $this->workflowProcessingService->prepareAndExecutePagesMetadataWorkflow($config),
            'pageTranslate' => $this->workflowProcessingService->prepareAndExecutePageTranslationWorkflow($config),
            'fileReferences' => $this->workflowProcessingService->prepareAndExecuteFileReferencesMetadataWorkflow($config),
            'fileMetadata' => $this->workflowProcessingService->prepareAndExecuteFileMetadataWorkflow($config),
            'fileMetadataTranslation' => $this->workflowProcessingService->prepareAndExecuteFileMetadataTranslationWorkflow($config),
            default => ['success' => false, 'message' => 'Unsupported workflow type: '.$config['type']],
        };

        if ($result['success']) {
            $io->success($result['message']);

            return Command::SUCCESS;
        }

        $io->error($result['message']);

        return Command::FAILURE;
    }

    /**
     * @return null|array<string, mixed>
     */
    private function collectWorkflowConfiguration(InputInterface $input, SymfonyStyle $io): ?array
    {
        $type = $this->resolveType($input, $io);
        if (null === $type) {
            return null;
        }
        $config = ['type' => $type];

        $model = $input->getOption('model');
        if (null !== $model) {
            $config['model'] = $model;

            return $config;
        }

        try {
            $availableModels = $this->libraryService->findModelsForWorkflowType($type);
        } catch (\RuntimeException $e) {
            $io->error('Error fetching available models: '.$e->getMessage());

            return null;
        }

        if (empty($availableModels)) {
            $io->error('No models available for type: '.$type);

            return null;
        }

        $config['model'] = $io->choice('Please select a model', $availableModels);

        return $config;
    }

    private function resolveType(InputInterface $input, SymfonyStyle $io): ?string
    {
        $type = $input->getOption('type');
        if (null !== $type) {
            if (!array_key_exists($type, WorkflowProcessingService::WORKFLOW_TYPES)) {
                $io->error('Invalid type. Valid types are: '.implode(', ', array_keys(WorkflowProcessingService::WORKFLOW_TYPES)));

                return null;
            }

            return $type;
        }

        $selectedLabel = $io->choice('Please select a workflow type', WorkflowProcessingService::WORKFLOW_TYPES);
        $key = array_search($selectedLabel, WorkflowProcessingService::WORKFLOW_TYPES, true);

        return false === $key ? $selectedLabel : (string) $key;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function addTypeSpecificFilters(string $type, InputInterface $input, SymfonyStyle $io, array &$config): void
    {
        match ($type) {
            'page' => $this->addPageFilters($input, $io, $config),
            'pageTranslate' => $this->addPageTranslateFilters($input, $io, $config),
            'fileReferences' => $this->addFileReferencesFilters($input, $io, $config),
            'fileMetadata' => $this->addFileMetadataFilters($input, $io, $config),
            'fileMetadataTranslation' => $this->addFileMetadataTranslationFilters($input, $io, $config),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $config
     */
    private function addPageFilters(InputInterface $input, SymfonyStyle $io, array &$config): void
    {
        $config['startFromPid'] = $this->resolveIntegerOption($input, $io, 'start-from-pid', 'Start from page ID', 1);
        $config['pageType'] = $this->resolveChoiceKey($input, $io, 'page-type', 'Page type filter', $this->workflowProcessingService->getAvailablePageTypes());
        $config['depth'] = $this->resolveIntegerOption($input, $io, 'depth', 'Depth for page traversal', 0);
        $config['column'] = $this->resolveChoiceKey($input, $io, 'column', 'Column to process', [
            'seo_title' => 'Title',
            'description' => 'Description',
            'og_title' => 'OG Title',
            'og_description' => 'OG Description',
            'twitter_title' => 'Twitter Title',
            'twitter_description' => 'Twitter Description',
        ]);
        $config['sysLanguage'] = $this->resolveLanguageOption($input, $io, 'sys-language', 'System language', $config['startFromPid']);
        $config['showOnlyEmpty'] = $this->resolveBooleanOption($input, $io, 'show-only-empty', 'Consider only empty fields?');
    }

    /**
     * @param array<string, mixed> $config
     */
    private function addPageTranslateFilters(InputInterface $input, SymfonyStyle $io, array &$config): void
    {
        $config['startFromPid'] = $this->resolveIntegerOption($input, $io, 'start-from-pid', 'Start from page ID', 1);
        $config['pageType'] = $this->resolveChoiceKey($input, $io, 'page-type', 'Page type filter', $this->workflowProcessingService->getAvailablePageTypes());
        $config['depth'] = $this->resolveIntegerOption($input, $io, 'depth', 'Depth for page traversal', 0);
        $config['sourceLanguage'] = $this->resolveLanguageOption($input, $io, 'source-language', 'Source language for translation', $config['startFromPid'], onlyDefault: true);
        $config['targetLanguage'] = $this->resolveLanguageOption($input, $io, 'target-language', 'Target language for translation', $config['startFromPid'], excludeKeys: [$config['sourceLanguage']]);
        $config['translationScope'] = $this->resolveOptionalStringOption($input, $io, 'translation-scope', 'Translation scope', 'all') ?? 'all';
    }

    /**
     * @param array<string, mixed> $config
     */
    private function addFileReferencesFilters(InputInterface $input, SymfonyStyle $io, array &$config): void
    {
        $config['startFromPid'] = $this->resolveIntegerOption($input, $io, 'start-from-pid', 'Start from page ID', 1);
        $config['depth'] = $this->resolveIntegerOption($input, $io, 'depth', 'Depth for page traversal', 0);
        $config['column'] = $this->resolveChoiceKey($input, $io, 'column', 'Column to process', [
            'title' => 'Title',
            'alternative' => 'Alternative Text',
            'description' => 'Description',
        ]);
        $config['sysLanguage'] = $this->resolveLanguageOption($input, $io, 'sys-language', 'System language', $config['startFromPid']);
        $config['showOnlyEmpty'] = $this->resolveBooleanOption($input, $io, 'show-only-empty', 'Show only empty fields?');
    }

    /**
     * @param array<string, mixed> $config
     */
    private function addFileMetadataFilters(InputInterface $input, SymfonyStyle $io, array &$config): void
    {
        $config['directory'] = $this->resolveOptionalStringOption($input, $io, 'directory', 'Directories to process, comma separated for more than one — either a path relative to the default storage root (e.g. "/user_upload/") or a combined identifier targeting a specific storage (e.g. "1:/user_upload/"). Press Enter to use the default storage root') ?? '';
        $config['depth'] = $this->resolveIntegerOption($input, $io, 'depth', 'Sub folder levels below the given directories (0 = only the given directories, max '.FolderSelectionService::MAX_DEPTH.')', 0);
        $config['column'] = $this->resolveChoiceKey($input, $io, 'column', 'Column to process', [
            'title' => 'Title',
            'alternative' => 'Alternative Text',
            'description' => 'Description',
        ]);
        $config['sysLanguage'] = $this->resolveLanguageOption($input, $io, 'sys-language', 'System language');
        $config['showOnlyEmpty'] = $this->resolveBooleanOption($input, $io, 'show-only-empty', 'Show only empty fields?');
        $config['showOnlyUsed'] = $this->resolveBooleanOption($input, $io, 'show-only-used', 'Show only used files?');
    }

    /**
     * @param array<string, mixed> $config
     */
    private function addFileMetadataTranslationFilters(InputInterface $input, SymfonyStyle $io, array &$config): void
    {
        $config['directory'] = $this->resolveOptionalStringOption($input, $io, 'directory', 'Directories to process, comma separated for more than one — either a path relative to the default storage root (e.g. "/user_upload/") or a combined identifier targeting a specific storage (e.g. "1:/user_upload/"). Press Enter to use the default storage root') ?? '';
        $config['depth'] = $this->resolveIntegerOption($input, $io, 'depth', 'Sub folder levels below the given directories (0 = only the given directories, max '.FolderSelectionService::MAX_DEPTH.')', 0);
        $config['column'] = $this->resolveChoiceKey($input, $io, 'column', 'Column to process', [
            'title' => 'Title',
            'alternative' => 'Alternative Text',
            'description' => 'Description',
            'all' => 'All Columns',
        ]);
        $config['sourceLanguage'] = $this->resolveLanguageOption($input, $io, 'source-language', 'Source language for translation');
        $config['targetLanguage'] = $this->resolveLanguageOption($input, $io, 'target-language', 'Target language for translation', excludeKeys: [$config['sourceLanguage']]);
        $config['showOnlyUsed'] = $this->resolveBooleanOption($input, $io, 'show-only-used', 'Show only used files?');
    }

    private function resolveIntegerOption(InputInterface $input, SymfonyStyle $io, string $optionName, string $question, int $default): int
    {
        $value = $input->getOption($optionName);
        if (null !== $value && false !== $value && '' !== $value) {
            return (int) $value;
        }

        return (int) ($io->ask(
            $question.' (press Enter to use default '.$default.')',
            (string) $default,
            $this->integerValidator(...),
        ) ?? $default);
    }

    private function resolveBooleanOption(InputInterface $input, SymfonyStyle $io, string $optionName, string $question): bool
    {
        if ($input->getOption($optionName)) {
            return true;
        }

        return $io->confirm($question, false);
    }

    private function resolveOptionalStringOption(InputInterface $input, SymfonyStyle $io, string $optionName, string $question, ?string $default = null): ?string
    {
        $value = $input->getOption($optionName);
        if (null !== $value && '' !== $value) {
            return $value;
        }
        $value = $io->ask($question, $default);

        return (null !== $value && '' !== $value) ? $value : $default;
    }

    /**
     * @param array<int|string, string> $choices
     */
    private function resolveChoiceKey(InputInterface $input, SymfonyStyle $io, string $optionName, string $question, array $choices): string
    {
        $value = $input->getOption($optionName);
        if (null !== $value && '' !== $value) {
            return (string) $value;
        }
        $selected = $io->choice($question, $choices);
        if (!array_key_exists($selected, $choices)) {
            $key = array_search($selected, $choices, true);
            if (false !== $key) {
                $selected = (string) $key;
            }
        }

        return (string) $selected;
    }

    /**
     * @param list<string> $excludeKeys
     */
    private function resolveLanguageOption(
        InputInterface $input,
        SymfonyStyle $io,
        string $optionName,
        string $question,
        ?int $pid = null,
        array $excludeKeys = [],
        bool $onlyDefault = false,
    ): string {
        $value = $input->getOption($optionName);
        if (null !== $value && '' !== $value) {
            return $value;
        }
        $allLangs = null !== $pid
            ? $this->siteService->getAvailableLanguages(true, $pid, $onlyDefault)
            : $this->siteService->getAvailableLanguages(true, 0, $onlyDefault);
        if (!empty($excludeKeys)) {
            $allLangs = array_diff_key($allLangs, array_flip($excludeKeys));
        }

        return (string) $io->choice($question, $allLangs);
    }

    private function integerValidator(?string $answer): string
    {
        if (null === $answer || !is_numeric($answer)) {
            throw new \RuntimeException('Enter a valid number.');
        }

        return $answer;
    }
}
