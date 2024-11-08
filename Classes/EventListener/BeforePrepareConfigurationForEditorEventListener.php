<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\EventListener;

use AutoDudes\AiSuite\Utility\BackendUserUtility;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\RteCKEditor\Form\Element\Event\BeforePrepareConfigurationForEditorEvent;
use Throwable;

class BeforePrepareConfigurationForEditorEventListener
{
    /**
     * @throws SiteNotFoundException
     */
    public function __invoke(BeforePrepareConfigurationForEditorEvent $event): void
    {
        if (BackendUserUtility::checkPermissions('tx_aisuite_features:enable_rte_aiplugin')) {
            $sysLanguageUid = $event->getData()['databaseRow']['sys_language_uid'];
            $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($event->getData()['effectivePid']);
            try {
                $siteLanguage = $site->getLanguageById((int)$sysLanguageUid);
            } catch (Throwable $e) {
                return;
            }
            $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
            $pageRenderer->addInlineSetting('aiSuite', 'rteLanguageCode', $siteLanguage->getLocale()->getLanguageCode());

            $configuration = $event->getConfiguration();
            $configuration['importModules'][] = '@autodudes/ai-suite/ckeditor/ai-plugin.js';
            $configuration['toolbar']['items'][] = 'aiPlugin';
            $event->setConfiguration($configuration);
        }
    }
}
