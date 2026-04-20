<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

use AutoDudes\AiSuite\Domain\Repository\GlossarRepository;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\SingletonInterface;

class GlossarService implements SingletonInterface
{
    /** @var array<string, mixed> */
    protected array $extConf;

    public function __construct(
        protected readonly GlossarRepository $glossarRepository,
        protected readonly SendRequestService $sendRequestService,
        protected readonly SiteService $siteService,
        protected readonly PageRepository $pageRepository,
        protected readonly LoggerInterface $logger,
        protected readonly ExtensionConfiguration $extensionConfiguration,
        protected readonly LocalizationService $localizationService,
    ) {
        $this->extConf = $this->extensionConfiguration->get('ai_suite');
    }

    /**
     * @return array<string, mixed>
     */
    public function findGlossarEntries(string $jsonContent, int $destLangUid, int $srcLangUid): array
    {
        $glossary = [];
        $glossarExpressions = $this->glossarRepository->findBySysLanguageUid($destLangUid);
        foreach ($glossarExpressions as $glossarExpression) {
            if ($glossarExpression['l18n_parent'] > 0) {
                if (0 === $srcLangUid) {
                    $parentExpression = $this->glossarRepository->findEntryByUid($glossarExpression['l18n_parent']);
                } else {
                    $parentExpression = $this->glossarRepository->findEntryByL18nParentAndUid($glossarExpression['l18n_parent'], $srcLangUid);
                }
                if (isset($parentExpression['input']) && str_contains($jsonContent, $parentExpression['input'])) {
                    $glossary[$parentExpression['input']] = $glossarExpression['input'];
                }
            }
        }

        return $glossary;
    }

    /**
     * @return array<string, mixed>
     */
    public function findDeeplGlossary(int $rootPageId, int $sourceLangId, int $targetLangId): array|false
    {
        return $this->glossarRepository->findDeeplGlossaryEntry($rootPageId, $sourceLangId, $targetLangId);
    }

    public function syncDeeplGlossar(int $pid, int $defaultLanguageId = 0): bool
    {
        try {
            $rootPageId = $this->siteService->getSiteRootPageId($pid);
            $foundPages = $this->pageRepository->getDescendantPageIdsRecursive($rootPageId, 99);
            $glossarEntries = $this->glossarRepository->findAllEntriesForPages(array_values($foundPages));

            $defaultLanguageRecords = [];
            $translationRecords = [];
            foreach ($glossarEntries as $entry) {
                if ((int) $entry['sys_language_uid'] === $defaultLanguageId) {
                    $defaultLanguageRecords[$entry['uid']] = $entry;
                } elseif ((int) $entry['sys_language_uid'] > 0 && (int) $entry['l18n_parent'] > 0) {
                    if (!isset($translationRecords[$entry['l18n_parent']])) {
                        $translationRecords[$entry['l18n_parent']] = [];
                    }
                    $translationRecords[$entry['l18n_parent']][(int) $entry['sys_language_uid']] = $entry;
                }
            }

            $defaultLanguageIsoCode = $this->siteService->getIsoCodeByLanguageId($defaultLanguageId, $pid);
            $inputCombinations = [];
            $languageIdCombinations = [];

            foreach ($defaultLanguageRecords as $defaultUid => $defaultRecord) {
                $defaultInput = $defaultRecord['input'] ?? '';
                if (isset($translationRecords[$defaultUid])) {
                    foreach ($translationRecords[$defaultUid] as $languageId => $translationRecord) {
                        $translationInput = $translationRecord['input'] ?? '';
                        $targetLanguageIsoCode = $this->siteService->getIsoCodeByLanguageId($languageId, $pid);
                        if (empty($targetLanguageIsoCode)) {
                            $this->logger->warning('Empty target language isoCode for languageId: '.$languageId.', input: '.$translationInput.', pid: '.$pid);

                            continue;
                        }
                        $combinationKey = $defaultLanguageIsoCode.'__'.$targetLanguageIsoCode;
                        $inputCombinations[$combinationKey][$defaultInput] = $translationInput;
                        $languageIdCombinations[$combinationKey] = [
                            'defaultLanguageId' => $defaultLanguageId,
                            'targetLanguageId' => $languageId,
                        ];
                    }
                }
            }

            $deeplGlossaryUuids = [];
            $languageCombinations = array_keys($inputCombinations);
            $deeplGlossaries = $this->glossarRepository->findDeeplGlossaryUuidsByRootPageId($rootPageId);

            $existingGlossariesByLang = [];
            foreach ($deeplGlossaries as $glossary) {
                $langKey = strtolower($glossary['source_lang']).'__'.strtolower($glossary['target_lang']);
                $existingGlossariesByLang[$langKey] = $glossary['glossar_uuid'];
            }

            foreach ($languageCombinations as $languageCombination) {
                $langIsoCodes = explode('__', $languageCombination);

                if (2 !== count($langIsoCodes)) {
                    continue;
                }

                $sourceLang = strtolower($langIsoCodes[0]);
                $targetLang = strtolower($langIsoCodes[1]);
                $langKey = $sourceLang.'__'.$targetLang;

                if (isset($existingGlossariesByLang[$langKey])) {
                    $deeplGlossaryUuids[$languageCombination] = $existingGlossariesByLang[$langKey];
                }
            }

            $answer = $this->sendRequestService->sendDataRequest(
                'glossary',
                [
                    'glossaries' => json_encode($inputCombinations, SendRequestService::JSON_SAFE_FLAGS),
                    'deepl_glossary_uuids' => json_encode($deeplGlossaryUuids),
                ],
                '',
                strtoupper($defaultLanguageIsoCode),
                [
                    'translate' => 'DeeplGlossaryManager',
                ]
            );
            if ('Error' === $answer->getType()) {
                $this->logger->error('Error while synchronizing glossary: '.$answer->getResponseData()['message']);

                return false;
            }
            $createdGlossaries = $answer->getResponseData()['createdGlossaries'];
            foreach ($createdGlossaries as $glossaryId => $glossaryName) {
                $nameParts = explode('__', $glossaryName);
                $combinationKey = $nameParts[1].'__'.$nameParts[2];
                $langIds = $languageIdCombinations[$combinationKey];
                $existingRecord = $this->glossarRepository->findDeeplGlossaryEntry($rootPageId, (int) $langIds['defaultLanguageId'], (int) $langIds['targetLanguageId']);

                $this->glossarRepository->insertOrUpdateDeeplGlossaryEntry(
                    $existingRecord,
                    $glossaryId,
                    $rootPageId,
                    $nameParts,
                    $langIds['defaultLanguageId'],
                    $langIds['targetLanguageId']
                );
            }

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error while syncing DeepL glossaries: '.$e->getMessage());

            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getAvailableGlossariesForFileTranslation(
        int $sourceLanguageId,
        int $targetLanguageId,
        string $sourceLanguageIso,
        string $targetLanguageIso,
        string $textAiModel
    ): array {
        $availableGlossaries = [];

        if (empty($sourceLanguageIso) || empty($targetLanguageIso)) {
            return $availableGlossaries;
        }

        $hasGlossaryEntries = $this->hasGlossaryEntriesForLanguageCombination($sourceLanguageId, $targetLanguageId);

        if ($hasGlossaryEntries) {
            if ('Deepl' === $textAiModel) {
                $glossaries = $this->glossarRepository->findDeeplGlossaryUuidsBySourceAndTargetLanguage($sourceLanguageIso, $targetLanguageIso);

                foreach ($glossaries as $glossary) {
                    $rootPageId = $glossary['root_page_uid'];
                    $siteName = $this->siteService->getDomainByRootPageId($rootPageId);
                    $key = $rootPageId.'__'.$sourceLanguageId.'__'.$targetLanguageId;
                    $labelArguments = [
                        strtoupper($sourceLanguageIso),
                        strtoupper($targetLanguageIso),
                        $siteName,
                    ];
                    $label = $this->localizationService->translate('aiSuite.generation.workflow.selectGlossaryLabel', $labelArguments);

                    if (array_key_exists('external', $glossary) && $glossary['external']) {
                        $label .= $this->localizationService->translate('aiSuite.generation.workflow.selectGlossaryExternal');
                    }

                    $availableGlossaries[$key] = $label;
                }
            } else {
                $rootPageUids = $this->glossarRepository->findDistinctRootPageUidsWithGlossaryEntries();

                foreach ($rootPageUids as $rootPageId) {
                    $siteName = $this->siteService->getDomainByRootPageId($rootPageId);
                    $key = $rootPageId.'__'.$sourceLanguageId.'__'.$targetLanguageId;
                    $labelArguments = [
                        strtoupper($sourceLanguageIso),
                        strtoupper($targetLanguageIso),
                        $siteName,
                    ];
                    $availableGlossaries[$key] = $this->localizationService->translate('aiSuite.generation.workflow.selectGlossaryLabel', $labelArguments);
                }
            }
        }

        return $availableGlossaries;
    }

    public function hasGlossaryEntriesForLanguageCombination(int $sourceLanguageId, int $targetLanguageId): bool
    {
        $glossarExpressions = $this->glossarRepository->findBySysLanguageUid($targetLanguageId);

        foreach ($glossarExpressions as $glossarExpression) {
            if ($glossarExpression['l18n_parent'] > 0) {
                if (0 === $sourceLanguageId) {
                    $parentExpression = $this->glossarRepository->findEntryByUid($glossarExpression['l18n_parent']);
                } else {
                    $parentExpression = $this->glossarRepository->findEntryByL18nParentAndUid($glossarExpression['l18n_parent'], $sourceLanguageId);
                }

                if (!empty($parentExpression['input'])) {
                    return true;
                }
            }
        }

        return false;
    }
}
