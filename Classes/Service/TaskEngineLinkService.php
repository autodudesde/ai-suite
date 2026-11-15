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

use TYPO3\CMS\Backend\Routing\UriBuilder;

class TaskEngineLinkService
{
    public const FILTER_PAGE = 'Page';
    public const FILTER_FILE_REFERENCE = 'FileReference';
    public const FILTER_FILE_METADATA = 'FileMetadata';
    public const FILTER_FILE_METADATA_TRANSLATION = 'FileMetadataTranslation';

    private const ROUTE = 'ai_suite_backgroundtask';
    private const PERMISSION = 'tx_aisuite_features:enable_background_task_handling';

    public function __construct(
        private readonly BackendUserService $backendUserService,
        private readonly UriBuilder $uriBuilder,
    ) {}

    /**
     * @param array<int, array<string, mixed>> $entries
     *
     * @return array<int, string>
     */
    public function buildLinks(array $entries, string $filter): array
    {
        if ([] === $entries || !$this->backendUserService->checkPermissions(self::PERMISSION)) {
            return [];
        }

        $latestPerUid = [];
        foreach ($entries as $entry) {
            $latestPerUid[(int) $entry['table_uid']] = $entry;
        }

        $links = [];
        foreach ($latestPerUid as $uid => $entry) {
            $uuid = (string) ($entry['uuid'] ?? '');
            if ('finished' !== ($entry['status'] ?? '') || '' === $uuid) {
                continue;
            }
            $links[$uid] = (string) $this->uriBuilder->buildUriFromRoute(self::ROUTE, [
                'backgroundTaskFilter' => $filter,
                'taskUuid' => $uuid,
            ]);
        }

        return $links;
    }
}
