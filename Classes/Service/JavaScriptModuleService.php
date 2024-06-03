<?php

namespace AutoDudes\AiSuite\Service;

use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

class JavaScriptModuleService
{
    public function addModules(): array
    {

        $resultArray['requireJsModules'] = [
            JavaScriptModuleInstruction::forRequireJS('TYPO3/CMS/AiSuite/Metadata/GenerateSuggestions')
        ];
        if (ExtensionManagementUtility::isLoaded('news')) {
            $resultArray['requireJsModules'][] = JavaScriptModuleInstruction::forRequireJS('TYPO3/CMS/AiSuite/Metadata/NewsGenerateSuggestions');
        }

        return $resultArray;
    }
}
