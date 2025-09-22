<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Controller\Page;

use AutoDudes\AiSuite\Domain\Repository\PagesRepository;
use AutoDudes\AiSuite\Service\MetadataService;
use AutoDudes\AiSuite\Service\SiteService;
use AutoDudes\AiSuite\Service\TranslationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class LocalizationController extends \TYPO3\CMS\Backend\Controller\Page\LocalizationController
{
    public const ACTION_LOCALIZE_OPEN_AI = 'localizeChatGPT';
    public const ACTION_LOCALIZE_ANTHROPIC = 'localizeAnthropic';
    public const ACTION_LOCALIZE_GOOGLE_TRANSLATE = 'localizeGoogleTranslate';
    public const ACTION_LOCALIZE_DEEPL = 'localizeDeepl';
    public const ACTION_LOCALIZE_AISUITETEXTULTIMATE = 'localizeAiSuiteTextUltimate';
    public const ACTION_COPY_OPEN_AI = 'copyFromLanguageChatGPT';
    public const ACTION_COPY_ANTHROPIC = 'copyFromLanguageAnthropic';
    public const ACTION_COPY_GOOGLE_TRANSLATE = 'copyFromLanguageGoogleTranslate';
    public const ACTION_COPY_DEEPL = 'copyFromLanguageDeepl';
    public const ACTION_COPY_AISUITETEXTULTIMATE = 'copyFromLanguageAiSuiteTextUltimate';

    // Whole page translation actions
    public const ACTION_LOCALIZE_WHOLE_PAGE_OPEN_AI = 'localizeWholePageChatGPT';
    public const ACTION_LOCALIZE_WHOLE_PAGE_ANTHROPIC = 'localizeWholePageAnthropic';
    public const ACTION_LOCALIZE_WHOLE_PAGE_GOOGLE_TRANSLATE = 'localizeWholePageGoogleTranslate';
    public const ACTION_LOCALIZE_WHOLE_PAGE_DEEPL = 'localizeWholePageDeepl';
    public const ACTION_LOCALIZE_WHOLE_PAGE_AISUITETEXTULTIMATE = 'localizeWholePageAiSuiteTextUltimate';

    protected MetadataService $metadataService;
    protected TranslationService $translationService;

    protected PagesRepository $pagesRepository;

    public function __construct()
    {
        parent::__construct();
        $this->metadataService = GeneralUtility::makeInstance(MetadataService::class);
        $this->translationService = GeneralUtility::makeInstance(TranslationService::class);
        $this->pagesRepository = GeneralUtility::makeInstance(PagesRepository::class);
    }

    public function getRecordLocalizeSummary(ServerRequestInterface $request): ResponseInterface
    {
        $response = parent::getRecordLocalizeSummary($request);

        if (ExtensionManagementUtility::isLoaded('container')) {
            $payload = json_decode($response->getBody()->getContents(), true);
            $recordLocalizeSummaryModifier = GeneralUtility::makeInstance(\B13\Container\Service\RecordLocalizeSummaryModifier::class);
            $payload = $recordLocalizeSummaryModifier->rebuildPayload($payload);

            $payload = $this->enhanceLocalizationSummary($payload, $request);
            return new JsonResponse($payload);
        }

        $payload = json_decode($response->getBody()->getContents(), true);
        $payload = $this->enhanceLocalizationSummary($payload, $request);
        return new JsonResponse($payload);
    }

    protected function enhanceLocalizationSummary(array $payload, ServerRequestInterface $request): array
    {
        $params = $request->getQueryParams();

        if (!isset($params['pageId'], $params['languageId'])) {
            return $payload;
        }

        $pageId = (int)$params['pageId'];
        $srcLanguageId = (int)$params['languageId'];
        $destLanguageId = (int)($params['destLanguageId']);

        try {
            $payload['pageMetadata'] = $this->metadataService->getPageMetadataForTranslation($pageId);
            $payload['pageTranslationNecessary'] = !$this->pagesRepository->checkPageTranslationExists($pageId, $destLanguageId);
        } catch (\Exception $e) {
            $payload['pageTranslationNecessary'] = false;
        }

        return $payload;
    }

    public function localizeRecords(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();

        if (!isset($params['pageId'], $params['srcLanguageId'], $params['destLanguageId'], $params['action'], $params['uidList'])) {
            return new JsonResponse(null, 400);
        }

        if ($this->isWholePageTranslationAction($params['action'])) {
            $this->processWholePageTranslation($params);
            $params['action'] = str_replace('localizeWholePage', 'localize', $params['action']);
        }

        if (
            $params['action'] !== static::ACTION_COPY
            && $params['action'] !== static::ACTION_LOCALIZE
            && $params['action'] !== static::ACTION_LOCALIZE_OPEN_AI
            && $params['action'] !== static::ACTION_LOCALIZE_ANTHROPIC
            && $params['action'] !== static::ACTION_LOCALIZE_GOOGLE_TRANSLATE
            && $params['action'] !== static::ACTION_LOCALIZE_DEEPL
            && $params['action'] !== static::ACTION_LOCALIZE_AISUITETEXTULTIMATE
            && $params['action'] !== static::ACTION_COPY_OPEN_AI
            && $params['action'] !== static::ACTION_COPY_ANTHROPIC
            && $params['action'] !== static::ACTION_COPY_GOOGLE_TRANSLATE
            && $params['action'] !== static::ACTION_COPY_DEEPL
            && $params['action'] !== static::ACTION_COPY_AISUITETEXTULTIMATE
            && $params['action'] !== static::ACTION_LOCALIZE_WHOLE_PAGE_OPEN_AI
            && $params['action'] !== static::ACTION_LOCALIZE_WHOLE_PAGE_ANTHROPIC
            && $params['action'] !== static::ACTION_LOCALIZE_WHOLE_PAGE_GOOGLE_TRANSLATE
            && $params['action'] !== static::ACTION_LOCALIZE_WHOLE_PAGE_DEEPL
            && $params['action'] !== static::ACTION_LOCALIZE_WHOLE_PAGE_AISUITETEXTULTIMATE
        ) {
            $response = new Response('php://temp', 400, ['Content-Type' => 'application/json; charset=utf-8']);
            $response->getBody()->write('Invalid action "' . $params['action'] . '" called.');
            return $response;
        }

        $params['uidList'] = $this->filterInvalidUids(
            (int)$params['pageId'],
            (int)$params['destLanguageId'],
            $this->getSourceLanguageId($params['srcLanguageId']),
            $params['uidList']
        );

        $this->process($params);

        return new JsonResponse([]);
    }

    /**
     * Processes the localization actions
     *
     * @param array $params
     */
    protected function process($params): void
    {
        $destLanguageId = (int)$params['destLanguageId'];
        $srcLanguageId = (int)$params['srcLanguageId'];
        $pageId = (int)$params['pageId'];

        $cmd = [
            'tt_content' => [],
        ];

        if (isset($params['uidList']) && is_array($params['uidList'])) {
            foreach ($params['uidList'] as $currentUid) {
                if (
                    $params['action'] === static::ACTION_LOCALIZE
                    || $params['action'] === static::ACTION_LOCALIZE_OPEN_AI
                    || $params['action'] === static::ACTION_LOCALIZE_ANTHROPIC
                    || $params['action'] === static::ACTION_LOCALIZE_GOOGLE_TRANSLATE
                    || $params['action'] === static::ACTION_LOCALIZE_DEEPL
                    || $params['action'] === static::ACTION_LOCALIZE_AISUITETEXTULTIMATE
                ) {
                    $cmd['tt_content'][$currentUid] = [
                        'localize' => $destLanguageId,
                    ];

                    if ($params['action'] === static::ACTION_LOCALIZE_OPEN_AI
                        || $params['action'] === static::ACTION_LOCALIZE_ANTHROPIC
                        || $params['action'] === static::ACTION_LOCALIZE_GOOGLE_TRANSLATE
                        || $params['action'] === static::ACTION_LOCALIZE_DEEPL
                        || $params['action'] === static::ACTION_LOCALIZE_AISUITETEXTULTIMATE
                    ) {
                        $siteService = GeneralUtility::makeInstance(SiteService::class);
                        $cmd['localization'][0]['aiSuite']['translateAi'] = str_replace('localize', '', $params['action']);
                        $cmd['localization'][0]['aiSuite']['srcLangIsoCode'] = $siteService->getIsoCodeByLanguageId($srcLanguageId, $pageId);
                        $cmd['localization'][0]['aiSuite']['destLangIsoCode'] = $siteService->getIsoCodeByLanguageId($destLanguageId, $pageId);
                        $cmd['localization'][0]['aiSuite']['destLangId'] = $destLanguageId;
                        $cmd['localization'][0]['aiSuite']['srcLangId'] = $srcLanguageId;
                        $cmd['localization'][0]['aiSuite']['uuid'] = $params['uuid'];
                        $cmd['localization'][0]['aiSuite']['rootPageId'] = $siteService->getSiteRootPageId($pageId);
                    }
                } else {
                    $cmd['tt_content'][$currentUid] = [
                        'copyToLanguage' => $destLanguageId,
                    ];
                    if ($params['action'] === static::ACTION_COPY_OPEN_AI
                        || $params['action'] === static::ACTION_COPY_ANTHROPIC
                        || $params['action'] === static::ACTION_COPY_GOOGLE_TRANSLATE
                        || $params['action'] === static::ACTION_COPY_DEEPL
                        || $params['action'] === static::ACTION_COPY_AISUITETEXTULTIMATE
                    ) {
                        $siteService = GeneralUtility::makeInstance(SiteService::class);
                        $cmd['localization'][0]['aiSuite']['translateAi'] = str_replace('copyFromLanguage', '', $params['action']);
                        ;
                        $cmd['localization'][0]['aiSuite']['srcLangIsoCode'] = $siteService->getIsoCodeByLanguageId($srcLanguageId, $pageId);
                        $cmd['localization'][0]['aiSuite']['destLangIsoCode'] = $siteService->getIsoCodeByLanguageId($destLanguageId, $pageId);
                        $cmd['localization'][0]['aiSuite']['destLangId'] = $destLanguageId;
                        $cmd['localization'][0]['aiSuite']['srcLangId'] = $srcLanguageId;
                        $cmd['localization'][0]['aiSuite']['uuid'] = $params['uuid'];
                        $cmd['localization'][0]['aiSuite']['rootPageId'] = $siteService->getSiteRootPageId($pageId);
                    }
                }
            }
        }

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], $cmd);
        $dataHandler->process_cmdmap();
    }

    /**
     * Return source language ID from source language string
     */
    public function getSourceLanguageId(string $srcLanguage): int
    {
        $langParam = explode('-', $srcLanguage);
        if (count($langParam) > 1) {
            return (int)$langParam[1];
        }
        return (int)$langParam[0];
    }

    protected function isWholePageTranslationAction(string $action): bool
    {
        return in_array($action, [
            self::ACTION_LOCALIZE_WHOLE_PAGE_OPEN_AI,
            self::ACTION_LOCALIZE_WHOLE_PAGE_ANTHROPIC,
            self::ACTION_LOCALIZE_WHOLE_PAGE_GOOGLE_TRANSLATE,
            self::ACTION_LOCALIZE_WHOLE_PAGE_DEEPL,
            self::ACTION_LOCALIZE_WHOLE_PAGE_AISUITETEXTULTIMATE,
        ]);
    }

    protected function processWholePageTranslation(array $params): void
    {
        $pageId = (int)$params['pageId'];
        $srcLanguageId = (int)$params['srcLanguageId'];
        $destLanguageId = (int)$params['destLanguageId'];
        $action = $params['action'];
        $uuid = $params['uuid'];

        $this->localizePageMetadata($pageId, $srcLanguageId, $destLanguageId, $action, $uuid);
    }

    protected function localizePageMetadata(int $pageId, int $srcLanguageId, int $destLanguageId, string $action, string $uuid): void
    {
        $pageUid = $this->pagesRepository->checkPageTranslationExists($pageId, $destLanguageId);
        if (!$pageUid) {
            $cmd['pages'][$pageId] = [ 'localize' => $destLanguageId ];
        } else {
            $cmd['pages'][$pageUid] = [];
        }

        $siteService = GeneralUtility::makeInstance(SiteService::class);

        $cmd['localization'] = [
            0 => [
                'aiSuite' => [
                    'translateAi' => str_replace('localizeWholePage', '', $action),
                    'srcLangIsoCode' => $siteService->getIsoCodeByLanguageId($srcLanguageId, $pageId),
                    'destLangIsoCode' => $siteService->getIsoCodeByLanguageId($destLanguageId, $pageId),
                    'destLangId' => $destLanguageId,
                    'srcLangId' => $srcLanguageId,
                    'uuid' => $uuid,
                    'rootPageId' => $siteService->getSiteRootPageId($pageId),
                    'wholePageMode' => true,
                    'scope' => 'page',
                ],
            ],
        ];

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], $cmd);
        $dataHandler->process_cmdmap();
    }
}
