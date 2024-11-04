<?php

namespace AutoDudes\AiSuite\EventListener;

use AutoDudes\AiSuite\Utility\BackendUserUtility;
use TYPO3\CMS\Backend\Controller\Event\ModifyNewContentElementWizardItemsEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

#[AsEventListener(
    identifier: 'ai-suite/modify-new-content-element-wizard-items-event-listener',
    event: ModifyNewContentElementWizardItemsEvent::class,
)]
final class ModifyNewContentElementWizardItemsEventListener
{
    public function __invoke(ModifyNewContentElementWizardItemsEvent $event): void
    {
        if (!BackendUserUtility::checkPermissions('tx_aisuite_features:enable_content_element_generation')) {
            return;
        }
        $addedAiSuiteWizardItems = [];
        $currentTabKey = '';
        foreach ($event->getWizardItems() as $key => $wizardItem) {
            if (array_key_exists('header', $wizardItem)) {
                $currentTabKey = $key;
                continue;
            }
            $cType = $wizardItem['defaultValues']['CType'];
            if (in_array($currentTabKey, AfterTcaCompilationEventListener::EXCLUDE_TAB_LIST) ||
                in_array($cType, AfterTcaCompilationEventListener::EXCLUDE_CTYPE_LIST)) {
                continue;
            }
            if (in_array($cType, $addedAiSuiteWizardItems)) {
                continue;
            }
            if (count($addedAiSuiteWizardItems) === 0) {
                $event->setWizardItem(
                    'aisuite',
                    [
                        'header' => 'AI Suite Content',
                    ]
                );
            }
            $event->setWizardItem(
                'aisuite_'.$key,
                [
                    'iconIdentifier' => $wizardItem['iconIdentifier'] ?? '',
                    'title' => $wizardItem['title'] . ' (AI Suite)',
                    'description' => $wizardItem['description'] . ' (with AI generated content)',
                    'defaultValues' => $wizardItem['defaultValues'],
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

        $sortedWizardItems = $commonEntries + $aiSuiteEntries + $otherEntries;
        $event->setWizardItems($sortedWizardItems);
    }
}
