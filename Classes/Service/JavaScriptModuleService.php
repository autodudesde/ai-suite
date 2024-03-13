<?php

namespace AutoDudes\AiSuite\Service;

use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

class JavaScriptModuleService
{
    public function addModules(): array
    {
        $resultArray['javaScriptModules'] = [
            JavaScriptModuleInstruction::create('@autodudes/ai-suite/metadata/generate-suggestions.js'),
        ];
        if (ExtensionManagementUtility::isLoaded('news')) {
            $resultArray['javaScriptModules'][] = JavaScriptModuleInstruction::create('@autodudes/ai-suite/metadata/news-generate-suggestions.js');
        }
        return $resultArray;
    }
}
