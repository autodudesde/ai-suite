<?php

namespace AutoDudes\AiSuite\EventListener;

use TYPO3\CMS\Backend\Controller\Event\ModifyNewContentElementWizardItemsEvent;

final class ModifyNewContentElementWizardItemsEventListener
{
    private const exclusionTabList = [
        'ext-news',
        'container',
        'data',
        'menu',
        'special',
        'plugins',
        'social',
        'form',
    ];
    public function __invoke(ModifyNewContentElementWizardItemsEvent $event): void
    {
        $addedAiSuiteWizardItems = [];
        foreach($event->getWizardItems() as $key => $wizardItem) {
            if(!str_contains($key, '_')) {
                continue;
            }
            $wizardItemParts = explode('_', $key);
            if(in_array($wizardItemParts[0], self::exclusionTabList)) {
                continue;
            }
            $itemName = '';
            foreach ($wizardItemParts as $partKey => $value) {
                if($partKey > 0) {
                    $itemName .= $value . '_';
                }
            }
            $itemName = rtrim($itemName, '_');
            if(in_array($itemName, $addedAiSuiteWizardItems)) {
                continue;
            }
            if(count($addedAiSuiteWizardItems) === 0) {
                $event->setWizardItem(
                    'aisuite',
                    [
                        'header' => 'AI Suite Content',
                    ]
                );
            }
            $event->setWizardItem(
                'aisuite_'.$itemName,
                [
                    'iconIdentifier' => $wizardItem['iconIdentifier'],
                    'title' => $wizardItem['title'] . ' (AI Suite)',
                    'description' => $wizardItem['description'] . ' (with AI generated content)',
                    'tt_content_defValues' => $wizardItem['tt_content_defValues'],
                ]
            );
            $addedAiSuiteWizardItems[] = $itemName;
        }
        $wizardItems = $event->getWizardItems();

        $commonEntries = [];
        $aiSuiteEntries = [];
        $otherEntries = [];

        foreach ($wizardItems as $key => $value) {
            if (str_starts_with($key, 'common')) {
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
