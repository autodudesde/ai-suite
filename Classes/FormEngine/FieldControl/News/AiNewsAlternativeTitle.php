<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\FormEngine\FieldControl\News;

use AutoDudes\AiSuite\Service\BackendUserService;
use AutoDudes\AiSuite\Service\LocalizationService;
use AutoDudes\AiSuite\Service\SiteService;
use TYPO3\CMS\Backend\Form\AbstractNode;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AiNewsAlternativeTitle extends AbstractNode
{
    /**
     * @return array<string, mixed>
     */
    public function render(): array
    {
        if (!GeneralUtility::makeInstance(BackendUserService::class)->checkPermissions('tx_aisuite_features:enable_metadata_generation')) {
            return [];
        }

        try {
            $siteService = GeneralUtility::makeInstance(SiteService::class);
            $pageUid = (int) $this->data['databaseRow']['pid'];
            if (isset($this->data['databaseRow']['l10n_parent'][0]) && (int) $this->data['databaseRow']['l10n_parent'][0] > 0) {
                $pageUid = (int) $this->data['databaseRow']['l10n_parent'][0];
            }
            $langIsoCode = $siteService->getIsoCodeByLanguageId((int) $this->data['databaseRow']['sys_language_uid'], $pageUid);
        } catch (SiteNotFoundException $e) {
            GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__)->error($e->getMessage());

            return [];
        }

        return [
            'iconIdentifier' => 'actions-document-synchronize',
            'title' => GeneralUtility::makeInstance(LocalizationService::class)->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.generation.newsAlternativeTitleSuggestions'),
            'linkAttributes' => [
                'id' => 'alternative_title_generation',
                'class' => 'ai-suite-suggestions-generation-btn',
                'data-id' => $this->data['databaseRow']['uid'],
                'data-page-id' => $this->data['databaseRow']['pid'],
                'data-lang-iso-code' => $langIsoCode,
                'data-language-id' => $this->data['databaseRow']['sys_language_uid'],
                'data-table' => $this->data['tableName'],
                'data-field-name' => 'alternative_title',
                'data-field-label' => 'NewsAlternativeTitle',
            ],
            'javaScriptModules' => [
                JavaScriptModuleInstruction::create('@autodudes/ai-suite/metadata/generate-suggestions.js'),
            ],
        ];
    }
}
