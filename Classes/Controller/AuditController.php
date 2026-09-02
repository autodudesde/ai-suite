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

namespace AutoDudes\AiSuite\Controller;

use AutoDudes\AiSuite\Controller\Trait\AjaxResponseTrait;
use AutoDudes\AiSuite\Domain\Repository\AuditResultRepository;
use AutoDudes\AiSuite\Domain\Repository\PagesRepository;
use AutoDudes\AiSuite\Domain\Repository\SysFileReferenceRepository;
use AutoDudes\AiSuite\Enumeration\GenerationLibraryEnumeration;
use AutoDudes\AiSuite\Service\AiSuiteContext;
use AutoDudes\AiSuite\Service\ContentTargetService;
use AutoDudes\AiSuite\Service\LibraryService;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\TranslationService;
use AutoDudes\AiSuite\Service\ViewFactoryService;
use AutoDudes\AiSuite\Utility\AuditScoreUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\Locale;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[AsController]
class AuditController extends AbstractBackendController
{
    use AjaxResponseTrait;

    private const AUDIT_TYPES = ['seo', 'a11y', 'questions', 'gap', 'cluster', 'competitors'];

    private const LANGUAGE_MAIN_MARKET = [
        'de' => 'DE', 'en' => 'US', 'fr' => 'FR', 'it' => 'IT', 'es' => 'ES',
        'nl' => 'NL', 'pl' => 'PL', 'pt' => 'PT', 'da' => 'DK', 'sv' => 'SE',
        'nb' => 'NO', 'no' => 'NO', 'fi' => 'FI', 'cs' => 'CZ', 'tr' => 'TR',
    ];
    private const PAGE_BROWSER_FIELD_REFERENCE = 'aiSuiteAuditPageSelection';

    private const RESULT_MAX_AGE_SECONDS = 14 * 86400;
    private const FIXABILITY_ORDER = ['one-click', 'ai-assist', 'dev-handoff', 'manual'];

    private const FIX_METADATA_FIELDS = [
        'title-missing' => ['seo_title' => 'PageTitle'],
        'title-too-long' => ['seo_title' => 'PageTitle'],
        'title-too-short' => ['seo_title' => 'PageTitle'],
        'meta-description-missing' => ['description' => 'MetaDescription'],
        'meta-description-too-long' => ['description' => 'MetaDescription'],
        'meta-description-too-short' => ['description' => 'MetaDescription'],
        'og-incomplete' => ['og_title' => 'OgTitle', 'og_description' => 'OgDescription'],
    ];
    private const FIX_APPLY_FIELDS = [
        'pages' => ['seo_title', 'description', 'og_title', 'og_description'],
        'sys_file_reference' => ['alternative'],
    ];
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    private const AUDIT_BATCH_PRICES = ['seo' => 3, 'a11y' => 3];

    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        UriBuilder $uriBuilder,
        PageRenderer $pageRenderer,
        FlashMessageService $flashMessageService,
        SendRequestService $requestService,
        TranslationService $translationService,
        EventDispatcher $eventDispatcher,
        AiSuiteContext $aiSuiteContext,
        protected readonly AuditResultRepository $auditResults,
        protected readonly ContentTargetService $contentTargetService,
        protected readonly SysFileReferenceRepository $sysFileReferenceRepository,
        protected readonly ViewFactoryService $viewFactoryService,
        protected readonly ExtensionConfiguration $extensionConfiguration,
        protected readonly LoggerInterface $logger,
    ) {
        parent::__construct(
            $moduleTemplateFactory,
            $uriBuilder,
            $pageRenderer,
            $flashMessageService,
            $requestService,
            $translationService,
            $eventDispatcher,
            $aiSuiteContext,
        );
    }

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $this->initialize($request);
        $this->auditResults->deleteOlderThan(self::RESULT_MAX_AGE_SECONDS);
        $identifier = $request->getAttribute('route')->getOption('_identifier');

        return match ($identifier) {
            'ai_suite_audit_run' => $this->runAction(),
            'ai_suite_audit_cached' => $this->cachedAction(),
            'ai_suite_audit_save_keyword' => $this->saveKeywordAction(),
            'ai_suite_audit_export' => $this->exportAction(),
            default => $this->overviewAction(),
        };
    }

    /**
     * @param array<string, int|string> $prefill
     */
    public function overviewAction(array $prefill = []): ResponseInterface
    {
        $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/audit/audit.js');
        $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/audit/batch-audit.js');
        $selectedPageId = (int) ($prefill['pageId'] ?? $this->aiSuiteContext->sessionService->getWebPageId());

        $keyword = (string) ($prefill['keyword'] ?? '');
        if ('' === $keyword && $selectedPageId > 0) {
            $keyword = $this->pageKeyword($selectedPageId);
        }

        $selectedLanguageUid = max(0, (int) ($prefill['languageUid'] ?? 0));
        $this->view->assignMultiple([
            'auditType' => $prefill['auditType'] ?? 'seo',
            'selectedPageId' => $selectedPageId,
            'selectedPageTitle' => $this->pageTitle($selectedPageId),
            'pageBrowserUrl' => $this->buildPageBrowserUrl($selectedPageId),
            'externalUrl' => $prefill['externalUrl'] ?? '',
            'keyword' => $keyword,
            'languageUid' => $selectedLanguageUid,
            'auditLanguages' => $this->auditablePageLanguages($selectedPageId),
            'lastAudits' => $this->lastAudits($selectedPageId, $selectedLanguageUid),
            'market' => $this->resolveMarket($prefill, $selectedPageId),
            'markets' => $this->collectMarkets(),
        ]);

        // Modell-Auswahl nur, wenn kein Standard-Audit-Modell konfiguriert ist
        if ('' === $this->configuredAuditModel()) {
            $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibraryEnumeration::METADATA, 'createMetadata', ['text']);
            if ('Error' !== $librariesAnswer->getType()) {
                $this->view->assign('paidRequestsAvailable', $librariesAnswer->getResponseData()['paidRequestsAvailable'] ?? false);
                $this->view->assign('auditTextLibraries', $this->aiSuiteContext->libraryService->prepareLibraries(
                    array_values(array_filter(
                        $librariesAnswer->getResponseData()['textGenerationLibraries'] ?? [],
                        static fn (array $library): bool => !empty($library['model_identifier'])
                            && !LibraryService::isVisionLibrary($library)
                    ))
                ));
            }
        }

        return $this->view->renderResponse('Audit/Overview');
    }

    public function lastAuditsAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        $parsedBody = (array) $request->getParsedBody();
        $pageId = (int) ($parsedBody['pageId'] ?? 0);
        if ($pageId <= 0) {
            return $this->jsonError($response, 'pageId is required.');
        }

        $languageUid = max(0, (int) ($parsedBody['languageUid'] ?? 0));

        return $this->jsonSuccess($response, [
            'pageId' => $pageId,
            'pageTitle' => $this->pageTitle($pageId),
            'keyword' => $this->pageKeyword($pageId),
            'suggestedMarket' => $this->marketForPage($pageId),
            'languages' => $this->auditablePageLanguages($pageId),
            'audits' => $this->lastAudits($pageId, $languageUid),
        ]);
    }

    public function fixStartAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        $parsedBody = (array) $request->getParsedBody();
        $pageId = (int) ($parsedBody['pageId'] ?? 0);
        $issueIds = json_decode((string) ($parsedBody['issueIds'] ?? '[]'), true);
        if ($pageId <= 0 || !\is_array($issueIds) || [] === $issueIds) {
            return $this->jsonError($response, 'pageId and issueIds are required.');
        }

        $queue = [];
        $altIssueIds = [];
        foreach ($issueIds as $issueId) {
            $issueId = (string) $issueId;
            if (isset(self::FIX_METADATA_FIELDS[$issueId])) {
                $fields = [];
                foreach (self::FIX_METADATA_FIELDS[$issueId] as $name => $aiLabel) {
                    $fields[] = [
                        'name' => $name,
                        'aiLabel' => $aiLabel,
                        'title' => $this->fieldTitle($name),
                        'current' => $this->pageFieldValue($pageId, $name),
                    ];
                }
                $queue[] = ['kind' => 'metadata', 'issueIds' => [$issueId], 'table' => 'pages', 'uid' => $pageId, 'sysFileId' => 0, 'label' => $issueId, 'fields' => $fields];
            } elseif ($this->isAltFixIssue($issueId)) {
                $altIssueIds[$issueId] = $issueId;
            }
        }
        if ([] !== $altIssueIds) {
            foreach ($this->emptyAltImages($pageId) as $image) {
                $queue[] = [
                    'kind' => 'alt',
                    'issueIds' => array_values($altIssueIds),
                    'table' => 'sys_file_reference',
                    'uid' => (int) $image['uid'],
                    'sysFileId' => (int) $image['uid_local'],
                    'label' => (string) $image['file_name'],
                    'fields' => [['name' => 'alternative', 'aiLabel' => 'Alternative', 'title' => $this->fieldTitle('alternative'), 'current' => '']],
                ];
            }
        }
        if ([] === $queue) {
            return $this->jsonError($response, $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.audit.fix.nothingToFix'));
        }

        $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibraryEnumeration::METADATA, 'createMetadata', ['text']);
        if ('Error' === $librariesAnswer->getType()) {
            return $this->jsonError($response, strip_tags((string) $librariesAnswer->getMessage()));
        }
        $allLibraries = $librariesAnswer->getResponseData()['textGenerationLibraries'] ?? [];
        $isVision = static fn (array $library): bool => LibraryService::isVisionLibrary($library);
        $needsText = [] !== array_filter($queue, static fn (array $item): bool => 'metadata' === $item['kind']);
        $needsVision = [] !== array_filter($queue, static fn (array $item): bool => 'alt' === $item['kind']);

        $uuid = $this->aiSuiteContext->uuidService->generateUuid();
        $content = $this->viewFactoryService->renderTemplate(
            $request,
            'FixWizardSlideOne',
            'EXT:ai_suite/Resources/Private/Templates/Ajax/Audit/',
            [
                'stepCount' => \count($queue),
                'requestCount' => array_sum(array_map(static fn (array $item): int => \count($item['fields']), $queue)),
                'textGenerationLibraries' => $needsText
                    ? $this->aiSuiteContext->libraryService->prepareLibraries(array_values(array_filter($allLibraries, static fn (array $library): bool => !$isVision($library))))
                    : [],
                'visionGenerationLibraries' => $needsVision
                    ? $this->aiSuiteContext->libraryService->prepareLibraries(array_values(array_filter($allLibraries, $isVision)))
                    : [],
                'paidRequestsAvailable' => $librariesAnswer->getResponseData()['paidRequestsAvailable'] ?? false,
                'uuid' => $uuid,
            ]
        );

        try {
            $langIsoCode = $this->aiSuiteContext->siteService->getIsoCodeByLanguageId(0, $pageId);
        } catch (\Throwable) {
            $langIsoCode = 'en';
        }

        return $this->jsonSuccess($response, [
            'content' => $content,
            'queue' => $queue,
            'uuid' => $uuid,
            'langIsoCode' => $langIsoCode,
        ]);
    }

    public function fixSuggestionsAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        $params = (array) $request->getParsedBody();
        $pageId = (int) ($params['pageId'] ?? 0);
        $kind = 'alt' === ($params['kind'] ?? '') ? 'alt' : 'metadata';
        $fields = json_decode((string) ($params['fields'] ?? '[]'), true);
        if ($pageId <= 0 || !\is_array($fields) || [] === $fields) {
            return $this->jsonError($response, 'pageId and fields are required.');
        }

        try {
            $filename = '';
            if ('alt' === $kind) {
                $sysFileId = (int) ($params['sysFileId'] ?? 0);
                if ($sysFileId <= 0 || !$this->aiSuiteContext->backendUserService->canEditFileReferenceMetadata($sysFileId)) {
                    return $this->logError('Insufficient permissions to access file with UID '.$sysFileId, $response, 403);
                }
                $model = (string) ($params['visionAiModel'] ?? '');
                $requestContent = $this->aiSuiteContext->metadataService->getFileContent($sysFileId);
                $filename = $this->aiSuiteContext->metadataService->getFilename($sysFileId);
                $globalInstructions = $this->aiSuiteContext->globalInstructionService->buildGlobalInstruction('files', 'metadata');
                $overridePredefinedPrompt = $this->aiSuiteContext->globalInstructionService->checkOverridePredefinedPrompt('files', 'metadata', ['']);
            } else {
                $model = (string) ($params['textAiModel'] ?? '');
                $requestContent = $this->aiSuiteContext->metadataService->fetchContentFromUrl($this->aiSuiteContext->metadataService->getPreviewUrl($pageId));
                $globalInstructions = $this->aiSuiteContext->globalInstructionService->buildGlobalInstruction('pages', 'metadata', $pageId);
                $intentHint = $this->searchIntentHint($pageId);
                if ('' !== $intentHint) {
                    $globalInstructions = trim($globalInstructions."\n".$intentHint);
                }
                $overridePredefinedPrompt = $this->aiSuiteContext->globalInstructionService->checkOverridePredefinedPrompt('pages', 'metadata', [$pageId]);
            }

            $suggestionCount = (int) ($this->extensionConfiguration->get('ai_suite')['metadataSuggestionCount'] ?? 3);
            $preparedFields = [];
            foreach ($fields as $field) {
                $answer = $this->requestService->sendDataRequest(
                    'createMetadata',
                    [
                        'uuid' => (string) ($params['uuid'] ?? ''),
                        'field_label' => (string) ($field['aiLabel'] ?? ''),
                        'request_content' => $requestContent,
                        'global_instructions' => $globalInstructions,
                        'override_predefined_prompt' => $overridePredefinedPrompt,
                        'custom_prompt' => '',
                        'filename' => $filename,
                    ],
                    '',
                    (string) ($params['langIsoCode'] ?? 'en'),
                    ['text' => $model]
                );
                if ('Error' === $answer->getType()) {
                    return $this->logError(strip_tags($this->requestService->getClientErrorMessage($answer)), $response, 503);
                }
                $metadataResult = $answer->getResponseData()['metadataResult'] ?? [];
                $suggestions = array_values(array_filter(
                    array_map(static fn ($suggestion): string => trim((string) $suggestion), \is_array($metadataResult) ? $metadataResult : []),
                    static fn (string $suggestion): bool => '' !== $suggestion
                ));
                if ([] === $suggestions) {
                    return $this->logError(
                        $this->aiSuiteContext->localizationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.metadata.noSuggestionsGenerated'),
                        $response,
                        422
                    );
                }
                $preparedFields[] = [
                    'name' => (string) ($field['name'] ?? ''),
                    'title' => (string) ($field['title'] ?? ''),
                    'current' => (string) ($field['current'] ?? ''),
                    'suggestions' => \array_slice($suggestions, 0, $suggestionCount),
                ];
            }

            $content = $this->viewFactoryService->renderTemplate(
                $request,
                'FixWizardStep',
                'EXT:ai_suite/Resources/Private/Templates/Ajax/Audit/',
                [
                    'kind' => $kind,
                    'label' => (string) ($params['label'] ?? ''),
                    'position' => (int) ($params['position'] ?? 1),
                    'total' => (int) ($params['total'] ?? 1),
                    'fileReferenceUid' => 'alt' === $kind ? (int) ($params['uid'] ?? 0) : 0,
                    'fields' => $preparedFields,
                ]
            );

            return $this->jsonSuccess($response, ['content' => $content]);
        } catch (\Throwable $e) {
            return $this->logError($e->getMessage(), $response, 503);
        }
    }

    // writes the confirmed values only on this explicit editor action, never automatically
    public function fixApplyAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        $params = (array) $request->getParsedBody();
        $table = (string) ($params['table'] ?? '');
        $uid = (int) ($params['uid'] ?? 0);
        $pageId = (int) ($params['pageId'] ?? 0);
        $auditType = \in_array($params['auditType'] ?? '', self::AUDIT_TYPES, true) ? (string) $params['auditType'] : 'seo';
        $values = json_decode((string) ($params['values'] ?? '[]'), true);
        $markFixed = json_decode((string) ($params['markFixedIssueIds'] ?? '[]'), true);
        if (!isset(self::FIX_APPLY_FIELDS[$table]) || $uid <= 0 || !\is_array($values)) {
            return $this->jsonError($response, 'Invalid fix payload.');
        }

        $datamap = [];
        foreach ($values as $field => $value) {
            $value = trim((string) $value);
            if ('' === $value || mb_strlen($value) > 1000 || !\in_array($field, self::FIX_APPLY_FIELDS[$table], true)) {
                continue;
            }
            $datamap[$field] = $value;
        }
        if ([] === $datamap) {
            return $this->jsonError($response, 'No values to save.');
        }

        try {
            $this->executeDataHandler([$table => [$uid => $datamap]]);
        } catch (\Throwable $e) {
            return $this->logError($e->getMessage(), $response, 500);
        }

        $fixedIssues = [];
        if (\is_array($markFixed) && [] !== $markFixed && $pageId > 0) {
            $cached = $this->auditResults->findLatest($pageId, $auditType);
            if (null !== $cached) {
                $result = $cached['result'];
                $fixedIssues = array_values(array_unique(array_merge(
                    \is_array($result['fixedIssues'] ?? null) ? $result['fixedIssues'] : [],
                    array_map('strval', $markFixed)
                )));
                $result['fixedIssues'] = $fixedIssues;
                $this->auditResults->store($pageId, $auditType, $cached['keyword'], $result, $cached['runTs']);
            }
        }

        return $this->jsonSuccess($response, ['saved' => array_keys($datamap), 'fixedIssues' => $fixedIssues]);
    }

    public function adviceAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        $params = (array) $request->getParsedBody();
        $pageId = (int) ($params['pageId'] ?? 0);
        $auditType = \in_array($params['auditType'] ?? '', self::AUDIT_TYPES, true) ? (string) $params['auditType'] : 'seo';
        $issueId = trim((string) ($params['issueId'] ?? ''));
        $issueMessage = trim((string) ($params['issueMessage'] ?? ''));
        if ($pageId <= 0 || '' === $issueId || '' === $issueMessage) {
            return $this->jsonError($response, 'pageId, issueId and issueMessage are required.');
        }

        $cached = $this->auditResults->findLatest($pageId, $auditType);
        $stored = $cached['result']['adviceByIssue'][$issueId] ?? null;
        if (\is_array($stored) && [] !== $stored) {
            return $this->jsonSuccess($response, ['advice' => $stored, 'cached' => true]);
        }

        try {
            $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibraryEnumeration::METADATA, 'createMetadata', ['text']);
            if ('Error' === $librariesAnswer->getType()) {
                return $this->jsonError($response, strip_tags((string) $librariesAnswer->getMessage()));
            }
            $libraries = $this->aiSuiteContext->libraryService->prepareLibraries(array_values(array_filter(
                $librariesAnswer->getResponseData()['textGenerationLibraries'] ?? [],
                static fn (array $library): bool => !LibraryService::isVisionLibrary($library)
            )));
            $model = $this->configuredAuditModel();
            foreach ('' === $model ? $libraries : [] as $library) {
                $model = (string) ($library['model_identifier'] ?? '');
                if ($library['checked'] ?? false) {
                    break;
                }
            }
            if ('' === $model) {
                return $this->jsonError($response, $this->aiSuiteContext->localizationService->translate('aiSuite.noLibrariesAvailable'));
            }

            try {
                $pageLanguage = $this->aiSuiteContext->siteService->getIsoCodeByLanguageId(0, $pageId);
            } catch (\Throwable) {
                $pageLanguage = '';
            }
            $answer = $this->requestService->sendDataRequest(
                'auditAdvice',
                [
                    'issue_id' => $issueId,
                    'issue_message' => $issueMessage,
                    'evidence' => trim((string) ($params['evidence'] ?? '')),
                    'request_content' => $this->aiSuiteContext->metadataService->fetchContentFromUrl($this->aiSuiteContext->metadataService->getPreviewUrl($pageId)),
                    'page_language' => $pageLanguage,
                ],
                '',
                $this->backendUserLanguage(),
                ['text' => $model]
            );
            if ('Error' === $answer->getType()) {
                return $this->logError(strip_tags($this->requestService->getClientErrorMessage($answer)), $response, 503);
            }
            $adviceResult = $answer->getResponseData()['adviceResult'] ?? [];
            $advice = array_values(array_filter(
                array_map(static fn ($tip): string => trim((string) $tip), \is_array($adviceResult) ? $adviceResult : []),
                static fn (string $tip): bool => '' !== $tip
            ));
            if ([] === $advice) {
                return $this->logError(
                    $this->aiSuiteContext->localizationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.metadata.noSuggestionsGenerated'),
                    $response,
                    422
                );
            }

            if (null !== $cached) {
                $result = $cached['result'];
                $result['adviceByIssue'][$issueId] = $advice;
                $this->auditResults->store($pageId, $auditType, $cached['keyword'], $result, $cached['runTs']);
            }

            return $this->jsonSuccess($response, ['advice' => $advice, 'cached' => false]);
        } catch (\Throwable $e) {
            return $this->logError($e->getMessage(), $response, 503);
        }
    }

    public function candidatesAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        $params = (array) $request->getParsedBody();
        $pageId = (int) ($params['pageId'] ?? 0);
        if ($pageId <= 0) {
            return $this->jsonError($response, 'pageId is required.');
        }

        $cached = $this->auditResults->findLatest($pageId, 'seo');
        $stored = $cached['result']['keywordCandidates'] ?? null;
        if (\is_array($stored) && [] !== $stored) {
            return $this->jsonSuccess($response, ['candidates' => $stored, 'cached' => true]);
        }

        try {
            $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibraryEnumeration::METADATA, 'createMetadata', ['text']);
            if ('Error' === $librariesAnswer->getType()) {
                return $this->jsonError($response, strip_tags((string) $librariesAnswer->getMessage()));
            }
            $libraries = $this->aiSuiteContext->libraryService->prepareLibraries(array_values(array_filter(
                $librariesAnswer->getResponseData()['textGenerationLibraries'] ?? [],
                static fn (array $library): bool => !LibraryService::isVisionLibrary($library)
            )));
            $model = $this->configuredAuditModel();
            foreach ('' === $model ? $libraries : [] as $library) {
                $model = (string) ($library['model_identifier'] ?? '');
                if ($library['checked'] ?? false) {
                    break;
                }
            }
            if ('' === $model) {
                return $this->jsonError($response, $this->aiSuiteContext->localizationService->translate('aiSuite.noLibrariesAvailable'));
            }

            try {
                $pageLanguage = $this->aiSuiteContext->siteService->getIsoCodeByLanguageId(0, $pageId);
            } catch (\Throwable) {
                $pageLanguage = 'de';
            }
            $answer = $this->requestService->sendDataRequest(
                'keywordCandidates',
                [
                    'market' => $this->marketForPage($pageId),
                    'request_content' => $this->aiSuiteContext->metadataService->fetchContentFromUrl($this->aiSuiteContext->metadataService->getPreviewUrl($pageId)),
                ],
                '',
                $pageLanguage,
                ['text' => $model]
            );
            if ('Error' === $answer->getType()) {
                return $this->logError(strip_tags($this->requestService->getClientErrorMessage($answer)), $response, 503);
            }
            $candidatesResult = $answer->getResponseData()['candidates'] ?? [];
            $candidates = array_values(array_filter(
                \is_array($candidatesResult) ? $candidatesResult : [],
                static fn ($candidate): bool => \is_array($candidate) && '' !== trim((string) ($candidate['keyword'] ?? ''))
            ));
            if ([] === $candidates) {
                return $this->logError(
                    $this->aiSuiteContext->localizationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.metadata.noSuggestionsGenerated'),
                    $response,
                    422
                );
            }

            if (null !== $cached) {
                $result = $cached['result'];
                $result['keywordCandidates'] = $candidates;
                $this->auditResults->store($pageId, 'seo', $cached['keyword'], $result, $cached['runTs']);
            }

            return $this->jsonSuccess($response, [
                'candidates' => $candidates,
                'volumesUnavailable' => (bool) ($answer->getResponseData()['volumesUnavailable'] ?? false),
                'cached' => false,
            ]);
        } catch (\Throwable $e) {
            return $this->logError($e->getMessage(), $response, 503);
        }
    }

    public function authorboxFormAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        $params = (array) $request->getParsedBody();
        $pageId = (int) ($params['pageId'] ?? 0);
        if ($pageId <= 0) {
            return $this->jsonError($response, 'pageId is required.');
        }

        try {
            $page = BackendUtility::getRecord('pages', $pageId, 'author');
            $prefillName = trim((string) ($page['author'] ?? ''));
            if ('' === $prefillName) {
                $prefillName = trim((string) ($this->aiSuiteContext->backendUserService->getBackendUser()?->user['realName'] ?? ''));
            }

            $content = $this->viewFactoryService->renderTemplate(
                $request,
                'AuthorboxForm',
                'EXT:ai_suite/Resources/Private/Templates/Ajax/Audit/',
                ['prefillName' => $prefillName]
            );

            return $this->jsonSuccess($response, ['content' => $content]);
        } catch (\Throwable $e) {
            return $this->logError($e->getMessage(), $response, 503);
        }
    }

    // writes pages.author only on this explicit editor opt-in, never automatically
    public function authorboxSaveAuthorAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        $params = (array) $request->getParsedBody();
        $pageId = (int) ($params['pageId'] ?? 0);
        $name = trim((string) ($params['name'] ?? ''));
        if ($pageId <= 0 || '' === $name || mb_strlen($name) > 255) {
            return $this->jsonError($response, 'pageId and a name (max. 255 chars) are required.');
        }

        try {
            $this->executeDataHandler(['pages' => [$pageId => ['author' => $name]]]);
        } catch (\Throwable $e) {
            return $this->logError($e->getMessage(), $response, 500);
        }

        return $this->jsonSuccess($response, ['saved' => true]);
    }

    public function batchPlanAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        $params = (array) $request->getParsedBody();
        $pageId = (int) ($params['pageId'] ?? 0);
        $depth = max(0, min(20, (int) ($params['depth'] ?? 0)));
        $auditType = \array_key_exists($params['auditType'] ?? '', self::AUDIT_BATCH_PRICES) ? (string) $params['auditType'] : 'seo';
        if ($pageId <= 0) {
            return $this->jsonError($response, 'pageId is required.');
        }

        try {
            $ids = 0 === $depth
                ? [$pageId]
                : GeneralUtility::makeInstance(PagesRepository::class)->getSubtreePageIds($pageId, $depth);
            $pages = $this->auditablePages($ids);
            $price = self::AUDIT_BATCH_PRICES[$auditType];
            $available = $this->availableCredits();

            return $this->jsonSuccess($response, [
                'pages' => $pages,
                'pricePerPage' => $price,
                'totalPrice' => $price * \count($pages),
                'available' => $available,
                'affordableCount' => null === $available ? null : (int) floor($available / $price),
            ]);
        } catch (\Throwable $e) {
            return $this->logError($e->getMessage(), $response, 503);
        }
    }

    public function batchRunOneAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        $params = (array) $request->getParsedBody();
        $pageId = (int) ($params['pageId'] ?? 0);
        $languageUid = max(0, (int) ($params['languageUid'] ?? 0));
        $auditType = \array_key_exists($params['auditType'] ?? '', self::AUDIT_BATCH_PRICES) ? (string) $params['auditType'] : 'seo';
        if ($pageId <= 0) {
            return $this->jsonError($response, 'pageId is required.');
        }

        try {
            $url = $this->aiSuiteContext->metadataService->getPreviewUrl($pageId, [], $languageUid);
            $data = ['url' => $url];
            if ('a11y' === $auditType) {
                $data['standard'] = $this->wcagStandard();
            }
            $answer = $this->requestService->sendDataRequest(
                'seo' === $auditType ? 'seoAudit' : 'accessibilityAudit',
                $data,
                '',
                $this->backendUserLanguage(),
            );
            if ('Error' === $answer->getType()) {
                return $this->jsonSuccess($response, [
                    'ok' => false,
                    'creditsExhausted' => 'notEnoughRequests' === $answer->getErrorType(),
                    'message' => strip_tags($this->requestService->getClientErrorMessage($answer)),
                ]);
            }

            $body = $answer->getResponseData();
            $this->auditResults->store($pageId, $auditType, '', ['url' => $url] + $body, null, $languageUid);
            $summary = $body['audit']['summary'] ?? [];
            $score = AuditScoreUtility::fromSummary(\is_array($summary) ? $summary : []);

            return $this->jsonSuccess($response, [
                'ok' => true,
                'score' => $score,
                'range' => AuditScoreUtility::range($score),
            ]);
        } catch (\Throwable $e) {
            return $this->logError($e->getMessage(), $response, 503);
        }
    }

    public function contentTargetsAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        $params = (array) $request->getParsedBody();
        $pageId = (int) ($params['pageId'] ?? 0);
        $auditType = \in_array($params['auditType'] ?? '', self::AUDIT_TYPES, true) ? (string) $params['auditType'] : 'questions';
        if ($pageId <= 0) {
            return $this->jsonError($response, 'pageId is required.');
        }

        try {
            $content = $this->viewFactoryService->renderTemplate(
                $request,
                'ContentTargetWizard',
                'EXT:ai_suite/Resources/Private/Templates/Ajax/Audit/',
                [
                    'columns' => $this->contentTargetService->getColumns($pageId),
                    'elementsByColumn' => $this->contentTargetService->getElementsByColumn($pageId),
                    'ceOptions' => $this->contentTargetService->getContentTypeOptions($pageId, (string) ($params['action'] ?? 'faq')),
                ]
            );

            return $this->jsonSuccess($response, [
                'content' => $content,
                'recordEditUrl' => (string) $this->uriBuilder->buildUriFromRoute('ai_suite_record_edit'),
                'returnUrl' => (string) $this->uriBuilder->buildUriFromRoute('ai_suite_audit_cached', ['pageId' => $pageId, 'auditType' => $auditType]),
            ]);
        } catch (\Throwable $e) {
            return $this->logError($e->getMessage(), $response, 503);
        }
    }

    public function questionsAnswersAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        $params = (array) $request->getParsedBody();
        $url = trim((string) ($params['url'] ?? ''));
        $questions = json_decode((string) ($params['questions'] ?? '[]'), true);
        if ('' === $url || !\is_array($questions) || [] === $questions) {
            return $this->jsonError($response, 'url and questions are required.');
        }

        try {
            $model = $this->auditTextModel();
            if ('' === $model) {
                return $this->jsonError($response, $this->aiSuiteContext->localizationService->translate('aiSuite.noLibrariesAvailable'));
            }

            $answer = $this->requestService->sendDataRequest(
                'questionsAnswers',
                [
                    'request_content' => $this->aiSuiteContext->metadataService->fetchContentFromUrl($url),
                    'questions' => (string) json_encode(array_values(array_map('strval', $questions))),
                ],
                '',
                $this->backendUserLanguage(),
                ['text' => $model]
            );
            if ('Error' === $answer->getType()) {
                return $this->logError(strip_tags($this->requestService->getClientErrorMessage($answer)), $response, 503);
            }
            $answers = array_values(array_filter(
                \is_array($answer->getResponseData()['answers'] ?? null) ? $answer->getResponseData()['answers'] : [],
                static fn ($row): bool => \is_array($row) && '' !== trim((string) ($row['answer'] ?? ''))
            ));
            if ([] === $answers) {
                return $this->logError(
                    $this->aiSuiteContext->localizationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.metadata.noSuggestionsGenerated'),
                    $response,
                    422
                );
            }

            return $this->jsonSuccess($response, ['answers' => $answers]);
        } catch (\Throwable $e) {
            return $this->logError($e->getMessage(), $response, 503);
        }
    }

    public function exportAction(): ResponseInterface
    {
        $queryParams = $this->request->getQueryParams();
        $pageId = (int) ($queryParams['pageId'] ?? 0);
        $auditType = \in_array($queryParams['auditType'] ?? '', self::AUDIT_TYPES, true) ? (string) $queryParams['auditType'] : 'seo';

        $cached = $pageId > 0 ? $this->auditResults->findLatest($pageId, $auditType) : null;
        if (null === $cached) {
            $this->view->addFlashMessage(
                $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.audit.noCachedResult.message'),
                $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.audit.noCachedResult.title'),
                ContextualFeedbackSeverity::INFO
            );

            return $this->overviewAction(['pageId' => $pageId]);
        }

        $rows = [['severity', 'id', 'category', 'fixability', 'message', 'evidence', 'hint', 'docUrl', 'tags']];
        $issues = $cached['result']['audit']['issues'] ?? [];
        foreach (\is_array($issues) ? $issues : [] as $issue) {
            if (!\is_array($issue)) {
                continue;
            }
            $rows[] = [
                (string) ($issue['severity'] ?? ''),
                (string) ($issue['id'] ?? ''),
                (string) ($issue['category'] ?? ''),
                (string) ($issue['fixability'] ?? ''),
                (string) ($issue['message'] ?? ''),
                (string) ($issue['evidence'] ?? ''),
                (string) ($issue['hint'] ?? ''),
                (string) ($issue['docUrl'] ?? ''),
                implode(',', \is_array($issue['tags'] ?? null) ? $issue['tags'] : []),
            ];
        }
        // BOM + Semikolon: direkt in (deutschem) Excel öffenbar
        $csv = "\xEF\xBB\xBF".implode("\r\n", array_map(
            static fn (array $row): string => implode(';', array_map(
                static fn (string $value): string => '"'.str_replace('"', '""', $value).'"',
                $row
            )),
            $rows
        ))."\r\n";

        $response = new Response();
        $response->getBody()->write($csv);

        return $response
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', sprintf('attachment; filename="audit-%s-page-%d.csv"', $auditType, $pageId))
        ;
    }

    public function runAction(): ResponseInterface
    {
        $parsedBody = (array) $this->request->getParsedBody();
        $auditType = \in_array($parsedBody['auditType'] ?? '', self::AUDIT_TYPES, true) ? $parsedBody['auditType'] : 'seo';
        $pageId = (int) ($parsedBody['pageId'] ?? 0);
        $externalUrl = trim((string) ($parsedBody['externalUrl'] ?? ''));
        $keyword = \in_array($auditType, ['seo', 'questions', 'cluster'], true) ? trim((string) ($parsedBody['keyword'] ?? '')) : '';
        $languageUid = max(0, (int) ($parsedBody['languageUid'] ?? 0));
        $market = $this->resolveMarket($parsedBody, $pageId);
        $model = trim((string) ($parsedBody['libraries']['textGenerationLibrary'] ?? ''));
        if ('' === $model) {
            $model = $this->auditTextModel();
        }
        $prefill = ['auditType' => $auditType, 'pageId' => $pageId, 'externalUrl' => $externalUrl, 'keyword' => $keyword, 'market' => $market, 'languageUid' => $languageUid];

        try {
            $sourcePageId = 0;
            if ('' !== $externalUrl) {
                $url = $externalUrl;
            } elseif ($pageId > 0) {
                $url = $this->aiSuiteContext->metadataService->getPreviewUrl($pageId, [], $languageUid);
                $sourcePageId = $pageId;
            } else {
                $url = '';
            }

            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $this->view->addFlashMessage(
                    $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.audit.invalidUrl.message'),
                    $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.audit.invalidUrl.title'),
                    ContextualFeedbackSeverity::WARNING
                );

                return $this->overviewAction($prefill);
            }

            if ('questions' === $auditType) {
                return $this->runQuestionsAudit($url, $keyword, $prefill, $sourcePageId, $market, $model, $languageUid);
            }
            if ('gap' === $auditType) {
                return $this->runGapAudit($url, $prefill, $sourcePageId, $market, $model, $languageUid);
            }
            if ('competitors' === $auditType) {
                return $this->runCompetitorAudit($url, $prefill, $sourcePageId, $market, $model, $languageUid);
            }
            if ('cluster' === $auditType) {
                if ('' === $keyword) {
                    $this->view->addFlashMessage(
                        $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.audit.cluster.keywordRequired.message'),
                        $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.audit.cluster.keywordRequired.title'),
                        ContextualFeedbackSeverity::WARNING
                    );

                    return $this->overviewAction($prefill);
                }

                return $this->runClusterAudit($url, $keyword, $prefill, $sourcePageId, $market, $model, $languageUid);
            }

            $data = ['url' => $url];
            if ('' !== $keyword) {
                $data['keyword'] = $keyword;
                $data['market'] = $market;
            }
            if ('a11y' === $auditType) {
                $data['standard'] = $this->wcagStandard();
            }
            $answer = $this->requestService->sendDataRequest(
                'seo' === $auditType ? 'seoAudit' : 'accessibilityAudit',
                $data,
                '',
                $this->backendUserLanguage(),
            );

            if ('Error' === $answer->getType()) {
                $this->view->addFlashMessage(
                    strip_tags($this->requestService->getClientErrorMessage($answer)),
                    $this->aiSuiteContext->localizationService->translate('aiSuite.error.default.title'),
                    ContextualFeedbackSeverity::ERROR
                );

                return $this->overviewAction($prefill);
            }

            $body = $answer->getResponseData();
            if (($body['keywordUnavailable'] ?? false) === true) {
                $this->view->addFlashMessage(
                    $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.audit.keywordUnavailable.message'),
                    $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.audit.keywordUnavailable.title'),
                    ContextualFeedbackSeverity::INFO
                );
            }
            if ($sourcePageId > 0) {
                $this->auditResults->store($sourcePageId, $auditType, $keyword, ['url' => $url] + $body, null, $languageUid);
            }

            return $this->renderResult($auditType, $url, $keyword, $body, $sourcePageId, null, $languageUid);
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            $this->view->addFlashMessage(
                $e->getMessage(),
                $this->aiSuiteContext->localizationService->translate('aiSuite.error.default.title'),
                ContextualFeedbackSeverity::ERROR
            );

            return $this->overviewAction($prefill);
        }
    }

    public function cachedAction(): ResponseInterface
    {
        $queryParams = $this->request->getQueryParams();
        $pageId = (int) ($queryParams['pageId'] ?? 0);
        $auditType = \in_array($queryParams['auditType'] ?? '', self::AUDIT_TYPES, true) ? $queryParams['auditType'] : 'seo';
        $languageUid = max(0, (int) ($queryParams['languageUid'] ?? 0));

        $cached = $pageId > 0 ? $this->auditResults->findLatest($pageId, $auditType, $languageUid) : null;
        if (null === $cached) {
            $this->view->addFlashMessage(
                $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.audit.noCachedResult.message'),
                $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.audit.noCachedResult.title'),
                ContextualFeedbackSeverity::INFO
            );

            return $this->overviewAction(['pageId' => $pageId]);
        }

        $body = $cached['result'];

        return $this->renderResult($auditType, (string) ($body['url'] ?? ''), $cached['keyword'], $body, $pageId, $cached['runTs'], $languageUid);
    }

    // writes pages.keywords only on this explicit editor action, never automatically
    public function saveKeywordAction(): ResponseInterface
    {
        $parsedBody = (array) $this->request->getParsedBody();
        $pageId = (int) ($parsedBody['pageId'] ?? 0);
        $keyword = trim((string) ($parsedBody['keyword'] ?? ''));

        try {
            if ($pageId <= 0 || '' === $keyword || mb_strlen($keyword) > 255) {
                return $this->overviewAction();
            }

            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start(['pages' => [$pageId => ['keywords' => $keyword]]], []);
            $dataHandler->process_datamap();
            if (\count($dataHandler->errorLog) > 0) {
                throw new \RuntimeException(implode(', ', $dataHandler->errorLog));
            }

            $this->view->addFlashMessage(
                $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.audit.keywordSaved.message'),
                $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.audit.keywordSaved.title'),
                ContextualFeedbackSeverity::OK
            );
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            $this->view->addFlashMessage(
                $e->getMessage(),
                $this->aiSuiteContext->localizationService->translate('aiSuite.error.default.title'),
                ContextualFeedbackSeverity::ERROR
            );
        }

        $cached = $this->auditResults->findLatest($pageId, 'seo');
        if (null !== $cached) {
            return $this->renderResult('seo', (string) ($cached['result']['url'] ?? ''), $cached['keyword'], $cached['result'], $pageId, $cached['runTs']);
        }

        return $this->overviewAction(['pageId' => $pageId]);
    }

    /**
     * @param list<int> $ids
     *
     * @return list<array{uid: int, title: string}>
     */
    private function auditablePages(array $ids): array
    {
        $pages = [];
        foreach ($ids as $id) {
            $page = BackendUtility::getRecord('pages', (int) $id, 'uid, title, doktype, hidden');
            if (null === $page || 1 !== (int) $page['doktype'] || 1 === (int) $page['hidden']) {
                continue;
            }
            $pages[] = ['uid' => (int) $page['uid'], 'title' => (string) $page['title']];
        }

        return $pages;
    }

    private function availableCredits(): ?int
    {
        try {
            $answer = $this->requestService->sendDataRequest('getRequestsState');
            if ('RequestsState' !== $answer->getType()) {
                return null;
            }
            $body = $answer->getResponseData();

            return (int) ($body['free_requests'] ?? 0) + (int) ($body['paid_requests'] ?? 0) + (int) ($body['abo_requests'] ?? 0);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, int|string> $prefill
     */
    private function runQuestionsAudit(string $url, string $keyword, array $prefill, int $sourcePageId, string $market, string $model, int $languageUid = 0): ResponseInterface
    {
        if ('' === $model) {
            $this->view->addFlashMessage(
                $this->aiSuiteContext->localizationService->translate('aiSuite.noLibrariesAvailable'),
                $this->aiSuiteContext->localizationService->translate('aiSuite.error.default.title'),
                ContextualFeedbackSeverity::ERROR
            );

            return $this->overviewAction($prefill);
        }

        $langIsoCode = $this->backendUserLanguage();
        if ($sourcePageId > 0) {
            try {
                $langIsoCode = $this->aiSuiteContext->siteService->getIsoCodeByLanguageId(0, $sourcePageId);
            } catch (\Throwable) {
                // Fallback bleibt die Backend-Sprache
            }
        }

        $answer = $this->requestService->sendDataRequest(
            'questionsAudit',
            [
                'market' => $market,
                'url' => $url,
                'keyword' => $keyword,
                'request_content' => $this->aiSuiteContext->metadataService->fetchContentFromUrl($url),
            ],
            '',
            $langIsoCode,
            ['text' => $model]
        );
        if ('Error' === $answer->getType()) {
            $this->view->addFlashMessage(
                strip_tags($this->requestService->getClientErrorMessage($answer)),
                $this->aiSuiteContext->localizationService->translate('aiSuite.error.default.title'),
                ContextualFeedbackSeverity::ERROR
            );

            return $this->overviewAction($prefill);
        }

        $body = $answer->getResponseData();
        if ($sourcePageId > 0) {
            $this->auditResults->store($sourcePageId, 'questions', $keyword, ['url' => $url] + $body, null, $languageUid);
        }

        return $this->renderQuestionsResult($url, $keyword, $body, $sourcePageId, null, $languageUid);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function renderQuestionsResult(string $url, string $keyword, array $body, int $pageId, ?int $cachedAt = null, int $languageUid = 0): ResponseInterface
    {
        $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/audit/fix-wizard.js');
        $questions = \is_array($body['questions'] ?? null) ? $body['questions'] : [];
        $unanswered = array_values(array_filter(
            $questions,
            static fn ($question): bool => \is_array($question) && 'answered' !== ($question['status'] ?? '')
        ));

        $generatePrompt = '';
        if ($pageId > 0 && [] !== $unanswered) {
            $generatePrompt = $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.audit.questions.generatePrompt')
                ."\n- ".implode("\n- ", array_column($unanswered, 'question'));
        }

        $this->view->assignMultiple([
            'unansweredQuestionsJson' => [] === $unanswered ? '' : (string) json_encode(array_column($unanswered, 'question')),
            'auditType' => 'questions',
            'auditTypeLabel' => $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.audit.type.questions'),
            'url' => $url,
            'keyword' => $keyword,
            'pageId' => $pageId,
            'cachedAt' => $cachedAt,
            'questions' => $questions,
            'summary' => \is_array($body['summary'] ?? null) ? $body['summary'] : [],
            'serpUnavailable' => (bool) ($body['serpUnavailable'] ?? false),
            'generatePrompt' => $generatePrompt,
        ]);

        return $this->view->renderResponse('Audit/QuestionsResult');
    }

    private function searchIntentHint(int $pageId): string
    {
        $cached = $this->auditResults->findLatest($pageId, 'seo');
        $intent = $cached['result']['keyword']['intent'] ?? null;
        if (!\is_array($intent) || '' === (string) ($intent['label'] ?? '')) {
            return '';
        }
        $labels = [(string) $intent['label']];
        foreach (\is_array($intent['secondary'] ?? null) ? $intent['secondary'] : [] as $secondary) {
            $labels[] = (string) $secondary;
        }

        return 'The dominant search intent for this page\'s focus keyword is: '.implode(', ', array_unique($labels)).'. Match tone and emphasis accordingly.';
    }

    /**
     * @param array<string, int|string> $prefill
     */
    private function runGapAudit(string $url, array $prefill, int $sourcePageId, string $market, string $model, int $languageUid = 0): ResponseInterface
    {
        if ('' === $model) {
            $this->view->addFlashMessage(
                $this->aiSuiteContext->localizationService->translate('aiSuite.noLibrariesAvailable'),
                $this->aiSuiteContext->localizationService->translate('aiSuite.error.default.title'),
                ContextualFeedbackSeverity::ERROR
            );

            return $this->overviewAction($prefill);
        }

        $answer = $this->requestService->sendDataRequest(
            'contentGapAudit',
            [
                'market' => $market,
                'url' => $url,
                'request_content' => $this->aiSuiteContext->metadataService->fetchContentFromUrl($url),
            ],
            '',
            $this->backendUserLanguage(),
            ['text' => $model]
        );
        if ('Error' === $answer->getType()) {
            $this->view->addFlashMessage(
                strip_tags($this->requestService->getClientErrorMessage($answer)),
                $this->aiSuiteContext->localizationService->translate('aiSuite.error.default.title'),
                ContextualFeedbackSeverity::ERROR
            );

            return $this->overviewAction($prefill);
        }

        $body = $answer->getResponseData();
        if ($sourcePageId > 0) {
            $this->auditResults->store($sourcePageId, 'gap', '', ['url' => $url] + $body, null, $languageUid);
        }

        return $this->renderGapResult($url, $body, $sourcePageId, null, $languageUid);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function renderGapResult(string $url, array $body, int $pageId, ?int $cachedAt = null, int $languageUid = 0): ResponseInterface
    {
        $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/audit/fix-wizard.js');
        $gaps = $this->withDifficultyDisplay(array_values(array_filter(
            \is_array($body['gaps'] ?? null) ? $body['gaps'] : [],
            static fn ($gap): bool => \is_array($gap)
        )));
        $uncovered = array_values(array_filter(
            $gaps,
            static fn ($gap): bool => \is_array($gap) && 'answered' !== ($gap['status'] ?? '')
        ));

        $generatePrompt = '';
        if ($pageId > 0 && 0 === $languageUid && [] !== $uncovered) {
            $generatePrompt = $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.audit.gap.generatePrompt')
                ."\n- ".implode("\n- ", array_column($uncovered, 'keyword'));
        }

        $this->view->assignMultiple([
            'auditType' => 'gap',
            'auditTypeLabel' => $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.audit.type.gap'),
            'url' => $url,
            'pageId' => $pageId,
            'cachedAt' => $cachedAt,
            'gaps' => $gaps,
            'summary' => \is_array($body['summary'] ?? null) ? $body['summary'] : [],
            'target' => (string) ($body['target'] ?? ''),
            'noCandidates' => (bool) ($body['noCandidates'] ?? false),
            'domainUnknown' => (bool) ($body['domainUnknown'] ?? false),
            'pageNotRanking' => (bool) ($body['pageNotRanking'] ?? false),
            'generatePrompt' => $generatePrompt,
        ]);

        return $this->view->renderResponse('Audit/GapResult');
    }

    /**
     * @param array<string, int|string> $prefill
     */
    private function runCompetitorAudit(string $url, array $prefill, int $sourcePageId, string $market, string $model, int $languageUid = 0): ResponseInterface
    {
        if ('' === $model) {
            $this->view->addFlashMessage(
                $this->aiSuiteContext->localizationService->translate('aiSuite.noLibrariesAvailable'),
                $this->aiSuiteContext->localizationService->translate('aiSuite.error.default.title'),
                ContextualFeedbackSeverity::ERROR
            );

            return $this->overviewAction($prefill);
        }

        $answer = $this->requestService->sendDataRequest(
            'competitorAudit',
            [
                'market' => $market,
                'url' => $url,
                'request_content' => $this->aiSuiteContext->metadataService->fetchContentFromUrl($url),
            ],
            '',
            $this->backendUserLanguage(),
            ['text' => $model]
        );
        if ('Error' === $answer->getType()) {
            $this->view->addFlashMessage(
                strip_tags($this->requestService->getClientErrorMessage($answer)),
                $this->aiSuiteContext->localizationService->translate('aiSuite.error.default.title'),
                ContextualFeedbackSeverity::ERROR
            );

            return $this->overviewAction($prefill);
        }

        $body = $answer->getResponseData();
        if ($sourcePageId > 0) {
            $this->auditResults->store($sourcePageId, 'competitors', '', ['url' => $url] + $body, null, $languageUid);
        }

        return $this->renderCompetitorResult($url, $body, $sourcePageId, null, $languageUid);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function renderCompetitorResult(string $url, array $body, int $pageId, ?int $cachedAt = null, int $languageUid = 0): ResponseInterface
    {
        $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/audit/fix-wizard.js');
        $competitors = \is_array($body['competitors'] ?? null) ? $body['competitors'] : [];
        // Ohne ermittelbaren Wettbewerber darf NIE "covers this market well" stehen
        $domainUnknown = (bool) ($body['domainUnknown'] ?? false) || ([] === $competitors && [] === ($body['gaps'] ?? []));
        $gaps = [];
        foreach (\is_array($body['gaps'] ?? null) ? $body['gaps'] : [] as $gap) {
            if (!\is_array($gap)) {
                continue;
            }
            if ($pageId > 0 && 0 === $languageUid && 'answered' !== ($gap['status'] ?? '')) {
                $gap['generatePrompt'] = $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.audit.competitors.generatePrompt')
                    ."\n".(string) ($gap['keyword'] ?? '');
            }
            $gaps[] = $gap;
        }
        $gaps = $this->withDifficultyDisplay($gaps);

        // Seitenbaum nur als VORSCHLAG über den bestehenden Consent-Flow
        $pageTreeUrl = '';
        $uncovered = array_values(array_filter($gaps, static fn (array $gap): bool => 'answered' !== ($gap['status'] ?? '')));
        if ([] !== $uncovered) {
            $pageTreePrompt = $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.audit.competitors.pagetreePrompt')
                ."\n- ".implode("\n- ", array_column($uncovered, 'keyword'));

            try {
                // Prompt per POST durchreichen, nicht als GET-Query
                $pageTreeUrl = (string) $this->uriBuilder->buildUriFromRoute('ai_suite_page_create_pagetree');
            } catch (\Throwable) {
                // Modul nicht verfügbar -> kein Vorschlags-Link
            }
        }

        $this->view->assignMultiple([
            'auditType' => 'competitors',
            'auditTypeLabel' => $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.audit.type.competitors'),
            'url' => $url,
            'pageId' => $pageId,
            'cachedAt' => $cachedAt,
            'target' => (string) ($body['target'] ?? ''),
            'competitors' => $competitors,
            'gapTarget' => (string) ($body['gapTarget'] ?? ''),
            'gaps' => $gaps,
            'summary' => \is_array($body['summary'] ?? null) ? $body['summary'] : [],
            'noCandidates' => !$domainUnknown && (bool) ($body['noCandidates'] ?? false),
            'domainUnknown' => $domainUnknown,
            'pageTreeUrl' => $pageTreeUrl,
            'pageTreePrompt' => $pageTreePrompt ?? '',
        ]);

        return $this->view->renderResponse('Audit/CompetitorsResult');
    }

    /**
     * @param array<string, int|string> $prefill
     */
    private function runClusterAudit(string $url, string $keyword, array $prefill, int $sourcePageId, string $market, string $model, int $languageUid = 0): ResponseInterface
    {
        if ('' === $model) {
            $this->view->addFlashMessage(
                $this->aiSuiteContext->localizationService->translate('aiSuite.noLibrariesAvailable'),
                $this->aiSuiteContext->localizationService->translate('aiSuite.error.default.title'),
                ContextualFeedbackSeverity::ERROR
            );

            return $this->overviewAction($prefill);
        }

        $answer = $this->requestService->sendDataRequest(
            'topicClusterAudit',
            [
                'market' => $market,
                'keyword' => $keyword,
                'request_content' => $this->aiSuiteContext->metadataService->fetchContentFromUrl($url),
            ],
            '',
            $this->backendUserLanguage(),
            ['text' => $model]
        );
        if ('Error' === $answer->getType()) {
            $this->view->addFlashMessage(
                strip_tags($this->requestService->getClientErrorMessage($answer)),
                $this->aiSuiteContext->localizationService->translate('aiSuite.error.default.title'),
                ContextualFeedbackSeverity::ERROR
            );

            return $this->overviewAction($prefill);
        }

        $body = $answer->getResponseData();
        if ($sourcePageId > 0) {
            $this->auditResults->store($sourcePageId, 'cluster', $keyword, ['url' => $url] + $body, null, $languageUid);
        }

        return $this->renderClusterResult($url, $keyword, $body, $sourcePageId, null, $languageUid);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function renderClusterResult(string $url, string $keyword, array $body, int $pageId, ?int $cachedAt = null, int $languageUid = 0): ResponseInterface
    {
        $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/audit/fix-wizard.js');
        $clusters = [];
        foreach (\is_array($body['clusters'] ?? null) ? $body['clusters'] : [] as $cluster) {
            if (!\is_array($cluster)) {
                continue;
            }
            $cluster['exampleQueries'] = implode(', ', array_column(
                \is_array($cluster['keywords'] ?? null) ? $cluster['keywords'] : [],
                'keyword'
            ));
            if ($pageId > 0 && 0 === $languageUid && 'answered' !== ($cluster['status'] ?? '')) {
                $cluster['generatePrompt'] = $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.audit.cluster.generatePrompt')
                    ."\n".sprintf('%s: %s', (string) ($cluster['label'] ?? ''), $cluster['exampleQueries']);
            }
            $clusters[] = $cluster;
        }

        // Seitenbaum nur als VORSCHLAG über den bestehenden Consent-Flow
        $pageTreeUrl = '';
        $uncovered = array_values(array_filter($clusters, static fn (array $cluster): bool => 'answered' !== ($cluster['status'] ?? '')));
        if ([] !== $uncovered) {
            $pageTreePrompt = $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.audit.cluster.pagetreePrompt')
                ."\n- ".implode("\n- ", array_map(
                    static fn (array $cluster): string => sprintf('%s (%s)', (string) ($cluster['label'] ?? ''), $cluster['exampleQueries']),
                    $uncovered
                ));

            try {
                // Prompt per POST durchreichen, nicht als GET-Query
                $pageTreeUrl = (string) $this->uriBuilder->buildUriFromRoute('ai_suite_page_create_pagetree');
            } catch (\Throwable) {
                // Modul nicht verfügbar -> kein Vorschlags-Link
            }
        }

        $this->view->assignMultiple([
            'auditType' => 'cluster',
            'auditTypeLabel' => $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.audit.type.cluster'),
            'url' => $url,
            'keyword' => $keyword,
            'pageId' => $pageId,
            'cachedAt' => $cachedAt,
            'clusters' => $clusters,
            'summary' => \is_array($body['summary'] ?? null) ? $body['summary'] : [],
            'noClusters' => (bool) ($body['noClusters'] ?? false),
            'pageTreeUrl' => $pageTreeUrl,
            'pageTreePrompt' => $pageTreePrompt ?? '',
        ]);

        return $this->view->renderResponse('Audit/ClusterResult');
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function withDifficultyDisplay(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['difficultyDisplay'] = isset($row['difficulty']) ? (string) (int) $row['difficulty'] : '—';
        }
        unset($row);

        return $rows;
    }

    private function firstTextModel(): string
    {
        $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibraryEnumeration::METADATA, 'createMetadata', ['text']);
        if ('Error' === $librariesAnswer->getType()) {
            return '';
        }
        $libraries = $this->aiSuiteContext->libraryService->prepareLibraries(array_values(array_filter(
            $librariesAnswer->getResponseData()['textGenerationLibraries'] ?? [],
            static fn (array $library): bool => !LibraryService::isVisionLibrary($library)
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

    /**
     * @return list<array<string, mixed>>
     */
    private function emptyAltImages(int $pageId): array
    {
        $images = [];
        foreach ($this->sysFileReferenceRepository->findByFileOrPage(null, $pageId) as $row) {
            if ('' !== trim((string) ($row['alternative'] ?? ''))
                || !\in_array(strtolower((string) ($row['extension'] ?? '')), self::IMAGE_EXTENSIONS, true)
                || !$this->aiSuiteContext->backendUserService->canEditFileReferenceMetadata((int) $row['uid_local'])
            ) {
                continue;
            }
            $images[] = $row;
        }

        return $images;
    }

    private function fieldTitle(string $fieldName): string
    {
        return $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.audit.fix.field.'.$fieldName);
    }

    private function pageFieldValue(int $pageId, string $fieldName): string
    {
        $page = BackendUtility::getRecord('pages', $pageId, $fieldName);

        return trim((string) ($page[$fieldName] ?? ''));
    }

    private function buildPageBrowserUrl(int $selectedPageId): string
    {
        if (GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() >= 14) {
            // no bparams here: it would win over these parameters and force useEvents=false
            $parameters = [
                'mode' => 'db',
                'fieldReference' => self::PAGE_BROWSER_FIELD_REFERENCE,
                'allowedTypes' => 'pages',
                'useEvents' => 1,
            ];
        } else {
            $parameters = ['mode' => 'db', 'bparams' => self::PAGE_BROWSER_FIELD_REFERENCE.'|||pages'];
        }
        if ($selectedPageId > 0) {
            $parameters['expandPage'] = $selectedPageId;
        }

        try {
            return (string) $this->uriBuilder->buildUriFromRoute('wizard_element_browser', $parameters);
        } catch (\Throwable $e) {
            $this->logger->error('Page element browser route is not available', ['error' => $e->getMessage()]);

            return '';
        }
    }

    private function pageTitle(int $pageId): string
    {
        if ($pageId <= 0) {
            return '';
        }
        $page = BackendUtility::getRecord('pages', $pageId, 'title');

        return null === $page ? '' : sprintf('%s [%d]', (string) ($page['title'] ?? ''), $pageId);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function renderResult(string $auditType, string $url, string $keyword, array $body, int $pageId, ?int $cachedAt = null, int $languageUid = 0): ResponseInterface
    {
        if ('questions' === $auditType) {
            return $this->renderQuestionsResult($url, $keyword, $body, $pageId, $cachedAt, $languageUid);
        }
        if ('gap' === $auditType) {
            return $this->renderGapResult($url, $body, $pageId, $cachedAt, $languageUid);
        }
        if ('cluster' === $auditType) {
            return $this->renderClusterResult($url, $keyword, $body, $pageId, $cachedAt, $languageUid);
        }
        if ('competitors' === $auditType) {
            return $this->renderCompetitorResult($url, $body, $pageId, $cachedAt, $languageUid);
        }
        $audit = \is_array($body['audit'] ?? null) ? $body['audit'] : [];
        // Ein-Klick-Aktionen schreiben nur in die Standardsprache
        $actionPageId = 0 === $languageUid ? $pageId : 0;
        $currentKeywords = $actionPageId > 0 ? $this->pageKeyword($actionPageId) : '';
        $fixedIssues = \is_array($body['fixedIssues'] ?? null) ? $body['fixedIssues'] : [];
        $adviceByIssue = \is_array($body['adviceByIssue'] ?? null) ? $body['adviceByIssue'] : [];
        $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/audit/fix-wizard.js');

        $keywordCandidates = $this->withDifficultyDisplay(\is_array($body['keywordCandidates'] ?? null) ? $body['keywordCandidates'] : []);
        $keywordData = \is_array($body['keyword'] ?? null) ? $body['keyword'] : null;
        if (null !== $keywordData && isset($keywordData['difficulty'])) {
            $keywordData['difficultyDisplay'] = ((int) $keywordData['difficulty']).'/100';
        }
        $this->view->assignMultiple([
            'auditType' => $auditType,
            'auditTypeLabel' => $this->auditTypeLabel($auditType, $audit),
            'keywordCandidates' => $keywordCandidates,
            'showCandidatesButton' => 'seo' === $auditType && $actionPageId > 0 && '' === $keyword && [] === $keywordCandidates,
            'url' => $url,
            'keyword' => $keyword,
            'pageId' => $pageId,
            'languageUid' => $languageUid,
            'cachedAt' => $cachedAt,
            'audit' => $audit,
            'score' => AuditScoreUtility::fromSummary(\is_array($audit['summary'] ?? null) ? $audit['summary'] : []),
            'scoreRange' => AuditScoreUtility::range(AuditScoreUtility::fromSummary(\is_array($audit['summary'] ?? null) ? $audit['summary'] : [])),
            'issueGroups' => $this->groupIssuesByFixability($audit['issues'] ?? [], $actionPageId, $fixedIssues, $adviceByIssue),
            'keywordData' => $keywordData,
            'canSaveKeyword' => 'seo' === $auditType && $actionPageId > 0 && '' !== $keyword && $keyword !== $currentKeywords,
        ]);

        return $this->view->renderResponse('Audit/Result');
    }

    /**
     * @param array<int, array<string, mixed>> $issues
     * @param list<string>                     $fixedIssues
     * @param array<string, list<string>>      $adviceByIssue
     *
     * @return list<array{fixability: string, label: string, fixableIssueIdsJson: string, issues: list<array<string, mixed>>}>
     */
    private function groupIssuesByFixability(array $issues, int $pageId = 0, array $fixedIssues = [], array $adviceByIssue = []): array
    {
        $grouped = array_fill_keys(self::FIXABILITY_ORDER, []);
        $fixableIds = array_fill_keys(self::FIXABILITY_ORDER, []);
        foreach ($issues as $issue) {
            if (!\is_array($issue)) {
                continue;
            }
            $level = \in_array($issue['fixability'] ?? '', self::FIXABILITY_ORDER, true) ? $issue['fixability'] : 'manual';
            $issue['severityBadge'] = match ($issue['severity'] ?? '') {
                'error' => 'danger',
                'warning' => 'warning',
                default => 'info',
            };
            $issueId = (string) ($issue['id'] ?? '');
            $issue['fixed'] = \in_array($issueId, $fixedIssues, true);
            $issue['aiFixable'] = $pageId > 0 && !$issue['fixed'] && $this->isAiFixable($issueId);
            if ($issue['aiFixable']) {
                $fixableIds[$level][$issueId] = $issueId;
            }
            $issue['authorboxAction'] = 'eeat-author-missing' === $issueId && $pageId > 0 && !$issue['fixed'];
            $issue['advice'] = \is_array($adviceByIssue[$issueId] ?? null) ? $adviceByIssue[$issueId] : [];
            $issue['adviceable'] = $pageId > 0 && 'ai-assist' === $level && [] === $issue['advice'];
            $issue['hasMoreInfo'] = '' !== (string) ($issue['hint'] ?? '')
                || '' !== (string) ($issue['docUrl'] ?? '')
                || [] !== $issue['advice']
                || $issue['adviceable'];
            $grouped[$level][] = $issue;
        }

        $groups = [];
        foreach ($grouped as $level => $groupIssues) {
            if ([] === $groupIssues) {
                continue;
            }
            $groups[] = [
                'fixability' => $level,
                'label' => $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.audit.fixability.'.$level),
                'fixableIssueIdsJson' => [] === $fixableIds[$level] ? '' : (string) json_encode(array_values($fixableIds[$level])),
                'issues' => $groupIssues,
            ];
        }

        return $groups;
    }

    private function isAiFixable(string $issueId): bool
    {
        return isset(self::FIX_METADATA_FIELDS[$issueId]) || $this->isAltFixIssue($issueId);
    }

    private function isAltFixIssue(string $issueId): bool
    {
        return 'images-missing-alt' === $issueId || str_contains($issueId, 'alt');
    }

    /**
     * @return array<string, array{runTs: int, date: string, keyword: string, viewUrl: string}>
     */
    private function lastAudits(int $pageId, int $languageUid = 0): array
    {
        if ($pageId <= 0) {
            return [];
        }
        $result = [];
        foreach (self::AUDIT_TYPES as $type) {
            $cached = $this->auditResults->findLatest($pageId, $type, $languageUid);
            if (null !== $cached) {
                $result[$type] = [
                    'runTs' => $cached['runTs'],
                    'date' => date('d.m.Y H:i', $cached['runTs']),
                    'keyword' => $cached['keyword'],
                    'viewUrl' => (string) $this->uriBuilder->buildUriFromRoute('ai_suite_audit_cached', ['pageId' => $pageId, 'auditType' => $type, 'languageUid' => $languageUid]),
                ];
            }
        }

        return $result;
    }

    /**
     * @return list<array{uid: int, title: string}>
     */
    private function auditablePageLanguages(int $pageId): array
    {
        if ($pageId <= 0) {
            return [];
        }
        $languages = [];

        try {
            foreach (GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($pageId)->getLanguages() as $siteLanguage) {
                $uid = $siteLanguage->getLanguageId();
                if ($uid > 0 && null === BackendUtility::getRecordLocalization('pages', $pageId, $uid)) {
                    continue;
                }
                if ($uid >= 0) {
                    $languages[] = ['uid' => $uid, 'title' => $siteLanguage->getTitle()];
                }
            }
        } catch (\Throwable) {
            return [];
        }

        return $languages;
    }

    private function pageKeyword(int $pageId): string
    {
        $page = BackendUtility::getRecord('pages', $pageId, 'keywords');

        return trim((string) ($page['keywords'] ?? ''));
    }

    /**
     * @param array<string, mixed> $audit
     */
    private function auditTypeLabel(string $auditType, array $audit): string
    {
        $label = $this->aiSuiteContext->localizationService->translate(
            'seo' === $auditType ? 'module:aiSuite.module.audit.type.seo' : 'module:aiSuite.module.audit.type.a11y'
        );
        $standard = (string) ($audit['a11y']['standard'] ?? '');
        if ('a11y' === $auditType && '' !== $standard) {
            $label .= ' ('.$standard.')';
        }

        return $label;
    }

    /**
     * @param array<string, mixed> $parsedBody
     */
    private function resolveMarket(array $parsedBody, int $pageId): string
    {
        $market = str_replace('_', '-', trim((string) ($parsedBody['market'] ?? '')));
        if (1 === preg_match('/^([a-zA-Z]{2})-([a-zA-Z]{2})$/', $market, $matches)) {
            return strtolower($matches[1]).'-'.strtoupper($matches[2]);
        }

        return $this->marketForPage($pageId);
    }

    private function marketForPage(int $pageId): string
    {
        if ($pageId > 0) {
            try {
                $market = $this->localeToMarket(GeneralUtility::makeInstance(SiteFinder::class)
                    ->getSiteByPageId($pageId)->getDefaultLanguage()->getLocale());
                if (null !== $market) {
                    return $market;
                }
            } catch (\Throwable) {
                // keine Site auflösbar -> Default unten
            }
        }

        return 'de-DE';
    }

    private function localeToMarket(Locale $locale): ?string
    {
        $language = strtolower($locale->getLanguageCode());
        $country = strtoupper((string) $locale->getCountryCode());
        if ('' === $country) {
            $country = self::LANGUAGE_MAIN_MARKET[$language] ?? '';
        }

        return '' !== $language && '' !== $country ? $language.'-'.$country : null;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function collectMarkets(): array
    {
        $displayLanguage = $this->backendUserLanguage();
        $markets = [];

        try {
            foreach (GeneralUtility::makeInstance(SiteFinder::class)->getAllSites() as $site) {
                foreach ($site->getLanguages() as $siteLanguage) {
                    $market = $this->localeToMarket($siteLanguage->getLocale());
                    if (null === $market || isset($markets[$market])) {
                        continue;
                    }
                    [$language, $country] = explode('-', $market);
                    $countryName = \Locale::getDisplayRegion('und_'.$country, $displayLanguage) ?: $country;
                    if ($countryName === $country) {
                        // unaufgelöster Code = kaputtes Locale in der Site-Config
                        continue;
                    }
                    $markets[$market] = ['value' => $market, 'label' => sprintf('%s (%s)', $countryName, $language)];
                }
            }
        } catch (\Throwable) {
            // keine Sites -> Default unten
        }
        if ([] === $markets) {
            $markets['de-DE'] = ['value' => 'de-DE', 'label' => (\Locale::getDisplayRegion('und_DE', $displayLanguage) ?: 'DE').' (de)'];
        }
        $markets = array_values($markets);
        usort($markets, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return $markets;
    }

    private function configuredAuditModel(): string
    {
        try {
            return trim((string) ($this->extensionConfiguration->get('ai_suite')['auditDefaultTextModel'] ?? ''));
        } catch (\Throwable) {
            return '';
        }
    }

    private function auditTextModel(): string
    {
        $configured = $this->configuredAuditModel();

        return '' !== $configured ? $configured : $this->firstTextModel();
    }

    private function wcagStandard(): string
    {
        try {
            $standard = (string) ($this->extensionConfiguration->get('ai_suite')['auditWcagStandard'] ?? '');
        } catch (\Throwable) {
            $standard = '';
        }

        return \in_array($standard, ['WCAG2A', 'WCAG2AA', 'WCAG2AAA'], true) ? $standard : 'WCAG2AA';
    }

    private function backendUserLanguage(): string
    {
        $lang = (string) ($this->aiSuiteContext->backendUserService->getBackendUser()?->user['lang'] ?? 'default');

        return 'default' === $lang ? 'en' : substr($lang, 0, 2);
    }
}
