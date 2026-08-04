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

namespace AutoDudes\AiSuite\Service;

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

class PromptTemplateScopeService implements SingletonInterface
{
    public const SCOPE_METADATA = 'metadata';

    protected const LABEL_PREFIX = 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_module.xlf:aiSuite.module.workflow.columns.';

    /** @var array<string, list<string>> */
    protected const METADATA_FIELDS = [
        'pages' => [
            'seo_title', 'description', 'og_title', 'og_description', 'twitter_title', 'twitter_description', 'abstract',
        ],
        'sys_file_metadata' => [
            'title', 'alternative', 'description',
        ],
        'tx_news_domain_model_news' => [
            'alternative_title', 'description',
        ],
    ];

    public function __construct(
        protected readonly LocalizationService $localizationService,
    ) {}

    public function buildMetadataType(string $table, string $fieldName): string
    {
        if ('' === $table || '' === $fieldName) {
            return '';
        }
        if ('sys_file_reference' === $table) {
            $table = 'sys_file_metadata';
        }

        return $table.'.'.$fieldName;
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    public function getMetadataTypeItems(): array
    {
        $items = [];
        foreach (self::METADATA_FIELDS as $table => $fields) {
            if ('tx_news_domain_model_news' === $table && !ExtensionManagementUtility::isLoaded('news')) {
                continue;
            }
            foreach ($fields as $fieldName) {
                $items[] = [
                    'label' => $this->localizationService->translate(self::LABEL_PREFIX.$table.'.'.$fieldName),
                    'value' => $table.'.'.$fieldName,
                ];
            }
        }

        return $items;
    }
}
