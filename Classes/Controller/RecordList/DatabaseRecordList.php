<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Controller\RecordList;

use AutoDudes\AiSuite\Utility\TranslationUtility;

class DatabaseRecordList extends \TYPO3\CMS\Recordlist\RecordList\DatabaseRecordList
{
    /**
     * Creates the localization panel
     *
     * @param string $table The table
     * @param mixed[] $row The record for which to make the localization panel.
     */
    public function makeLocalizationPanel($table, $row, array $translations): string
    {
        $out = parent::makeLocalizationPanel($table, $row, $translations);
        if ($out) {
            $pageId = (int)($table === 'pages' ? $row['uid'] : $row['pid']);
            $possibleTranslations = $this->possibleTranslations;
            if ($table === 'pages') {
                $possibleTranslations = array_map(static fn ($siteLanguage) => $siteLanguage->getLanguageId(), $this->languagesAllowedForUser);
                $possibleTranslations = array_filter($possibleTranslations, static fn ($languageUid) => $languageUid > 0);
            }
            $languageInformation = $this->translateTools->getSystemLanguages($pageId);
            foreach ($possibleTranslations as $lUid_OnPage) {
                if ($this->isEditable($table)
                    && !$this->isRecordDeletePlaceholder($row)
                    && !isset($translations[$lUid_OnPage])
                    && $this->getBackendUserAuthentication()->checkLanguageAccess($lUid_OnPage)
                    && TranslationUtility::isTranslatable($pageId, $lUid_OnPage)
                ) {
                    $out .= TranslationUtility::buildTranslateButton(
                        $table,
                        $row['uid'],
                        $lUid_OnPage,
                        $this->listURL(),
                        $pageId,
                        $languageInformation[$lUid_OnPage]['flagIcon'],
                    );
                }
            }
        }
        return $out;
    }
}
