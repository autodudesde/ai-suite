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

namespace AutoDudes\AiSuite\Tca;

use AutoDudes\AiSuite\Service\PromptTemplateScopeService;

class PromptTemplateTypeItemsProcFunc
{
    public const EXCLUDE_TAB_LIST = [
        'news',
        'container',
        'data',
        'lists',
        'menu',
        'special',
        'plugins',
        'social',
        'forms',
    ];
    public const EXCLUDE_CTYPE_LIST = [
        'csv',
        'external_media',
        'menu_card_list',
        'menu_card_dir',
        'menu_thumbnail_list',
        'menu_thumbnail_dir',
        'social_links',
        'audio',
    ];

    public function __construct(
        protected readonly PromptTemplateScopeService $promptTemplateScopeService,
    ) {}

    /**
     * @param array<string, mixed> $config
     */
    public function getTypeItems(array &$config): void
    {
        $scope = $config['row']['scope'] ?? '';
        if (is_array($scope)) {
            $scope = $scope[0] ?? '';
        }

        $config['items'] = PromptTemplateScopeService::SCOPE_METADATA === $scope
            ? $this->promptTemplateScopeService->getMetadataTypeItems()
            : $this->getContentTypeItems();
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    protected function getContentTypeItems(): array
    {
        $cTypes = [];
        foreach ($GLOBALS['TCA']['tt_content']['columns']['CType']['config']['items'] ?? [] as $item) {
            if (array_key_exists('label', $item) && array_key_exists('value', $item) && array_key_exists('group', $item)) {
                if (!in_array($item['group'], self::EXCLUDE_TAB_LIST, true) && !in_array($item['value'], self::EXCLUDE_CTYPE_LIST, true) && '--div--' !== $item['value']) {
                    $cTypes[] = ['label' => $item['label'], 'value' => $item['value']];
                }
            } elseif (array_key_exists('0', $item) && array_key_exists('1', $item) && array_key_exists('3', $item)) {
                if (!in_array($item['3'], self::EXCLUDE_TAB_LIST, true) && !in_array($item['1'], self::EXCLUDE_CTYPE_LIST, true) && '--div--' !== $item['1']) {
                    $cTypes[] = ['label' => $item['0'], 'value' => $item['1']];
                }
            }
        }

        return $cTypes;
    }
}
