<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace AutoDudes\AiSuite\Controller\Page;


use TYPO3\CMS\Backend\Domain\Model\Element\ImmediateActionElement;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\Drawing\BackendLayoutRenderer;
use TYPO3\CMS\Backend\View\PageLayoutContext;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Script Class for Web > Layout module
 */
class PageLayoutController extends \TYPO3\CMS\Backend\Controller\PageLayoutController
{
    /**
     * Rendering content
     *
     * @return string
     */
    protected function renderContent(): string
    {
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/ContextMenu');
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Tooltip');
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/AiSuite/Translation/Localization');
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/LayoutModule/DragDrop');
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Modal');
        $this->pageRenderer->loadRequireJsModule(ImmediateActionElement::MODULE_NAME);
        $this->pageRenderer->addInlineLanguageLabelFile('EXT:backend/Resources/Private/Language/locallang_layout.xlf');

        $tableOutput = '';
        $numberOfHiddenElements = 0;

        if ($this->context instanceof PageLayoutContext) {
            // Context may not be set, which happens if the page module is viewed by a user with no access to the
            // current page, or if the ID parameter is malformed. In this case we do not resolve any backend layout
            // or other page structure information and we do not render any "table output" for the module.
            $configuration = $this->context->getDrawingConfiguration();
            $configuration->setDefaultLanguageBinding(!empty($this->modTSconfig['properties']['defLangBinding']));
            $configuration->setActiveColumns(GeneralUtility::trimExplode(',', $this->activeColPosList, true));
            $configuration->setShowHidden((bool)$this->MOD_SETTINGS['tt_content_showHidden']);
            $configuration->setLanguageColumns($this->MOD_MENU['language']);
            $configuration->setShowNewContentWizard(empty($this->modTSconfig['properties']['disableNewContentElementWizard']));
            $configuration->setSelectedLanguageId((int)$this->MOD_SETTINGS['language']);
            if ($this->MOD_SETTINGS['function'] == 2) {
                $configuration->setLanguageMode(true);
            }

            $numberOfHiddenElements = $this->getNumberOfHiddenElements($configuration->getLanguageMode());

            $pageActionsInstruction = JavaScriptModuleInstruction::forRequireJS('TYPO3/CMS/Backend/PageActions');
            if ($this->context->isPageEditable()) {
                $languageOverlayId = 0;
                $pageLocalizationRecord = BackendUtility::getRecordLocalization('pages', $this->id, (int)$this->current_sys_language);
                if (is_array($pageLocalizationRecord)) {
                    $pageLocalizationRecord = reset($pageLocalizationRecord);
                }
                if (!empty($pageLocalizationRecord['uid'])) {
                    $languageOverlayId = $pageLocalizationRecord['uid'];
                }
                $pageActionsInstruction
                    ->invoke('setPageId', (int)$this->id)
                    ->invoke('setLanguageOverlayId', $languageOverlayId);
            }
            $this->pageRenderer->getJavaScriptRenderer()->addJavaScriptModuleInstruction($pageActionsInstruction);
            $tableOutput = GeneralUtility::makeInstance(BackendLayoutRenderer::class, $this->context)->drawContent();
        }

        if ($this->getBackendUser()->check('tables_select', 'tt_content') && $numberOfHiddenElements > 0) {
            // Toggle hidden ContentElements
            $tableOutput .= '
                <div class="form-check">
                    <input type="checkbox" id="checkTt_content_showHidden" class="form-check-input" name="SET[tt_content_showHidden]" value="1" ' . ($this->MOD_SETTINGS['tt_content_showHidden'] ? 'checked="checked"' : '') . ' />
                    <label class="form-check-label" for="checkTt_content_showHidden">
                        ' . htmlspecialchars($this->getLanguageService()->getLL('hiddenCE')) . ' (<span class="t3js-hidden-counter">' . $numberOfHiddenElements . '</span>)
                    </label>
                </div>';
        }

        // Init the content
        $content = '';
        // Additional header content
        foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms/layout/db_layout.php']['drawHeaderHook'] ?? [] as $hook) {
            $params = [];
            $content .= GeneralUtility::callUserFunction($hook, $params, $this);
        }
        $content .= $tableOutput;

        // Additional footer content
        foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms/layout/db_layout.php']['drawFooterHook'] ?? [] as $hook) {
            $params = [];
            $content .= GeneralUtility::callUserFunction($hook, $params, $this);
        }
        return $content;
    }
}
