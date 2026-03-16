<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Localization\Handler;

use AutoDudes\AiSuite\Domain\Repository\PagesRepository;
use AutoDudes\AiSuite\Service\BackendUserService;
use AutoDudes\AiSuite\Service\SiteService;
use TYPO3\CMS\Backend\Localization\Finisher\ReloadLocalizationFinisher;
use TYPO3\CMS\Backend\Localization\LocalizationHandlerInterface;
use TYPO3\CMS\Backend\Localization\LocalizationInstructions;
use TYPO3\CMS\Backend\Localization\LocalizationResult;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class AbstractAiLocalizationHandler implements LocalizationHandlerInterface
{
    public function __construct(
        protected readonly SiteService $siteService,
        protected readonly BackendUserService $backendUserService,
        protected readonly PagesRepository $pagesRepository,
    ) {}

    public function isAvailable(LocalizationInstructions $instructions): bool
    {
        return $this->backendUserService->checkPermissions('tx_aisuite_features:enable_translation')
            && $this->backendUserService->checkPermissions('tx_aisuite_models:'.$this->getModelPermissionKey());
    }

    public function processLocalization(LocalizationInstructions $instructions): LocalizationResult
    {
        $pageId = $instructions->recordUid;
        $srcLanguageId = $instructions->sourceLanguageId;
        $destLanguageId = $instructions->targetLanguageId;
        $additionalData = $instructions->additionalData;

        $uuid = (string) ($additionalData['uuid'] ?? '');
        $wholePageMode = (bool) ($additionalData['wholePageMode'] ?? false);
        $selectedRecordUids = $additionalData['selectedRecordUids'] ?? [];

        $srcLangIsoCode = $this->siteService->getIsoCodeByLanguageId($srcLanguageId, $pageId);
        $destLangIsoCode = $this->siteService->getIsoCodeByLanguageId($destLanguageId, $pageId);
        $rootPageId = $this->siteService->getSiteRootPageId($pageId);

        $aiSuiteBase = [
            'translateAi' => $this->getModelPermissionKey(),
            'srcLangIsoCode' => $srcLangIsoCode,
            'destLangIsoCode' => $destLangIsoCode,
            'destLangId' => $destLanguageId,
            'srcLangId' => $srcLanguageId,
            'uuid' => $uuid,
            'rootPageId' => $rootPageId,
            'pageId' => $pageId,
        ];

        if ($wholePageMode) {
            $this->processWholePageMetadataTranslation($pageId, $destLanguageId, $aiSuiteBase);
        }

        if (!empty($selectedRecordUids)) {
            $this->processContentTranslation(
                $pageId,
                $destLanguageId,
                array_map('intval', $selectedRecordUids),
                $instructions->mode->getDataHandlerCommand(),
                $aiSuiteBase
            );
        }

        return LocalizationResult::success(new ReloadLocalizationFinisher());
    }

    abstract protected function getModelPermissionKey(): string;

    protected function processWholePageMetadataTranslation(
        int $pageId,
        int $destLanguageId,
        array $aiSuiteBase
    ): void {
        $existingPageUid = $this->pagesRepository->checkPageTranslationExists($pageId, $destLanguageId);

        $cmd = [];
        if (!$existingPageUid) {
            $cmd['pages'][$pageId] = ['localize' => $destLanguageId];
        } else {
            $cmd['pages'][$existingPageUid] = [];
        }

        $cmd['localization'][0]['aiSuite'] = array_merge($aiSuiteBase, [
            'wholePageMode' => true,
            'scope' => 'page',
        ]);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], $cmd);
        $dataHandler->process_cmdmap();
    }

    protected function processContentTranslation(
        int $pageId,
        int $destLanguageId,
        array $selectedRecordUids,
        string $dataHandlerCommand,
        array $aiSuiteBase
    ): void {
        if (!$this->pagesRepository->checkPageTranslationExists($pageId, $destLanguageId)) {
            $pageCmd = ['pages' => [$pageId => ['localize' => $destLanguageId]]];
            $pageDataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $pageDataHandler->start([], $pageCmd);
            $pageDataHandler->process_cmdmap();
        }

        $cmd = ['tt_content' => []];
        foreach ($selectedRecordUids as $uid) {
            $cmd['tt_content'][$uid] = [$dataHandlerCommand => $destLanguageId];
        }

        $cmd['localization'][0]['aiSuite'] = $aiSuiteBase;

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], $cmd);
        $dataHandler->process_cmdmap();
    }
}
