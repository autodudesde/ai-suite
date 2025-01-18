<?php

declare(strict_types=1);

/***
 *
 * This file is part of the "ai_suite" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *
 ***/

namespace AutoDudes\AiSuite\Providers;

use AutoDudes\AiSuite\Service\BackendUserService;
use TYPO3\CMS\Backend\ContextMenu\ItemProviders\AbstractProvider;
use TYPO3\CMS\Backend\Routing\UriBuilder;


class PagesContextMenuProvider extends AbstractProvider
{
    protected BackendUserService $backendUserService;
    protected UriBuilder $uriBuilder;

    public function __construct(
        BackendUserService $backendUserService,
        UriBuilder $uriBuilder
    ) {
        parent::__construct();
        $this->backendUserService = $backendUserService;
        $this->uriBuilder = $uriBuilder;
    }

    /**
     * @var array
     */
    protected $itemsConfiguration = [
        'aisuite' => [
            'type' => 'submenu',
            'label' => 'AI Suite',
            'iconIdentifier' => 'tx-aisuite-extension',
            'callbackAction' => 'openSubmenu',
            'childItems' => [
                'pageMetaMassAction' => [
                    'type' => 'item',
                    'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite.module.dashboard.card.massActionPages.title',
                    'iconIdentifier' => 'actions-duplicate',
                    'callbackAction' => 'contextMenuLink',
                ],
                'fileReferencesMetaMassAction' => [
                    'type' => 'item',
                    'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite.module.dashboard.card.massActionFileReferences.title',
                    'iconIdentifier' => 'actions-duplicate',
                    'callbackAction' => 'contextMenuLink',
                ],
                'filelistMetaMassAction' => [
                    'type' => 'item',
                    'label' => 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:tx_aisuite.module.dashboard.card.massActionFilelist.title',
                    'iconIdentifier' => 'actions-duplicate',
                    'callbackAction' => 'contextMenuLink',
                ],
            ],
        ],
    ];

    /**
     * @return bool
     */
    public function canHandle(): bool
    {
        return $this->table === 'pages' || $this->table === 'sys_file';
    }

    /**
     * @return int
     */
    public function getPriority(): int
    {
        return 55;
    }

    /**
     * @param string $itemName
     * @return array
     */
    protected function getAdditionalAttributes(string $itemName): array
    {
        $moduleUrl = '';
        switch ($itemName) {
            case 'pageMetaMassAction':
                $moduleUrl = $this->uriBuilder->buildUriFromRoute('ai_suite_massaction_pages_prepare', ['id' => $this->identifier])->__toString();
                break;
            case 'fileReferencesMetaMassAction':
                $moduleUrl = $this->uriBuilder->buildUriFromRoute('ai_suite_massaction_filereferences_prepare', ['id' => $this->identifier])->__toString();
                break;
            case 'filelistMetaMassAction':
                $moduleUrl = $this->uriBuilder->buildUriFromRoute('ai_suite_massaction_filelist_files_prepare', ['id' => $this->identifier])->__toString();
                break;
        }
        return [
            'data-callback-module' => '@autodudes/ai-suite/context-menu/page-context-menu-actions',
            'data-module-url' => $moduleUrl,
        ];
    }

    /**
     * @param array $items
     * @return array
     */
    public function addItems(array $items): array
    {
        $this->initDisabledItems();

        $localItems = $this->prepareItems($this->itemsConfiguration);

        if (isset($items['info'])) {
            $position = array_search('info', array_keys($items), true);

            $beginning = array_slice($items, 0, $position+1, true);
            $end = array_slice($items, $position, null, true);

            $items = $beginning + $localItems + $end;
        } else if (isset($items['newFile'])) {
            $position = array_search('newFile', array_keys($items), true);

            $beginning = array_slice($items, 0, $position+1, true);
            $end = array_slice($items, $position, null, true);

            $items = $beginning + $localItems + $end;
        } else {
            $items = $items + $localItems;
        }
        return $items;
    }

    /**
     * @param string $itemName
     * @param string $type
     * @return bool
     */
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
            case 'pageMetaMassAction':
                $canRender = $this->canPageMassAction();
                break;
            case 'fileReferencesMetaMassAction':
                $canRender = $this->canFileReferencesMassAction();
                break;
            case 'filelistMetaMassAction':
                $canRender = $this->canFilelistMassAction();
                break;
        }
        return $canRender;
    }


    /**
     * @return bool
     */
    protected function canShowAiSuite(): bool
    {
        return $this->canPageMassAction() || $this->canFileReferencesMassAction() || $this->canFilelistMassAction();
    }

    protected function canPageMassAction(): bool
    {
        return $this->table === 'pages' && $this->backendUserService->checkPermissions('tx_aisuite_features:enable_massaction_generation');
    }
    protected function canFileReferencesMassAction(): bool
    {
        return $this->table === 'pages' && $this->backendUserService->checkPermissions('tx_aisuite_features:enable_massaction_generation');
    }
    protected function canFilelistMassAction(): bool
    {
        return $this->table === 'sys_file' && $this->backendUserService->checkPermissions('tx_aisuite_features:enable_massaction_generation');
    }
}
