<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\EventListener;

use AutoDudes\AiSuite\Service\BackendUserService;
use AutoDudes\AiSuite\Service\SiteService;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\RteCKEditor\Form\Element\Event\BeforePrepareConfigurationForEditorEvent;
use Throwable;

#[AsEventListener(
    identifier: 'ai-suite/file-controls-event-listener',
    event: BeforePrepareConfigurationForEditorEvent::class,
)]
class BeforePrepareConfigurationForEditorEventListener
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
    public function __invoke(BeforePrepareConfigurationForEditorEvent $event): void
    {
        if ($this->backendUserService->checkPermissions('tx_aisuite_features:enable_rte_aiplugin')) {
            try {
                $langIsoCode = $this->siteService->getIsoCodeByLanguageId((int)$event->getData()['databaseRow']['sys_language_uid'], $event->getData()['effectivePid']);
            } catch (Throwable $e) {
                return;
            }
            $this->pageRenderer->addInlineSetting('aiSuite', 'rteLanguageCode', $langIsoCode);

            $configuration = $event->getConfiguration();
            $configuration['importModules'][] = '@autodudes/ai-suite/ckeditor/ai-plugin.js';
            $configuration['toolbar']['items'][] = 'aiPlugin';
            $event->setConfiguration($configuration);
        }
    }
}
