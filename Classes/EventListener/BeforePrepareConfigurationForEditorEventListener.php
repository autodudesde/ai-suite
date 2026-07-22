<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\EventListener;

use AutoDudes\AiSuite\Service\BackendUserService;
use AutoDudes\AiSuite\Service\SiteService;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\RteCKEditor\Form\Element\Event\BeforePrepareConfigurationForEditorEvent;

#[AsEventListener(
    identifier: 'tx-ai-suite/before-prepare-configuration-for-editor-event-listener',
    event: BeforePrepareConfigurationForEditorEvent::class,
)]
class BeforePrepareConfigurationForEditorEventListener
{
    public function __construct(
        protected readonly BackendUserService $backendUserService,
        protected readonly SiteService $siteService,
        protected readonly LoggerInterface $logger,
    ) {}

    /**
     * @throws SiteNotFoundException
     */
    public function __invoke(BeforePrepareConfigurationForEditorEvent $event): void
    {
        try {
            $langIsoCode = $this->siteService->getIsoCodeByLanguageId((int) $event->getData()['databaseRow']['sys_language_uid'], $event->getData()['effectivePid']);
        } catch (\Throwable $e) {
            $this->logger->notice('Skipping AI Suite RTE configuration: could not resolve language ISO code', [
                'effectivePid' => $event->getData()['effectivePid'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return;
        }
        $configuration = $event->getConfiguration();
        $configuration['aiSuite'] = [
            'rteLanguageCode' => $langIsoCode,
            'pageId' => (int) ($event->getData()['effectivePid'] ?? 0),
        ];
        if ($this->backendUserService->checkPermissions('tx_aisuite_features:enable_rte_aiplugin')) {
            $configuration['importModules'][] = [
                'module' => '@autodudes/ai-suite/ckeditor/AiPlugin/ai-plugin.js',
                'exports' => [
                    'AiPlugin',
                ],
            ];
            $configuration['toolbar']['items'][] = 'AiPlugin';
        }
        if ($this->backendUserService->checkPermissions('tx_aisuite_features:enable_rte_aieasylanguageplugin')) {
            $configuration['importModules'][] = [
                'module' => '@autodudes/ai-suite/ckeditor/AiEasyLanguagePlugin/ai-easy-language-plugin.js',
                'exports' => [
                    'AiEasyLanguagePlugin',
                ],
            ];
            $configuration['toolbar']['items'][] = 'AiEasyLanguagePlugin';
        }

        $event->setConfiguration($configuration);
    }
}
