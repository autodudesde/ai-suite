<?php

declare(strict_types=1);

/*
 *
 * This file is part of the "ai_suite" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *
 */

namespace AutoDudes\AiSuite\Providers;

use AutoDudes\AiSuite\Service\BackendUserService;
use TYPO3\CMS\Backend\ContextMenu\ItemProviders\AbstractProvider;
use TYPO3\CMS\Backend\Routing\UriBuilder;

class PagesContextMenuProvider extends AbstractProvider
{
    /**
     * @var array
     */
    /** @var array<string, array<string, mixed>> */
    protected $itemsConfiguration = [
        'aisuite' => [
            'type' => 'submenu',
            'label' => 'AI Suite',
            'iconIdentifier' => 'tx-aisuite-extension',
            'callbackAction' => 'openSubmenu',
            'childItems' => [
                'pageMetaWorkflow' => [
                    'type' => 'item',
                    'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_module.xlf:aiSuite.module.dashboard.card.workflowPages.title',
                    'iconIdentifier' => 'actions-duplicate',
                    'callbackAction' => 'contextMenuLink',
                ],
                'fileReferencesMetaWorkflow' => [
                    'type' => 'item',
                    'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_module.xlf:aiSuite.module.dashboard.card.workflowFileReferences.title',
                    'iconIdentifier' => 'actions-duplicate',
                    'callbackAction' => 'contextMenuLink',
                ],
                'filelistMetaWorkflow' => [
                    'type' => 'item',
                    'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_module.xlf:aiSuite.module.dashboard.card.workflowFilelist.title',
                    'iconIdentifier' => 'actions-duplicate',
                    'callbackAction' => 'contextMenuLink',
                ],
                'divider1' => [
                    'type' => 'divider',
                ],
                'translateWholePage' => [
                    'type' => 'item',
                    'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.translateWholePage',
                    'iconIdentifier' => 'actions-localize',
                    'callbackAction' => 'contextMenuLink',
                ],
                'translateFileMetadata' => [
                    'type' => 'item',
                    'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.translateFileMetadata',
                    'iconIdentifier' => 'actions-localize',
                    'callbackAction' => 'contextMenuLink',
                ],
            ],
        ],
    ];

    public function __construct(
        protected readonly BackendUserService $backendUserService,
        protected readonly UriBuilder $uriBuilder,
    ) {
        parent::__construct();
    }

    public function canHandle(): bool
    {
        return 'pages' === $this->table || 'sys_file' === $this->table;
    }

    public function getPriority(): int
    {
        return 55;
    }

    /**
     * @param array<string, mixed> $items
     *
     * @return array<string, mixed>
     */
    public function addItems(array $items): array
    {
        $this->initDisabledItems();

        $localItems = $this->prepareItems($this->itemsConfiguration);

        if (isset($items['info'])) {
            $position = (int) array_search('info', array_keys($items), true);

            $beginning = array_slice($items, 0, $position + 1, true);
            $end = array_slice($items, $position, null, true);

            $items = $beginning + $localItems + $end;
        } elseif (isset($items['newFile'])) {
            $position = (int) array_search('newFile', array_keys($items), true);

            $beginning = array_slice($items, 0, $position + 1, true);
            $end = array_slice($items, $position, null, true);

            $items = $beginning + $localItems + $end;
        } else {
            $items = $items + $localItems;
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getAdditionalAttributes(string $itemName): array
    {
        $moduleUrl = '';

        switch ($itemName) {
            case 'pageMetaWorkflow':
                $moduleUrl = $this->uriBuilder->buildUriFromRoute('ai_suite_workflow_pages_prepare', ['id' => $this->identifier])->__toString();

                break;

            case 'fileReferencesMetaWorkflow':
                $moduleUrl = $this->uriBuilder->buildUriFromRoute('ai_suite_workflow_filereferences_prepare', ['id' => $this->identifier])->__toString();

                break;

            case 'filelistMetaWorkflow':
                $moduleUrl = $this->uriBuilder->buildUriFromRoute('ai_suite_workflow_filelist_files_prepare', ['id' => $this->identifier])->__toString();

                break;

            case 'translateWholePage':
                $moduleUrl = $this->uriBuilder->buildUriFromRoute('web_layout', ['id' => $this->identifier])->__toString();

                break;

            case 'translateFileMetadata':
                $moduleUrl = $this->uriBuilder->buildUriFromRoute('ai_suite_workflow_filelist_files_translate_prepare', ['id' => $this->identifier])->__toString();

                break;
        }
        $attributes = [
            'data-callback-module' => '@autodudes/ai-suite/context-menu/page-context-menu-actions',
            'data-module-url' => $moduleUrl,
        ];

        if ('translateWholePage' === $itemName) {
            $attributes['data-action'] = 'translateWholePage';
        }

        return $attributes;
    }

    protected function canRender(string $itemName, string $type): bool
    {
        if (in_array($itemName, $this->disabledItems, true)) {
            return false;
        }
        $canRender = false;

        switch ($itemName) {
            case 'aisuite':
                $canRender = $this->canShowAiSuite();

                break;

            case 'pageMetaWorkflow':
                $canRender = $this->canPageWorkflow();

                break;

            case 'fileReferencesMetaWorkflow':
                $canRender = $this->canFileReferencesWorkflow();

                break;

            case 'filelistMetaWorkflow':
                $canRender = $this->canFilelistWorkflow();

                break;

            case 'translateWholePage':
                $canRender = $this->canTranslateWholePage();

                break;

            case 'translateFileMetadata':
                $canRender = $this->canTranslateFileMetadata();

                break;
        }

        return $canRender;
    }

    protected function canShowAiSuite(): bool
    {
        return $this->canPageWorkflow() || $this->canFileReferencesWorkflow() || $this->canFilelistWorkflow() || $this->canTranslateWholePage() || $this->canTranslateFileMetadata();
    }

    protected function canPageWorkflow(): bool
    {
        return 'pages' === $this->table && $this->backendUserService->checkPermissions('tx_aisuite_features:enable_massaction_generation');
    }

    protected function canFileReferencesWorkflow(): bool
    {
        return 'pages' === $this->table && $this->backendUserService->checkPermissions('tx_aisuite_features:enable_massaction_generation');
    }

    protected function canFilelistWorkflow(): bool
    {
        return 'sys_file' === $this->table && $this->backendUserService->checkPermissions('tx_aisuite_features:enable_massaction_generation');
    }

    protected function canTranslateWholePage(): bool
    {
        return 'pages' === $this->table && $this->backendUserService->checkPermissions('tx_aisuite_features:enable_translation_whole_page');
    }

    protected function canTranslateFileMetadata(): bool
    {
        return 'sys_file' === $this->table && $this->backendUserService->checkPermissions('tx_aisuite_features:enable_translation_sys_file_metadata');
    }
}
