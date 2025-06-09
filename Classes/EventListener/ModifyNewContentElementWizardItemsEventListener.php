<?php

namespace AutoDudes\AiSuite\EventListener;

use AutoDudes\AiSuite\Service\BackendUserService;
use AutoDudes\AiSuite\Service\TranslationService;
use TYPO3\CMS\Backend\Controller\Event\ModifyNewContentElementWizardItemsEvent;

class ModifyNewContentElementWizardItemsEventListener
{
    private BackendUserService $backendUserService;
    private TranslationService $translationService;

    public function __construct(
        BackendUserService $backendUserService,
        TranslationService $translationService
    ) {
        $this->backendUserService = $backendUserService;
        $this->translationService = $translationService;
    }

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
            if (!array_key_exists('CType', $wizardItem['tt_content_defValues']) || empty($wizardItem['tt_content_defValues']['CType'])) {
                continue;
            }
            $cType = $wizardItem['tt_content_defValues']['CType'];
            if (in_array($currentTabKey, AfterTcaCompilationEventListener::EXCLUDE_TAB_LIST) ||
                in_array($cType, AfterTcaCompilationEventListener::EXCLUDE_CTYPE_LIST)) {
                continue;
            }
            if (in_array($cType, $addedAiSuiteWizardItems)) {
                continue;
            }
            if (null === ($wizardItem['title'] ?? null)) {
                continue;
            }
            if (count($addedAiSuiteWizardItems) === 0) {
                $event->setWizardItem(
                    'aisuite',
                    [
                        'header' => $this->translationService->translate('mlang_tabs_tab') . ' Content',
                    ]
                );
            }
            $event->setWizardItem(
                'aisuite_'.$cType,
                [
                    'iconIdentifier' => $wizardItem['iconIdentifier'] ?? '',
                    'title' => ($wizardItem['title']  ?? '') . ' (' . $this->translationService->translate('mlang_tabs_tab') . ')',
                    'description' => ($wizardItem['description'] ?? '') . ' (with AI generated content)',
                    'tt_content_defValues' => $wizardItem['tt_content_defValues'] ?? [],
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
