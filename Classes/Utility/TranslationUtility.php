<?php

declare(strict_types=1);

/***
 *
 * This file is part of the "ai_suite" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *
 ***/

namespace AutoDudes\AiSuite\Utility;

use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class TranslationUtility
{
    public static function isTranslatable(int $pageId, int $languageId): bool
    {
        try {
            $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($pageId);
            $sourceLanguageIsoCode = SiteUtility::getIsoCodeByLanguageId($site->getDefaultLanguage()->getLanguageId());
            $targetLanguageIsoCode = SiteUtility::getIsoCodeByLanguageId($languageId);
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws RouteNotFoundException
     */
    public static function buildTranslateButton(
        $table,
        $id,
        $lUid_OnPage,
        $returnUrl,
        $pageId,
        $flagIcon = ''
    ): string {
        $params = [];
        $uuid = UuidUtility::generateUuid();
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($pageId);
        $openTranslatedRecordInEditMode = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('ai_suite', 'openTranslatedRecordInEditMode');
        if($openTranslatedRecordInEditMode) {
            $redirectUrl = (string)$uriBuilder->buildUriFromRoute(
                'record_edit',
                [
                    'justLocalized' => $table . ':' . $id . ':' . $lUid_OnPage,
                    'returnUrl' => $returnUrl,
                ]
            );
            $params['redirect'] = $redirectUrl;
        } else {
            $params['redirect'] = $returnUrl;
        }
        $params['cmd'][$table][$id]['localize'] = $lUid_OnPage;
        $params['cmd']['localization']['aiSuite']['srcLanguageId'] = $site->getDefaultLanguage()->getLanguageId();
        $params['cmd']['localization']['aiSuite']['destLanguageId'] = $lUid_OnPage;
        $params['cmd']['localization']['aiSuite']['translateAi'] = 'AI_SUITE_MODEL';
        $params['cmd']['localization']['aiSuite']['uuid'] = $uuid;
        $href = (string)$uriBuilder->buildUriFromRoute('tce_db', $params);
        $title = LocalizationUtility::translate('aiSuite.translateRecord', 'ai_suite');

        if ($flagIcon) {
            $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
            $icon = $iconFactory->getIcon($flagIcon, Icon::SIZE_SMALL, 'tx-aisuite-localization');
            $lC = $icon->render();
        } else {
            $lC = GeneralUtility::makeInstance(IconFactory::class)
                ->getIcon('tx-aisuite-localization', Icon::SIZE_SMALL)
                ->render();
        }

        return '<a href="#"'
            . '" class="btn btn-default t3js-action-localize ai-suite-record-localization"'
            . 'data-href="' . htmlspecialchars($href) . '"'
            . 'data-page-id="' . $pageId . '"'
            . 'data-uuid="' . $uuid . '"'
            . ' title="' . $title . '">'
            . $lC . '</a> ';
    }
}
