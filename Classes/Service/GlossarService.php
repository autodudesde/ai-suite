<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

use AutoDudes\AiSuite\Domain\Repository\GlossarRepository;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\SingletonInterface;

class GlossarService implements SingletonInterface
{
    /**
     * @var null|array<int, list<int>>
     */
    private ?array $glossaryPidsBySiteRoot = null;

    public function __construct(
        protected readonly GlossarRepository $glossarRepository,
        protected readonly SendRequestService $sendRequestService,
        protected readonly SiteService $siteService,
        protected readonly LoggerInterface $logger,
        protected readonly LocalizationService $localizationService,
    ) {}

    public function getSiteRootPageId(int $pageId): int
    {
        if ($pageId <= 0) {
            return 0;
        }

        return $this->siteService->getSiteRootPageId($pageId);
    }

    /**
     * @return list<int>
     */
    public function getSiteGlossaryPids(int $rootPageId): array
    {
        if ($rootPageId <= 0) {
            return [];
        }

        return $this->getGlossaryPidsBySiteRoot()[$rootPageId] ?? [];
    }

    /**
     * @return array<string, string>
     */
    public function findGlossarEntries(string $jsonContent, int $destLangUid, int $srcLangUid, int $rootPageId): array
    {
        $glossary = [];
        foreach ($this->findTermPairs($srcLangUid, $destLangUid, $rootPageId) as [$sourceTerm, $targetTerm]) {
            if (str_contains($jsonContent, $sourceTerm)) {
                $glossary[$sourceTerm] = $targetTerm;
            }
        }

        return $glossary;
    }

    /**
     * @return array{glossary: string, deepl_glossary_id: string}
     */
    public function buildRequestParameters(string $content, int $srcLangUid, int $destLangUid, int $rootPageId): array
    {
        if ($rootPageId <= 0) {
            return ['glossary' => '[]', 'deepl_glossary_id' => ''];
        }
        $deeplGlossary = $this->findDeeplGlossary($rootPageId, $srcLangUid, $destLangUid);

        return [
            'glossary' => (string) json_encode($this->findGlossarEntries($content, $destLangUid, $srcLangUid, $rootPageId), SendRequestService::JSON_SAFE_FLAGS),
            'deepl_glossary_id' => is_array($deeplGlossary) ? (string) ($deeplGlossary['glossar_uuid'] ?? '') : '',
        ];
    }

    /**
     * @return null|array{rootPageId: int, sourceLanguageId: int, targetLanguageId: int}
     */
    public function resolveGlossarySelection(string $selection): ?array
    {
        $parts = explode('__', $selection);
        if (3 !== count($parts) || !ctype_digit($parts[0]) || !ctype_digit($parts[1]) || !ctype_digit($parts[2])) {
            return null;
        }
        $rootPageId = (int) $parts[0];
        if ($rootPageId <= 0 || $this->getSiteRootPageId($rootPageId) !== $rootPageId) {
            return null;
        }

        return [
            'rootPageId' => $rootPageId,
            'sourceLanguageId' => (int) $parts[1],
            'targetLanguageId' => (int) $parts[2],
        ];
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
            $rootPageId = $this->getSiteRootPageId($pid);
            $glossaryPids = $this->getSiteGlossaryPids($rootPageId);
            if ([] === $glossaryPids) {
                $this->logger->warning('No glossary entries found in the site of page '.$pid.', nothing to synchronize.');

                return false;
            }
            $glossarEntries = $this->glossarRepository->findAllEntriesForPages($glossaryPids);

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

            if ([] === $inputCombinations) {
                $this->logger->warning('No translated glossary entries found below root page '.$rootPageId.', nothing to synchronize.');

                return false;
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
            $createdGlossaries = $answer->getResponseData()['createdGlossaries'] ?? [];
            if (count($createdGlossaries) < count($inputCombinations)) {
                $this->logger->error('DeepL glossary synchronization returned '.count($createdGlossaries).' of '.count($inputCombinations).' glossaries.');

                return false;
            }
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
     * @return array<string, string>
     */
    public function getAvailableGlossariesForFileTranslation(
        int $sourceLanguageId,
        int $targetLanguageId,
        string $sourceLanguageIso,
        string $targetLanguageIso,
        string $textAiModel,
        ?int $rootPageId = null
    ): array {
        $availableGlossaries = [];

        if (empty($sourceLanguageIso) || empty($targetLanguageIso) || 0 === $rootPageId) {
            return $availableGlossaries;
        }

        if ('Deepl' === $textAiModel) {
            $glossaries = $this->glossarRepository->findDeeplGlossaryUuidsBySourceAndTargetLanguage($sourceLanguageIso, $targetLanguageIso);

            foreach ($glossaries as $glossary) {
                $glossaryRootPageId = (int) $glossary['root_page_uid'];
                $isExternal = (bool) ($glossary['external'] ?? false);
                if (null !== $rootPageId && $glossaryRootPageId !== $rootPageId) {
                    continue;
                }
                if (!$isExternal && !$this->hasGlossaryEntriesForLanguageCombination($sourceLanguageId, $targetLanguageId, $glossaryRootPageId)) {
                    continue;
                }
                $label = $this->buildGlossaryLabel($sourceLanguageIso, $targetLanguageIso, $glossaryRootPageId);
                if ($isExternal) {
                    $label .= $this->localizationService->translate('aiSuite.generation.workflow.selectGlossaryExternal');
                }
                $availableGlossaries[$glossaryRootPageId.'__'.$sourceLanguageId.'__'.$targetLanguageId] = $label;
            }

            return $availableGlossaries;
        }

        $siteRootPageIds = null === $rootPageId ? array_keys($this->getGlossaryPidsBySiteRoot()) : [$rootPageId];
        foreach ($siteRootPageIds as $siteRootPageId) {
            if (!$this->siteLanguagesMatch($siteRootPageId, $sourceLanguageId, $targetLanguageId, $sourceLanguageIso, $targetLanguageIso)
                || !$this->hasGlossaryEntriesForLanguageCombination($sourceLanguageId, $targetLanguageId, $siteRootPageId)
            ) {
                continue;
            }
            $availableGlossaries[$siteRootPageId.'__'.$sourceLanguageId.'__'.$targetLanguageId] = $this->buildGlossaryLabel($sourceLanguageIso, $targetLanguageIso, $siteRootPageId);
        }

        return $availableGlossaries;
    }

    public function hasGlossaryEntriesForLanguageCombination(int $sourceLanguageId, int $targetLanguageId, int $rootPageId): bool
    {
        return [] !== $this->findTermPairs($sourceLanguageId, $targetLanguageId, $rootPageId);
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function findTermPairs(int $sourceLanguageId, int $targetLanguageId, int $rootPageId): array
    {
        $pids = $this->getSiteGlossaryPids($rootPageId);
        if ([] === $pids) {
            return [];
        }
        $translations = array_values(array_filter(
            $this->glossarRepository->findBySysLanguageUidAndPages($targetLanguageId, $pids),
            static fn (array $translation): bool => $translation['l18n_parent'] > 0
        ));
        if ([] === $translations) {
            return [];
        }
        $sourceInputs = $this->glossarRepository->findSourceInputsByParents(
            array_values(array_unique(array_column($translations, 'l18n_parent'))),
            $sourceLanguageId,
            $pids
        );

        $pairs = [];
        foreach ($translations as $translation) {
            $sourceTerm = $sourceInputs[$translation['l18n_parent']] ?? '';
            if ('' !== $sourceTerm) {
                $pairs[] = [$sourceTerm, $translation['input']];
            }
        }

        return $pairs;
    }

    /**
     * @return array<int, list<int>>
     */
    private function getGlossaryPidsBySiteRoot(): array
    {
        if (null === $this->glossaryPidsBySiteRoot) {
            $this->glossaryPidsBySiteRoot = [];
            foreach ($this->glossarRepository->findDistinctPids() as $pid) {
                $rootPageId = $this->siteService->getSiteRootPageId($pid);
                if ($rootPageId > 0) {
                    $this->glossaryPidsBySiteRoot[$rootPageId][] = $pid;
                }
            }
        }

        return $this->glossaryPidsBySiteRoot;
    }

    private function siteLanguagesMatch(int $rootPageId, int $sourceLanguageId, int $targetLanguageId, string $sourceLanguageIso, string $targetLanguageIso): bool
    {
        try {
            return strtolower($this->siteService->getIsoCodeByLanguageIdIncludingDisabled($sourceLanguageId, $rootPageId)) === strtolower($sourceLanguageIso)
                && strtolower($this->siteService->getIsoCodeByLanguageIdIncludingDisabled($targetLanguageId, $rootPageId)) === strtolower($targetLanguageIso);
        } catch (\Throwable) {
            return false;
        }
    }

    private function buildGlossaryLabel(string $sourceLanguageIso, string $targetLanguageIso, int $rootPageId): string
    {
        return $this->localizationService->translate('aiSuite.generation.workflow.selectGlossaryLabel', [
            strtoupper($sourceLanguageIso),
            strtoupper($targetLanguageIso),
            $this->siteService->getDomainByRootPageId($rootPageId),
        ]);
    }
}
