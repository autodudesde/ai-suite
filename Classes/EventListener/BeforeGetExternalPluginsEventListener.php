<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\EventListener;

use AutoDudes\AiSuite\Service\BackendUserService;
use AutoDudes\AiSuite\Service\SiteService;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\RteCKEditor\Form\Element\Event\BeforeGetExternalPluginsEvent;
use Throwable;

class BeforeGetExternalPluginsEventListener
{
    protected PageRenderer $pageRenderer;
    protected BackendUserService $backendUserService;
    protected SiteService $siteService;

    public function __construct(
        PageRenderer $pageRenderer,
        BackendUserService $backendUserService,
        SiteService $siteService
    ) {
        $this->pageRenderer = $pageRenderer;
        $this->backendUserService = $backendUserService;
        $this->siteService = $siteService;
    }

    /**
     * @throws SiteNotFoundException
     */
    public function __invoke(BeforeGetExternalPluginsEvent $event): void
    {
        try {
            $langIsoCode = $this->siteService->getIsoCodeByLanguageId((int)$event->getData()['databaseRow']['sys_language_uid'], $event->getData()['effectivePid']);
        } catch (Throwable $e) {
            return;
        }

        $this->pageRenderer->addInlineSetting('aiSuite', 'rteLanguageCode', $langIsoCode);
        $configuration = $event->getConfiguration();

        if ($this->backendUserService->checkPermissions('tx_aisuite_features:enable_rte_aiplugin')) {
            $configuration['aisuite_aiplugin'] = [];
            $configuration['aisuite_aiplugin']['resource'] = 'EXT:ai_suite/Resources/Public/JavaScript/CKEditor/Plugins/AiPlugin/plugin.js';
        }

        $event->setConfiguration($configuration);
    }
}
