<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\FormEngine\FieldControl\SysFileReference;

use AutoDudes\AiSuite\Service\SiteService;
use TYPO3\CMS\Backend\Form\AbstractNode;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AiSysFileReferenceAlternative extends AbstractNode
{
    public function render(): array
    {
        if(!$GLOBALS['BE_USER']->check('custom_options', 'tx_aisuite_features:enable_metadata_generation')) {
            return [];
        }
        try {
            $siteService = GeneralUtility::makeInstance(SiteService::class);
            $pageUid = (int)$this->data['parentPageRow']['uid'];
            if(isset($this->data['parentPageRow']['l10n_parent'][0]) && (int)$this->data['parentPageRow']['l10n_parent'][0] > 0) {
                $pageUid = (int)$this->data['parentPageRow']['l10n_parent'][0];
            }
            $langIsoCode = $siteService->getIsoCodeByLanguageId((int)$this->data['databaseRow']['sys_language_uid'], $pageUid);
        } catch (\Throwable $e) {
            return [];
        }
        return [
            'iconIdentifier' => 'actions-document-synchronize',
            'title' => $GLOBALS['LANG']->sL('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:AiSuite.generation.alternativeSuggestions'),
            'linkAttributes' => [
                'id' => 'alternative_generation',
                'data-sys-file-id' => (int)$this->data['databaseRow']['uid_local'][0]['uid'],
                'class' => 'ai-suite-suggestions-generation-btn',
                'data-id' => $this->data['databaseRow']['uid'],
                'data-lang-iso-code' => $langIsoCode,
                'data-table' => $this->data['tableName'],
                'data-field-name' => 'alternative',
                'data-field-label' => 'Alternative',
            ],
            'javaScriptModules' => [
                JavaScriptModuleInstruction::create('@autodudes/ai-suite/metadata/generate-suggestions.js')
            ],
        ];
    }
}
