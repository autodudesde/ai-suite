<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\EventListener;

use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\RteCKEditor\Form\Element\Event\BeforePrepareConfigurationForEditorEvent;

class BeforePrepareConfigurationForEditorEventListener
{
    /**
     * @throws SiteNotFoundException
     */
    public function __invoke(BeforePrepareConfigurationForEditorEvent $event): void
    {
        $sysLanguageUid = $event->getData()['databaseRow']['sys_language_uid'];
        $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($event->getData()['effectivePid']);
        $siteLanguage = $site->getLanguageById((int)$sysLanguageUid);
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->addInlineSetting('aiSuite', 'rteLanguageCode', $siteLanguage->getLocale()->getLanguageCode());

        $configuration = $event->getConfiguration();
        $configuration['importModules'][] = '@autodudes/ai-suite/ckeditor/ai-plugin.js';
        $configuration['toolbar']['items'][] = 'aiPlugin';
        $event->setConfiguration($configuration);
    }
}
