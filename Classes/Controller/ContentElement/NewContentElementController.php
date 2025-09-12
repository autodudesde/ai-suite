<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Controller\ContentElement;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Controller\Event\ModifyNewContentElementWizardItemsEvent;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

class NewContentElementController extends \TYPO3\CMS\Backend\Controller\ContentElement\NewContentElementController
{
    protected function wizardAction(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->id || $this->pageInfo === []) {
            // No pageId or no access.
            return new HtmlResponse('No Access');
        }
        // Whether position selection must be performed (no colPos was yet defined)
        $positionSelection = $this->colPos === null;

        // Get processed and modified wizard items
        $wizardItems = $this->eventDispatcher->dispatch(
            new ModifyNewContentElementWizardItemsEvent(
                $this->getWizards(),
                $this->pageInfo,
                $this->colPos,
                $this->sys_language,
                $this->uid_pid,
                $request,
            )
        )->getWizardItems();

        $key = 'common';
        $categories = [];
        foreach ($wizardItems as $wizardKey => $wizardItem) {
            // An item is either a header or an item rendered with title/description and icon:
            if (isset($wizardItem['header'])) {
                $key = $wizardKey;
                $categories[$key] = [
                    'identifier' => $key,
                    'label' => $wizardItem['header'] ?: '-',
                    'items' => [],
                ];
            } else {
                // Initialize the view variables for the item
                $item = [
                    'identifier' => $wizardKey,
                    'icon' => $wizardItem['iconIdentifier'] ?? '',
                    'label' => $wizardItem['title'] ?? '',
                    'description' => $wizardItem['description'] ?? '',
                ];

                // Get default values for the wizard item
                $defVals = (array)($wizardItem['tt_content_defValues'] ?? []);
                if (!$positionSelection) {
                    // In case no position has to be selected, we can just add the target
                    if (($wizardItem['saveAndClose'] ?? false)) {
                        // Go to DataHandler directly instead of FormEngine
                        $item['url'] = (string)$this->uriBuilder->buildUriFromRoute('tce_db', [
                            'data' => [
                                'tt_content' => [
                                    StringUtility::getUniqueId('NEW') => array_replace($defVals, [
                                        'colPos' => $this->colPos,
                                        'pid' => $this->uid_pid,
                                        'sys_language_uid' => $this->sys_language,
                                    ]),
                                ],
                            ],
                            'redirect' => $this->returnUrl,
                        ]);
                    } else {
                        if ($key === 'aisuite') {
                            $item['url'] = (string)$this->uriBuilder->buildUriFromRoute('ai_suite_record_edit', [
                                'edit' => [
                                    'tt_content' => [
                                        $this->uid_pid => 'new',
                                    ],
                                ],
                                'returnUrl' => $this->returnUrl,
                                'defVals' => [
                                    'tt_content' => array_replace($defVals, [
                                        'colPos' => $this->colPos,
                                        'pid' => $this->id,
                                        'sys_language_uid' => $this->sys_language,
                                    ]),
                                ],
                            ]);
                        } else {
                            $item['url'] = (string)$this->uriBuilder->buildUriFromRoute('record_edit', [
                                'edit' => [
                                    'tt_content' => [
                                        $this->uid_pid => 'new',
                                    ],
                                ],
                                'returnUrl' => $this->returnUrl,
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
                    $item['url'] = (string)$this->uriBuilder
                        ->buildUriFromRoute(
                            'new_content_element_wizard',
                            [
                                'action' => 'positionMap',
                                'id' => $this->id,
                                'sys_language_uid' => $this->sys_language,
                                'returnUrl' => $this->returnUrl,
                            ]
                        );
                    $item['requestType'] = 'ajax';
                    $item['defaultValues'] = $defVals;
                    $item['saveAndClose'] = (bool)($wizardItem['saveAndClose'] ?? false);
                }
                $categories[$key]['items'][] = $item;
            }
        }

        $view = $this->backendViewFactory->create($request);
        $view->assignMultiple([
            'positionSelection' => $positionSelection,
            'categoriesJson' => GeneralUtility::jsonEncodeForHtmlAttribute($categories, false),
        ]);
        return new HtmlResponse($view->render('NewContentElement/Wizard'));
    }
}
