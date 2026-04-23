<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\EventListener;

use AutoDudes\AiSuite\Service\BackendUserService;
use AutoDudes\AiSuite\Service\LocalizationService;
use TYPO3\CMS\Backend\Controller\Event\ModifyNewContentElementWizardItemsEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

#[AsEventListener(
    identifier: 'tx-ai-suite/modify-new-content-element-wizard-items-event-listener',
    event: ModifyNewContentElementWizardItemsEvent::class,
)]
class ModifyNewContentElementWizardItemsEventListener
{
    public function __construct(
        private readonly BackendUserService $backendUserService,
        private readonly LocalizationService $localizationService,
    ) {}

    public function __invoke(ModifyNewContentElementWizardItemsEvent $event): void
    {
        if (!$this->backendUserService->checkPermissions('tx_aisuite_features:enable_content_element_generation')) {
            return;
        }
        $addedAiSuiteWizardItems = [];
        $currentTabKey = '';
        foreach ($event->getWizardItems() as $key => $wizardItem) {
            if (array_key_exists('header', $wizardItem)) {
                $currentTabKey = $key;

                continue;
            }
            if (!array_key_exists('CType', $wizardItem['defaultValues']) || empty($wizardItem['defaultValues']['CType'])) {
                continue;
            }
            $cType = $wizardItem['defaultValues']['CType'];
            if (in_array($currentTabKey, AfterTcaCompilationEventListener::EXCLUDE_TAB_LIST)
                || in_array($cType, AfterTcaCompilationEventListener::EXCLUDE_CTYPE_LIST)) {
                continue;
            }
            if (in_array($cType, $addedAiSuiteWizardItems)) {
                continue;
            }
            if (null === ($wizardItem['title'] ?? null)) {
                continue;
            }
            if (0 === count($addedAiSuiteWizardItems)) {
                $event->setWizardItem(
                    'aisuite',
                    [
                        'header' => $this->localizationService->translate('aiSuite.mlangTabsTab').' Content',
                    ]
                );
            }
            $event->setWizardItem(
                'aisuite_'.$key,
                [
                    'iconIdentifier' => $wizardItem['iconIdentifier'] ?? '',
                    'title' => ($wizardItem['title'] ?? '').' ('.$this->localizationService->translate('aiSuite.mlangTabsTab').')',
                    'description' => ($wizardItem['description'] ?? '').' (with AI generated content)',
                    'defaultValues' => $wizardItem['defaultValues'] ?? [],
                ]
            );
            $addedAiSuiteWizardItems[] = $cType;
        }
        $wizardItems = $event->getWizardItems();

        $commonEntries = [];
        $aiSuiteEntries = [];
        $otherEntries = [];

        foreach ($wizardItems as $key => $value) {
            if (str_starts_with($key, 'default')) {
                $commonEntries[$key] = $value;
            } elseif (str_starts_with($key, 'aisuite')) {
                $aiSuiteEntries[$key] = $value;
            } else {
                $otherEntries[$key] = $value;
            }
        }

        $sortedWizardItems = $commonEntries + $otherEntries + $aiSuiteEntries;
        $event->setWizardItems($sortedWizardItems);
    }
}
