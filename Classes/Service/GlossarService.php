<?php

namespace AutoDudes\AiSuite\Service;

use AutoDudes\AiSuite\Domain\Repository\GlossarRepository;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\SingletonInterface;

class GlossarService implements SingletonInterface
{
    protected SendRequestService $sendRequestService;
    protected GlossarRepository $glossarRepository;
    protected SiteService $siteService;
    protected PageRepository $pageRepository;
    protected LoggerInterface $logger;
    protected ExtensionConfiguration $extensionConfiguration;

    protected array $extConf;

    public function __construct(GlossarRepository $glossarRepository,
        SendRequestService $sendRequestService,
        SiteService $siteService,
        PageRepository $pageRepository,
        LoggerInterface $logger,
        ExtensionConfiguration $extensionConfiguration
    ) {
        $this->sendRequestService = $sendRequestService;
        $this->glossarRepository = $glossarRepository;
        $this->siteService = $siteService;
        $this->pageRepository = $pageRepository;
        $this->logger = $logger;
        $this->extensionConfiguration = $extensionConfiguration;
        $this->extConf = $this->extensionConfiguration->get('ai_suite');
    }

    public function findGlossarEntries(string $jsonContent, int $destLangUid, int $srcLangUid): array {
        $glossary = [];
        $glossarExpressions = $this->glossarRepository->findBySysLanguageUid($destLangUid);
        foreach ($glossarExpressions as $glossarExpression) {
            if ($glossarExpression['l18n_parent'] > 0) {
                if($srcLangUid === 0) {
                    $parentExpression = $this->glossarRepository->findEntryByUid($glossarExpression['l18n_parent']);
                } else {
                    $parentExpression = $this->glossarRepository->findEntryByL18nParentAndUid($glossarExpression['l18n_parent'], $srcLangUid);
                }
                if(isset($parentExpression["input"]) && str_contains($jsonContent, $parentExpression["input"])) {
                    $glossary[$parentExpression["input"]] = $glossarExpression["input"];
                }
            }
        }
        return $glossary;
    }

    public function findDeeplGlossary(int $rootPageId, string $sourceLang, string $targetLang) {
        return $this->glossarRepository->findDeeplGlossaryEntry($rootPageId, $sourceLang, $targetLang);
    }

    public function syncDeeplGlossar($pid): bool
    {
        $rootPageId = $this->siteService->getSiteRootPageId($pid);
        $foundPages = $this->pageRepository->getDescendantPageIdsRecursive($rootPageId, 99);
        $glossarEntries = $this->glossarRepository->findAllEntriesForPages($foundPages);

        $defaultLanguageRecords = [];
        $translationRecords = [];
        foreach ($glossarEntries as $entry) {
            if ((int)$entry['sys_language_uid'] === 0) {
                $defaultLanguageRecords[$entry['uid']] = $entry;
            } elseif ((int)$entry['sys_language_uid'] > 0 && (int)$entry['l18n_parent'] > 0) {
                if (!isset($translationRecords[$entry['l18n_parent']])) {
                    $translationRecords[$entry['l18n_parent']] = [];
                }
                $translationRecords[$entry['l18n_parent']][(int)$entry['sys_language_uid']] = $entry;
            }
        }

        $defaultLanguageIsoCode = $this->siteService->getIsoCodeByLanguageId(0, $pid);
        $inputCombinations = [];
        foreach ($defaultLanguageRecords as $defaultUid => $defaultRecord) {
            $defaultInput = $defaultRecord['input'] ?? '';
            if (isset($translationRecords[$defaultUid])) {
                foreach ($translationRecords[$defaultUid] as $languageId => $translationRecord) {
                    $translationInput = $translationRecord['input'] ?? '';
                    $targetLanguageIsoCode = $this->siteService->getIsoCodeByLanguageId($languageId, $pid);
                    if(empty($targetLanguageIsoCode)) {
                        $this->logger->warning('Empty target language isoCode for languageId: ' . $languageId . ', input: ' . $translationInput . ', pid: ' . $pid);
                        continue;
                    }
                    $combinationKey = $defaultLanguageIsoCode . '__' . $targetLanguageIsoCode;
                    $inputCombinations[$combinationKey][$defaultInput] = $translationInput;
                }
            }
        }

        $deeplGlossaryUuids = [];
        $languageCombinations = array_keys($inputCombinations);
        $deeplGlossaries = $this->glossarRepository->findDeeplGlossaryUuidsByRootPageId($rootPageId);

        $existingGlossariesByLang = [];
        foreach ($deeplGlossaries as $glossary) {
            $langKey = strtolower($glossary['source_lang']) . '__' . strtolower($glossary['target_lang']);
            $existingGlossariesByLang[$langKey] = $glossary['glossar_uuid'];
        }

        foreach ($languageCombinations as $languageCombination) {
            $langIsoCodes = explode('__', $languageCombination);

            if (count($langIsoCodes) !== 2) {
                continue;
            }

            $sourceLang = strtolower($langIsoCodes[0]);
            $targetLang = strtolower($langIsoCodes[1]);
            $langKey = $sourceLang . '__' . $targetLang;

            if (isset($existingGlossariesByLang[$langKey])) {
                $deeplGlossaryUuids[$languageCombination] = $existingGlossariesByLang[$langKey];
            }
        }

        $answer = $this->sendRequestService->sendDataRequest(
            'glossary',
            [
                'glossaries' => json_encode($inputCombinations, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE),
                'deepl_glossary_uuids' => json_encode($deeplGlossaryUuids),
            ],
            '',
            strtoupper($defaultLanguageIsoCode),
            [
                'translate' => 'DeeplGlossaryManager'
            ]
        );
        if ($answer->getType() === 'Error') {
            $this->logger->error('Error while synchronizing glossary: ' . $answer->getResponseData()['message']);
            return false;
        } else {
            $createdGlossaries = $answer->getResponseData()['createdGlossaries'];
            foreach ($createdGlossaries as $glossaryId => $glossaryName) {
                $nameParts = explode('__', $glossaryName);
                $existingRecord = $this->glossarRepository->findDeeplGlossaryEntry($rootPageId, $nameParts[1], $nameParts[2]);
                $this->glossarRepository->insertOrUpdateDeeplGlossaryEntry($existingRecord, $glossaryId, $rootPageId, $nameParts);
            }
        }
        return true;
    }
}
