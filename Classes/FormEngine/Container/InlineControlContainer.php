<?php

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

namespace AutoDudes\AiSuite\FormEngine\Container;

use TYPO3\CMS\Backend\Form\Container\AbstractContainer;
use TYPO3\CMS\Backend\Form\InlineStackProcessor;
use TYPO3\CMS\Backend\Form\NodeFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\OnlineMedia\Helpers\OnlineMediaHelperRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

/**
 * Inline element entry container.
 *
 * This container is the entry step to rendering an inline element. It is created by SingleFieldContainer.
 *
 * The code creates the main structure for the single inline elements, initializes
 * the inlineData array, that is manipulated and also returned back in its manipulated state.
 * The "control" stuff of inline elements is rendered here, for example the "create new" button.
 *
 * For each existing inline relation an InlineRecordContainer is called for further processing.
 */
class InlineControlContainer extends \TYPO3\CMS\Backend\Form\Container\InlineControlContainer
{
    /**
     * Container objects give $nodeFactory down to other containers.
     *
     * @param NodeFactory $nodeFactory
     * @param array $data
     */
    public function __construct(NodeFactory $nodeFactory, array $data)
    {
        parent::__construct($nodeFactory, $data);
    }

    /**
     * Generate a button that opens an element browser in a new window.
     * For group/db there is no way to use a "selector" like a <select>|</select>-box.
     *
     * @param array $inlineConfiguration TCA inline configuration of the parent(!) field
     * @return string A HTML button that opens an element browser in a new window
     */
    protected function renderPossibleRecordsSelectorTypeGroupDB(array $inlineConfiguration): string
    {
        $typo3Version = new Typo3Version();
        if ($typo3Version->getMajorVersion() === 12) {
            return parent::renderPossibleRecordsSelectorTypeGroupDB($inlineConfiguration);
        } else {
            $backendUser = $this->getBackendUserAuthentication();
            $languageService = $this->getLanguageService();

            $groupFieldConfiguration = $inlineConfiguration['selectorOrUniqueConfiguration']['config'];

            $foreign_table = $inlineConfiguration['foreign_table'];
            $allowed = $groupFieldConfiguration['allowed'];
            $currentStructureDomObjectIdPrefix = $this->inlineStackProcessor->getCurrentStructureDomObjectIdPrefix($this->data['inlineFirstPid']);
            $objectPrefix = $currentStructureDomObjectIdPrefix . '-' . $foreign_table;
            $mode = 'db';
            $showUpload = false;
            $showByUrl = false;
            $elementBrowserEnabled = true;
            if (!empty($inlineConfiguration['appearance']['createNewRelationLinkTitle'])) {
                $createNewRelationText = htmlspecialchars($languageService->sL($inlineConfiguration['appearance']['createNewRelationLinkTitle']));
            } else {
                $createNewRelationText = htmlspecialchars($languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:cm.createNewRelation'));
            }
            if (is_array($groupFieldConfiguration['appearance'] ?? null)) {
                if (isset($groupFieldConfiguration['appearance']['elementBrowserType'])) {
                    $mode = $groupFieldConfiguration['appearance']['elementBrowserType'];
                }
                if ($mode === 'file') {
                    $showUpload = true;
                    $showByUrl = true;
                }
                if (isset($inlineConfiguration['appearance']['fileUploadAllowed'])) {
                    $showUpload = (bool)$inlineConfiguration['appearance']['fileUploadAllowed'];
                }
                if (isset($inlineConfiguration['appearance']['fileByUrlAllowed'])) {
                    $showByUrl = (bool)$inlineConfiguration['appearance']['fileByUrlAllowed'];
                }
                if (isset($groupFieldConfiguration['appearance']['elementBrowserAllowed'])) {
                    $allowed = $groupFieldConfiguration['appearance']['elementBrowserAllowed'];
                }
                if (isset($inlineConfiguration['appearance']['elementBrowserEnabled'])) {
                    $elementBrowserEnabled = (bool)$inlineConfiguration['appearance']['elementBrowserEnabled'];
                }
            }
            // Remove any white-spaces from the allowed extension lists
            $allowed = implode(',', GeneralUtility::trimExplode(',', $allowed, true));
            $browserParams = '|||' . $allowed . '|' . $objectPrefix;
            $buttonStyle = '';
            if (isset($inlineConfiguration['inline']['inlineNewRelationButtonStyle'])) {
                $buttonStyle = ' style="' . $inlineConfiguration['inline']['inlineNewRelationButtonStyle'] . '"';
            }
            $item = '';
            if ($elementBrowserEnabled) {
                $item .= '
			<button type="button" class="btn btn-default t3js-element-browser" data-mode="' . htmlspecialchars($mode) . '" data-params="' . htmlspecialchars($browserParams) . '"
				' . $buttonStyle . ' title="' . $createNewRelationText . '">
				' . $this->iconFactory->getIcon('actions-insert-record', Icon::SIZE_SMALL)->render() . '
				' . $createNewRelationText . '
			</button>';
            }

            $isDirectFileUploadEnabled = (bool)$backendUser->uc['edit_docModuleUpload'];
            $allowedArray = GeneralUtility::trimExplode(',', $allowed, true);
            $onlineMediaAllowed = GeneralUtility::makeInstance(OnlineMediaHelperRegistry::class)->getSupportedFileExtensions();
            if (!empty($allowedArray)) {
                $onlineMediaAllowed = array_intersect($allowedArray, $onlineMediaAllowed);
            }
            if (($showUpload || $showByUrl) && $isDirectFileUploadEnabled) {
                $folder = $backendUser->getDefaultUploadFolder(
                    $this->data['tableName'] === 'pages' ? $this->data['vanillaUid'] : ($this->data['parentPageRow']['uid'] ?? 0),
                    $this->data['tableName'],
                    $this->data['fieldName']
                );
                if (
                    $folder instanceof Folder
                    && $folder->getStorage()->checkUserActionPermission('add', 'File')
                ) {
                    if ($showUpload) {
                        $maxFileSize = GeneralUtility::getMaxUploadFileSize() * 1024;
                        $item .= ' <button type="button" class="btn btn-default t3js-drag-uploader inlineNewFileUploadButton"
					' . $buttonStyle . '
					data-dropzone-target="#' . htmlspecialchars(StringUtility::escapeCssSelector($currentStructureDomObjectIdPrefix)) . '"
					data-insert-dropzone-before="1"
					data-file-irre-object="' . htmlspecialchars($objectPrefix) . '"
					data-file-allowed="' . htmlspecialchars($allowed) . '"
					data-target-folder="' . htmlspecialchars($folder->getCombinedIdentifier()) . '"
					data-max-file-size="' . htmlspecialchars((string)$maxFileSize) . '"
					>';
                        $item .= $this->iconFactory->getIcon('actions-upload', Icon::SIZE_SMALL)->render() . ' ';
                        $item .= htmlspecialchars($languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:file_upload.select-and-submit'));
                        $item .= '</button>';

                        $this->requireJsModules[] = JavaScriptModuleInstruction::forRequireJS('TYPO3/CMS/Backend/DragUploader');
                    }
                    if (!empty($onlineMediaAllowed) && $showByUrl) {
                        $buttonStyle = '';
                        if (isset($inlineConfiguration['inline']['inlineOnlineMediaAddButtonStyle'])) {
                            $buttonStyle = ' style="' . $inlineConfiguration['inline']['inlineOnlineMediaAddButtonStyle'] . '"';
                        }
                        $this->requireJsModules[] = JavaScriptModuleInstruction::forRequireJS('TYPO3/CMS/Backend/OnlineMedia');
                        $buttonText = htmlspecialchars($languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:online_media.new_media.button'));
                        $placeholder = htmlspecialchars($languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:online_media.new_media.placeholder'));
                        $buttonSubmit = htmlspecialchars($languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:online_media.new_media.submit'));
                        $allowedMediaUrl = htmlspecialchars($languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:cm.allowEmbedSources'));
                        $item .= '
						<button type="button" class="btn btn-default t3js-online-media-add-btn"
							' . $buttonStyle . '
							data-file-irre-object="' . htmlspecialchars($objectPrefix) . '"
							data-online-media-allowed="' . htmlspecialchars(implode(',', $onlineMediaAllowed)) . '"
							data-online-media-allowed-help-text="' . $allowedMediaUrl . '"
							data-target-folder="' . htmlspecialchars($folder->getCombinedIdentifier()) . '"
							title="' . $buttonText . '"
							data-btn-submit="' . $buttonSubmit . '"
							data-placeholder="' . $placeholder . '"
							>
							' . $this->iconFactory->getIcon('actions-online-media-add', Icon::SIZE_SMALL)->render() . '
							' . $buttonText . '</button>';
                    }
                }
            }
            // TODO: add dynamic check for allowed file types based on the AI model
            if($mode === 'file' && $foreign_table === 'sys_file_reference' &&
                (in_array('jpeg', $allowedArray) || in_array('jpg', $allowedArray))
                && $elementBrowserEnabled
            ) {
                $this->requireJsModules[] = JavaScriptModuleInstruction::forRequireJS('TYPO3/CMS/AiSuite/Ajax/Image/GenerateImage');
                $buttonText = htmlspecialchars($languageService->sL('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.generateImageWithAiButton'));
                $placeholder = htmlspecialchars($languageService->sL('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.generateImageWithAiButton'));
                $buttonSubmit = htmlspecialchars($languageService->sL('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.generateImageWithAiButton'));
                $item .= '
						<button type="button" class="btn btn-default t3js-ai-suite-image-generation-add-btn"
							' . $buttonStyle . '
							data-file-irre-object="' . htmlspecialchars($objectPrefix) . '"
							data-file-context-config="' . htmlspecialchars($this->inlineData['config'][$objectPrefix]['context']['config']) . '"
							data-file-context-hmac="' . htmlspecialchars($this->inlineData['config'][$objectPrefix]['context']['hmac']) . '"
							data-ai-suite-image-generation-media-allowed="' . htmlspecialchars($allowed) . '"
							data-target-folder="' . htmlspecialchars($folder->getCombinedIdentifier()) . '"
							data-table="' . htmlspecialchars($this->data['tableName']) . '"
							data-record-uid="' . htmlspecialchars($this->data['databaseRow']['uid']) . '"
							data-fieldname="' . $this->data['fieldName'] . '"
							data-page-id="' . htmlspecialchars($this->data['databaseRow']['pid']) . '"
							data-position="0"
							title="' . $buttonText . '"
							data-btn-submit="' . $buttonSubmit . '"
							data-placeholder="' . $placeholder . '"
							>
							' . $this->iconFactory->getIcon('apps-clipboard-images', Icon::SIZE_SMALL)->render() . '
							' . $buttonText . '</button>';
            }

            $item = '<div class="form-control-wrap t3js-inline-controls">' . $item . '</div>';
            $allowedList = '';
            $allowedLabelKey = ($mode === 'file') ? 'allowedFileExtensions' : 'allowedRelations';
            $allowedLabel = htmlspecialchars($languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:cm.' . $allowedLabelKey));
            foreach ($allowedArray as $allowedItem) {
                $allowedList .= '<span class="label label-success">' . strtoupper($allowedItem) . '</span> ';
            }
            if (!empty($allowedList)) {
                $item .= '<div class="help-block">' . $allowedLabel . '<br>' . $allowedList . '</div>';
            }
            $item = '<div class="form-group t3js-formengine-validation-marker t3js-inline-controls-top-outer-container">' . $item . '</div>';
            return $item;
        }
    }

    /**
     * @return BackendUserAuthentication
     */
    protected function getBackendUserAuthentication(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * @return LanguageService
     */
    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
