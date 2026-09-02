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

namespace AutoDudes\AiSuite\Service;

use AutoDudes\AiSuite\Enumeration\GenerationLibraryEnumeration;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\SingletonInterface;

class LibraryService implements SingletonInterface
{
    /**
     * @var list<string>
     */
    private const VISION_MODEL_IDENTIFIERS = ['Vision', 'MittwaldMinistral14BVision'];

    public function __construct(
        protected readonly BackendUserService $backendUserService,
        protected readonly SendRequestService $sendRequestService,
        protected readonly LoggerInterface $logger,
    ) {}

    /**
     * @param array<array-key, mixed> $libraries
     *
     * @return list<array<string, mixed>>
     */
    public function prepareLibraries(array $libraries, string $selectedLibraryKey = ''): array
    {
        $processedLibraries = [];

        foreach ($libraries as $library) {
            if ('' === (string) ($library['model_identifier'] ?? '')) {
                $this->logger->warning('Skipping a generation library without a model identifier', [
                    'library' => $library,
                ]);

                continue;
            }
            if (!($this->backendUserService->getBackendUser()?->isAdmin() ?? false)
                && !$this->backendUserService->checkPermissions('tx_aisuite_models:'.$library['model_identifier'])
            ) {
                continue;
            }
            if ($library['model_identifier'] === $selectedLibraryKey) {
                $library['checked'] = true;
            } else {
                $library['checked'] = false;
            }
            $processedLibraries[] = $library;
        }
        if (empty($selectedLibraryKey) && count($processedLibraries) > 0) {
            $processedLibraries[0]['checked'] = true;
        }

        return $processedLibraries;
    }

    /**
     * @param array<string, mixed> $libraries
     *
     * @return array<string, mixed>
     */
    public function filterVisionLibraries(array $libraries): array
    {
        return array_filter($libraries, static fn (array $library): bool => self::isVisionLibrary($library));
    }

    /**
     * @param array<string, mixed> $libraries
     *
     * @return array<string, mixed>
     */
    public function filterNonVisionLibraries(array $libraries): array
    {
        return array_filter($libraries, static fn (array $library): bool => !self::isVisionLibrary($library));
    }

    /**
     * @param array<string, mixed> $library
     */
    public static function isVisionLibrary(array $library): bool
    {
        return in_array((string) ($library['model_identifier'] ?? ''), self::VISION_MODEL_IDENTIFIERS, true);
    }

    /**
     * @return array<string, string>
     *
     * @throws \RuntimeException
     */
    public function findModelsForWorkflowType(string $workflowType): array
    {
        $resolved = match (true) {
            in_array($workflowType, ['page', 'fileMetadata', 'fileReferences'], true) => [
                GenerationLibraryEnumeration::METADATA,
                'createMetadata',
            ],
            in_array($workflowType, ['pageTranslate', 'fileMetadataTranslation'], true) => [
                GenerationLibraryEnumeration::TRANSLATE,
                'translate',
            ],
            default => null,
        };

        if (null === $resolved) {
            return [];
        }
        [$libraryType, $action] = $resolved;

        $librariesAnswer = $this->sendRequestService->sendLibrariesRequest($libraryType, $action, ['text']);
        if ('Error' === $librariesAnswer->getType()) {
            $message = $librariesAnswer->getMessage() ?: 'Unknown error fetching available models.';

            throw new \RuntimeException($message);
        }

        $libraries = $librariesAnswer->getResponseData()['textGenerationLibraries'] ?? [];

        if ('fileReferences' === $workflowType || 'fileMetadata' === $workflowType) {
            $libraries = $this->filterVisionLibraries($libraries);
        } elseif ('page' === $workflowType) {
            $libraries = $this->filterNonVisionLibraries($libraries);
        }

        return array_column($libraries, 'name', 'model_identifier');
    }

    /**
     * @return array<string, mixed>
     */
    public function prepareAdditionalImageSettings(string $additionalImageSettings): array
    {
        $additionalImageSettingsArray = explode(' ', $additionalImageSettings);
        $additionalImageSettingsArray = array_filter($additionalImageSettingsArray);
        $returnArray = [];
        $activeKey = '';
        foreach ($additionalImageSettingsArray as $value) {
            if (str_contains($value, '--')) {
                $returnArray[substr($value, 2)] = '';
                $activeKey = substr($value, 2);
            }
            if ('' !== $activeKey && !str_contains($value, '--')) {
                $returnArray[$activeKey] .= $value;
            }
        }
        $returnArray['v'] ??= '';
        $returnArray['ar'] ??= '';
        $returnArray['no'] ??= 'text';
        $returnArray['sref'] ??= '';

        return $returnArray;
    }
}
