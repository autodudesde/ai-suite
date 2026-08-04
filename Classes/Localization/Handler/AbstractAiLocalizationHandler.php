<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Localization\Handler;

use AutoDudes\AiSuite\Domain\Repository\PagesRepository;
use AutoDudes\AiSuite\Service\BackendUserService;
use AutoDudes\AiSuite\Service\LocalizationService;
use AutoDudes\AiSuite\Service\SiteService;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Localization\Finisher\ReloadLocalizationFinisher;
use TYPO3\CMS\Backend\Localization\LocalizationHandlerInterface;
use TYPO3\CMS\Backend\Localization\LocalizationInstructions;
use TYPO3\CMS\Backend\Localization\LocalizationResult;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class AbstractAiLocalizationHandler implements LocalizationHandlerInterface
{
    public function __construct(
        protected readonly SiteService $siteService,
        protected readonly BackendUserService $backendUserService,
        protected readonly PagesRepository $pagesRepository,
        protected readonly LocalizationService $localizationService,
        protected readonly LoggerInterface $logger,
    ) {}

    public function isAvailable(LocalizationInstructions $instructions): bool
    {
        return $this->backendUserService->checkPermissions('tx_aisuite_features:enable_translation')
            && $this->backendUserService->checkPermissions('tx_aisuite_models:'.$this->getModelPermissionKey());
    }

    public function processLocalization(LocalizationInstructions $instructions): LocalizationResult
    {
        $recordType = $instructions->mainRecordType;
        $recordUid = $instructions->recordUid;
        $srcLanguageId = $instructions->sourceLanguageId;
        $destLanguageId = $instructions->targetLanguageId;
        $additionalData = $instructions->additionalData;

        if ('pages' === $recordType) {
            $pageId = $recordUid;
        } else {
            $record = BackendUtility::getRecord($recordType, $recordUid, 'pid');
            if (null === $record) {
                return LocalizationResult::error([
                    $this->localizationService->translate(
                        'aiSuite.error.localization.recordNotFound',
                        [$recordType, $recordUid]
                    ),
                ]);
            }
            $pageId = (int) $record['pid'];
        }

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

        if ('pages' !== $recordType) {
            $errorLog = $this->processSingleRecordTranslation(
                $recordType,
                $recordUid,
                $pageId,
                $destLanguageId,
                $instructions->mode->getDataHandlerCommand(),
                $aiSuiteBase
            );
        } elseif ($wholePageMode) {
            $errorLog = $this->processWholePageTranslation(
                $pageId,
                $destLanguageId,
                array_map('intval', $selectedRecordUids),
                $instructions->mode->getDataHandlerCommand(),
                $aiSuiteBase
            );
        } elseif (!empty($selectedRecordUids)) {
            $errorLog = $this->processContentTranslation(
                $pageId,
                $destLanguageId,
                array_map('intval', $selectedRecordUids),
                $instructions->mode->getDataHandlerCommand(),
                $aiSuiteBase
            );
        } else {
            $errorLog = [];
        }

        if ([] !== $errorLog) {
            $this->logger->warning('DataHandler reported problems while localizing', [
                'recordType' => $recordType,
                'recordUid' => $recordUid,
                'pageId' => $pageId,
                'targetLanguageId' => $destLanguageId,
                'errors' => $errorLog,
            ]);
        }

        return LocalizationResult::success(new ReloadLocalizationFinisher());
    }

    abstract protected function getModelPermissionKey(): string;

    /**
     * @param int[]                $selectedRecordUids
     * @param array<string, mixed> $aiSuiteBase
     *
     * @return list<string>
     */
    protected function processWholePageTranslation(
        int $pageId,
        int $destLanguageId,
        array $selectedRecordUids,
        string $dataHandlerCommand,
        array $aiSuiteBase
    ): array {
        $cmd = [];

        if (!$this->pagesRepository->checkPageTranslationExists($pageId, $destLanguageId)) {
            $cmd['pages'][$pageId] = ['localize' => $destLanguageId];
        }

        foreach ($selectedRecordUids as $uid) {
            $cmd['tt_content'][$uid] = [$dataHandlerCommand => $destLanguageId];
        }

        $cmd['localization'][0]['aiSuite'] = array_merge($aiSuiteBase, [
            'wholePageMode' => true,
        ]);

        return $this->executeCommandMap($cmd);
    }

    /**
     * @param int[]                $selectedRecordUids
     * @param array<string, mixed> $aiSuiteBase
     *
     * @return list<string>
     */
    protected function processContentTranslation(
        int $pageId,
        int $destLanguageId,
        array $selectedRecordUids,
        string $dataHandlerCommand,
        array $aiSuiteBase
    ): array {
        $errorLog = $this->localizePageIfMissing($pageId, $destLanguageId);

        $cmd = ['tt_content' => []];
        foreach ($selectedRecordUids as $uid) {
            $cmd['tt_content'][$uid] = [$dataHandlerCommand => $destLanguageId];
        }

        $cmd['localization'][0]['aiSuite'] = $aiSuiteBase;

        return array_merge($errorLog, $this->executeCommandMap($cmd));
    }

    /**
     * @param array<string, mixed> $aiSuiteBase
     *
     * @return list<string>
     */
    protected function processSingleRecordTranslation(
        string $table,
        int $recordUid,
        int $pageId,
        int $destLanguageId,
        string $dataHandlerCommand,
        array $aiSuiteBase
    ): array {
        $errorLog = 'tt_content' === $table
            ? $this->localizePageIfMissing($pageId, $destLanguageId)
            : [];

        $cmd = [
            $table => [
                $recordUid => [$dataHandlerCommand => $destLanguageId],
            ],
        ];
        $cmd['localization'][0]['aiSuite'] = $aiSuiteBase;

        return array_merge($errorLog, $this->executeCommandMap($cmd));
    }

    /**
     * @return list<string>
     */
    protected function localizePageIfMissing(int $pageId, int $destLanguageId): array
    {
        if ($this->pagesRepository->checkPageTranslationExists($pageId, $destLanguageId)) {
            return [];
        }

        return $this->executeCommandMap(['pages' => [$pageId => ['localize' => $destLanguageId]]]);
    }

    /**
     * @param array<string, mixed> $cmd
     *
     * @return list<string>
     */
    protected function executeCommandMap(array $cmd): array
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], $cmd);
        $dataHandler->process_cmdmap();

        return array_values(array_map(static fn ($message): string => (string) $message, $dataHandler->errorLog));
    }
}
