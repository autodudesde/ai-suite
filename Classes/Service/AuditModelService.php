<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

use AutoDudes\AiSuite\Enumeration\GenerationLibraryEnumeration;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

class AuditModelService
{
    public function __construct(
        protected readonly SendRequestService $sendRequestService,
        protected readonly LibraryService $libraryService,
        protected readonly ExtensionConfiguration $extensionConfiguration,
    ) {}

    public function configuredModel(): string
    {
        try {
            return trim((string) ($this->extensionConfiguration->get('ai_suite')['auditDefaultTextModel'] ?? ''));
        } catch (\Throwable) {
            return '';
        }
    }

    public function defaultTextModel(): string
    {
        $configured = $this->configuredModel();
        if ('' !== $configured) {
            return $configured;
        }

        $librariesAnswer = $this->sendRequestService->sendLibrariesRequest(GenerationLibraryEnumeration::METADATA, 'createMetadata', ['text']);
        if ('Error' === $librariesAnswer->getType()) {
            return '';
        }

        $textLibraries = $librariesAnswer->getResponseData()['textGenerationLibraries'] ?? [];

        return $this->textModelFrom(\is_array($textLibraries) ? $textLibraries : []);
    }

    /**
     * @param array<array-key, mixed> $textGenerationLibraries
     */
    public function textModelFrom(array $textGenerationLibraries): string
    {
        $configured = $this->configuredModel();
        if ('' !== $configured) {
            return $configured;
        }

        $libraries = $this->libraryService->prepareLibraries(array_values(array_filter(
            $textGenerationLibraries,
            static fn (mixed $library): bool => \is_array($library) && !LibraryService::isVisionLibrary($library)
        )));
        $model = '';
        foreach ($libraries as $library) {
            $model = (string) ($library['model_identifier'] ?? '');
            if ($library['checked'] ?? false) {
                break;
            }
        }

        return $model;
    }
}
