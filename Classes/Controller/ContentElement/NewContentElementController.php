<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Controller\ContentElement;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Wizard\NewContentElementWizardHookInterface;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

class NewContentElementController extends \TYPO3\CMS\Backend\Controller\ContentElement\NewContentElementController
{
    /**
     * Creating the module output.
     *
     * @throws \UnexpectedValueException
     */
    protected function prepareWizardContent(ServerRequestInterface $request): void
    {
        $hasAccess = $this->id && $this->pageInfo !== [];
        $this->view->assign('hasAccess', $hasAccess);
        if (!$hasAccess) {
            return;
        }
        // Whether position selection must be performed (no colPos was yet defined)
        $positionSelection = !isset($this->colPos);
        $this->view->assign('positionSelection', $positionSelection);

        // Get processed wizard items from configuration
        $wizardItems = $this->getWizards();

        // Call hooks for manipulating the wizard items
        foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms']['db_new_content_el']['wizardItemsHook'] ?? [] as $className) {
            $hookObject = GeneralUtility::makeInstance($className);
            if (!$hookObject instanceof NewContentElementWizardHookInterface) {
                throw new \UnexpectedValueException(
                    $className . ' must implement interface ' . NewContentElementWizardHookInterface::class,
                    1227834741
                );
            }
            $hookObject->manipulateWizardItems($wizardItems, $this);
        }

        $key = 0;
        $menuItems = [];
        // Traverse items for the wizard.
        // An item is either a header or an item rendered with title/description and icon:
        foreach ($wizardItems as $wizardKey => $wInfo) {
            if (isset($wInfo['header'])) {
                $menuItems[] = [
                    'label' => $wInfo['header'] ?: '-',
                    'content' => '',
                ];
                $key = count($menuItems) - 1;
            } else {
                // Initialize the view variables for the item
                $viewVariables = [
                    'wizardInformation' => $wInfo,
                    'wizardKey' => $wizardKey,
                    'icon' => $this->iconFactory->getIcon(($wInfo['iconIdentifier'] ?? ''), Icon::SIZE_DEFAULT, ($wInfo['iconOverlay'] ?? ''))->render(),
                ];

                // Check wizardItem for defVals
                $itemParams = [];
                parse_str($wInfo['params'] ?? '', $itemParams);
                $defVals = $itemParams['defVals']['tt_content'] ?? [];

                // In case no position has to be selected, we can just add the target
                if (!$positionSelection) {
                    // Go to DataHandler directly instead of FormEngine
                    if (($wInfo['saveAndClose'] ?? false)) {
                        $viewVariables['target'] = (string)$this->uriBuilder->buildUriFromRoute('tce_db', [
                            'data' => [
                                'tt_content' => [
                                    StringUtility::getUniqueId('NEW') => array_replace($defVals, [
                                        'colPos' => $this->colPos,
                                        'pid' => $this->uid_pid,
                                        'sys_language_uid' => $this->sys_language,
                                    ]),
                                ],
                            ],
                            'redirect' => $this->R_URI,
                        ]);
                    } else {
                        if (strpos($wizardKey, "aisuite_") === 0) {
                            $viewVariables['target'] = (string)$this->uriBuilder->buildUriFromRoute('ai_suite_record_edit', [
                                'edit' => [
                                    'tt_content' => [
                                        $this->uid_pid => 'new',
                                    ],
                                ],
                                'returnUrl' => $this->R_URI,
                                'defVals' => [
                                    'tt_content' => array_replace($defVals, [
                                        'colPos' => $this->colPos,
                                        'pid' => $this->id,
                                        'CType' => str_replace("aisuite_", "", $wizardKey),
                                        'sys_language_uid' => $this->sys_language,
                                    ]),
                                ],
                            ]);
                        } else {
                            $viewVariables['target'] = (string)$this->uriBuilder->buildUriFromRoute('record_edit', [
                                'edit' => [
                                    'tt_content' => [
                                        $this->uid_pid => 'new',
                                    ],
                                ],
                                'returnUrl' => $this->R_URI,
                                'defVals' => [
                                    'tt_content' => array_replace($defVals, [
                                        'colPos' => $this->colPos,
                                        'sys_language_uid' => $this->sys_language,
                                    ]),
                                ],
                            ]);
                        }
                    }
                } else {
                    $viewVariables['positionMapArguments'] = GeneralUtility::jsonEncodeForHtmlAttribute([
                        'url' => (string)$this->uriBuilder->buildUriFromRoute('new_content_element_wizard', [
                            'action' => 'positionMap',
                            'id' => $this->id,
                            'sys_language_uid' => $this->sys_language,
                            'returnUrl' => $this->R_URI,
                        ]),
                        'defVals' => $defVals,
                        'saveAndClose' => (bool)($wInfo['saveAndClose'] ?? false),
                    ], true);
                }

                $menuItems[$key]['content'] .= $this->getFluidTemplateObject('MenuItem')->assignMultiple($viewVariables)->render();
            }
        }

        $this->view->assign('renderedTabs', $this->moduleTemplateFactory->create($request)->getDynamicTabMenu(
            $menuItems,
            'new-content-element-wizard'
        ));
    }
}
