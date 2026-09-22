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

        $config['items'] = $this->promptTemplateScopeService->getTypeItems((string) $scope);
    }
}
