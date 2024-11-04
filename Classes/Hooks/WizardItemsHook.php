<?php

namespace AutoDudes\AiSuite\Hooks;

use AutoDudes\AiSuite\Utility\BackendUserUtility;
use TYPO3\CMS\Backend\Wizard\NewContentElementWizardHookInterface;

class WizardItemsHook implements NewContentElementWizardHookInterface
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

    public function manipulateWizardItems(&$wizardItems, &$parentObject)
    {
        if(!BackendUserUtility::checkPermissions('tx_aisuite_features:enable_content_element_generation')) {
            return;
        }
        $addedAiSuiteWizardItems = [];
        foreach($wizardItems as $key => $wizardItem) {
            if(strpos($key, '_') === false) {
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
                $wizardItems['aisuite'] = [
                    'header' => 'AI Suite Content',
                ];
            }
            $wizardItems['aisuite_'.$wizardItemParts[1]] = [
                'iconIdentifier' => $wizardItem['iconIdentifier'],
                'title' => $wizardItem['title'] . ' (AI Suite)',
                'description' => $wizardItem['title'] . ' (with AI generated content)',
                'tt_content_defValues' => $wizardItem['tt_content_defValues'],
            ];
            if(array_key_exists('params', $wizardItem)) {
                $wizardItems['aisuite_'.$wizardItemParts[1]]['params'] = $wizardItem['params'];
            }
            $addedAiSuiteWizardItems[] = $wizardItemParts[1];
        }

        $commonEntries = [];
        $aiSuiteEntries = [];
        $otherEntries = [];

        foreach ($wizardItems as $key => $value) {
            if (strpos($key, 'common') === 0) {
                $commonEntries[$key] = $value;
            } elseif (strpos($key, 'aisuite') === 0) {
                $aiSuiteEntries[$key] = $value;
            } else {
                $otherEntries[$key] = $value;
            }
        }

        $sortedWizardItems = $commonEntries + $aiSuiteEntries + $otherEntries;
        $wizardItems = $sortedWizardItems;
    }
}
