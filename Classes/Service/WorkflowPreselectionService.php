<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

use TYPO3\CMS\Core\Exception\SiteNotFoundException;

class WorkflowPreselectionService
{
    public const QUERY_PARAMETER = 'aiSuitePreselect';

    private const FORM_KEYS = [
        'ai_suite_workflow_pages_prepare' => 'workflowPagesPrepare',
        'ai_suite_workflow_filereferences_prepare' => 'workflowFileReferencesPrepare',
        'ai_suite_workflow_pages_translation_prepare' => 'workflowPagesTranslationPrepare',
        SessionService::ROUTE_FILELIST_METADATA => 'options',
        'files_aisuite.files_prepare' => 'options',
    ];
    private const FILE_ROUTES = [SessionService::ROUTE_FILELIST_METADATA, 'files_aisuite.files_prepare'];
    private const MAX_DEPTH = 5;
    private const TRANSLATION_SCOPES = ['all', 'metadata', 'content'];

    public function __construct(
        private readonly WorkflowSelectionGuard $selectionGuard,
        private readonly SiteService $siteService,
    ) {}

    public function formKeyOf(string $route): ?string
    {
        return self::FORM_KEYS[$route] ?? null;
    }

    /**
     * @param array<string, mixed> $raw
     *
     * @return array<string, string>
     */
    public function sanitize(string $route, array $raw, int $pageId): array
    {
        $values = [];
        if (isset($raw['depth']) && is_numeric($raw['depth']) && (int) $raw['depth'] >= 0 && (int) $raw['depth'] <= self::MAX_DEPTH) {
            $values['depth'] = (string) (int) $raw['depth'];
        }

        if ('ai_suite_workflow_pages_translation_prepare' === $route) {
            $sourceLanguage = $this->languageKey($raw['sourceLanguage'] ?? null, $pageId, true);
            if (null !== $sourceLanguage) {
                $values['sourceLanguage'] = $sourceLanguage;
            }
            $targetLanguage = $this->languageKey($raw['targetLanguage'] ?? null, $pageId, false);
            if (null !== $targetLanguage && !str_contains($targetLanguage, '__0__')) {
                $values['targetLanguage'] = $targetLanguage;
            }
            if (in_array($raw['translationScope'] ?? null, self::TRANSLATION_SCOPES, true)) {
                $values['translationScope'] = (string) $raw['translationScope'];
            }

            return $values;
        }

        if (in_array($route, self::FILE_ROUTES, true)) {
            return $values + $this->sanitizeFileOptions($raw);
        }

        $column = is_string($raw['column'] ?? null) ? $raw['column'] : '';
        $columnAllowed = 'ai_suite_workflow_pages_prepare' === $route
            ? $this->selectionGuard->mayUsePageMetadataColumn($column)
            : $this->selectionGuard->mayUseFileMetadataColumn($column);
        if ('' !== $column && $columnAllowed) {
            $values['column'] = $column;
        }
        if (in_array($raw['showOnlyEmpty'] ?? null, ['0', '1'], true)) {
            $values['showOnlyEmpty'] = (string) $raw['showOnlyEmpty'];
        }
        $sysLanguage = $this->languageKey($raw['sysLanguage'] ?? null, $pageId, false);
        if (null !== $sysLanguage) {
            $values['sysLanguage'] = $sysLanguage;
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $raw
     *
     * @return array<string, string>
     */
    private function sanitizeFileOptions(array $raw): array
    {
        $values = [];
        $column = is_string($raw['column'] ?? null) ? $raw['column'] : '';
        if ('' !== $column && $this->selectionGuard->mayUseFileMetadataColumn($column, true)) {
            $values['column'] = $column;
        }
        foreach (['showOnlyEmpty', 'showOnlyUsed'] as $flag) {
            if ('1' === ($raw[$flag] ?? null)) {
                $values[$flag] = '1';
            }
        }
        $sysLanguage = $this->languageKey($raw['sysLanguage'] ?? null, 0, false, true);
        if (null !== $sysLanguage) {
            $values['sysLanguage'] = $sysLanguage;
        }

        return $values;
    }

    private function languageKey(mixed $languageUid, int $pageId, bool $onlyDefault, bool $anySite = false): ?string
    {
        if (!is_string($languageUid) || !ctype_digit($languageUid) || ($pageId <= 0 && !$anySite)) {
            return null;
        }
        $uid = (int) $languageUid;
        if ($uid > 0 && !$this->selectionGuard->mayTranslateInto($uid)) {
            return null;
        }

        try {
            $available = $this->siteService->getAvailableLanguages(true, $pageId, $onlyDefault);
        } catch (SiteNotFoundException) {
            return null;
        }
        foreach (array_keys($available) as $key) {
            if ((string) $uid === (explode('__', (string) $key)[1] ?? null)) {
                return (string) $key;
            }
        }

        return null;
    }
}
