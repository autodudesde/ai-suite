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

use AutoDudes\AiSuite\Domain\Repository\ContentRepository;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendLayoutView;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ContentTargetService implements SingletonInterface
{
    private const EXCLUDE_CTYPE_GROUPS = ['news', 'container', 'data', 'lists', 'menu', 'special', 'plugins', 'social', 'forms'];
    private const EXCLUDE_CTYPES = ['csv', 'external_media', 'menu_card_list', 'menu_card_dir', 'menu_thumbnail_list', 'menu_thumbnail_dir', 'social_links', 'audio', 'div', 'shortcut', 'list', 'html'];
    private const FAQ_NAME_SIGNALS = ['faq', 'accordion', 'toggle', 'question'];
    private const AUTHORBOX_NAME_SIGNALS = ['author', 'profile', 'team', 'card', 'person'];

    public function __construct(
        protected readonly ContentRepository $contentRepository,
        protected readonly TcaCompatibilityService $tcaCompatibilityService,
        protected readonly LocalizationService $localizationService,
    ) {}

    /**
     * @return array<int, string>
     */
    public function getColumns(int $pageId): array
    {
        $columns = [];

        try {
            $layout = GeneralUtility::makeInstance(BackendLayoutView::class)->getBackendLayoutForPage($pageId);
            foreach ($layout?->getUsedColumns() ?? [] as $colPos => $label) {
                $columns[(int) $colPos] = $this->localizationService->translate((string) $label) ?: (string) $label;
            }
        } catch (\Throwable) {
            // kein Layout auflösbar -> Fallback unten
        }

        return [] === $columns ? [0 => 'Standard'] : $columns;
    }

    /**
     * @return array<int, list<array{uid: int, label: string}>>
     */
    public function getElementsByColumn(int $pageId, int $languageUid = 0): array
    {
        $grouped = [];
        foreach ($this->contentRepository->findByPage($pageId, $languageUid) as $row) {
            $header = trim((string) ($row['header'] ?? ''));
            $grouped[(int) $row['colPos']][] = [
                'uid' => (int) $row['uid'],
                'label' => ('' !== $header ? $header : '['.$row['CType'].']').' ['.$row['uid'].']',
            ];
        }

        return $grouped;
    }

    /**
     * @return list<array{value: string, label: string, recommended: bool, faqCapable: bool, sectionCapable: bool}>
     */
    public function getContentTypeOptions(int $pageId, string $action = 'faq'): array
    {
        $removed = $this->removedCTypes($pageId);
        $options = [];
        foreach ($GLOBALS['TCA']['tt_content']['columns']['CType']['config']['items'] ?? [] as $item) {
            $value = (string) ($item['value'] ?? $item[1] ?? '');
            $label = (string) ($item['label'] ?? $item[0] ?? '');
            $group = (string) ($item['group'] ?? $item[3] ?? '');
            if ('' === $value || '--div--' === $value
                || \in_array($value, $removed, true)
                || \in_array($value, self::EXCLUDE_CTYPES, true)
                || \in_array($group, self::EXCLUDE_CTYPE_GROUPS, true)
            ) {
                continue;
            }
            $translatedLabel = $this->localizationService->translate($label) ?: $label;
            $capabilities = $this->capabilities($value, $translatedLabel);
            $options[] = [
                'value' => $value,
                'label' => $translatedLabel,
                'faqCapable' => $capabilities['faq'],
                'sectionCapable' => $capabilities['section'],
                'authorboxCapable' => $capabilities['authorbox'],
                'recommended' => match ($action) {
                    'faq' => $capabilities['faq'] || $capabilities['section'],
                    'authorbox' => $capabilities['authorbox'] || $capabilities['section'],
                    default => $capabilities['section'],
                },
            ];
        }

        usort($options, static function (array $a, array $b) use ($action): int {
            $rank = static fn (array $option): int => match (true) {
                'faq' === $action && $option['faqCapable'] => 0,
                'authorbox' === $action && $option['authorboxCapable'] => 0,
                $option['sectionCapable'] => 1,
                default => 2,
            };

            return [$rank($a), $a['label']] <=> [$rank($b), $b['label']];
        });

        return $options;
    }

    /**
     * @return list<string>
     */
    private function removedCTypes(int $pageId): array
    {
        $tsConfig = BackendUtility::getPagesTSconfig($pageId);
        $removeItems = (string) ($tsConfig['TCEFORM.']['tt_content.']['CType.']['removeItems'] ?? '');

        return GeneralUtility::trimExplode(',', $removeItems, true);
    }

    /**
     * @return array{faq: bool, section: bool, authorbox: bool}
     */
    private function capabilities(string $cType, string $label): array
    {
        $section = false;
        $faq = false;
        $hasImage = false;

        try {
            $fields = $this->tcaCompatibilityService->getFieldNamesForType('tt_content', $cType);
            $columnConfigs = $this->tcaCompatibilityService->getColumnConfigs('tt_content');
            $hasHeader = \in_array('header', $fields, true);
            foreach ($fields as $field) {
                $config = $columnConfigs[$field] ?? [];
                $type = (string) ($config['type'] ?? '');
                if ($hasHeader && 'text' === $type && $this->tcaCompatibilityService->isRichTextField('tt_content', $field, $cType)) {
                    $section = true;
                }
                if ('file' === $type) {
                    $hasImage = true;
                }
                // IRRE-Kindtabelle mit Frage/Antwort-Muster (>= 2 Textfelder)
                if ('inline' === $type) {
                    $childTable = (string) ($config['foreign_table'] ?? '');
                    if ('' !== $childTable && 'sys_file_reference' !== $childTable && $this->childHasQuestionAnswerShape($childTable)) {
                        $faq = true;
                    }
                }
            }
        } catch (\Throwable) {
            // unbekannter CType/Schema -> keine Fähigkeiten
        }

        // Autorenbox: Name (Header) + Bio (RTE) + Foto (Bildfeld)
        $authorbox = $section && $hasImage;
        $needle = mb_strtolower($cType.' '.$label);
        foreach (self::FAQ_NAME_SIGNALS as $signal) {
            if (str_contains($needle, $signal)) {
                $faq = true;

                break;
            }
        }
        foreach (self::AUTHORBOX_NAME_SIGNALS as $signal) {
            if (str_contains($needle, $signal)) {
                $authorbox = true;

                break;
            }
        }

        return ['faq' => $faq, 'section' => $section, 'authorbox' => $authorbox];
    }

    private function childHasQuestionAnswerShape(string $childTable): bool
    {
        try {
            $textFields = 0;
            foreach ($this->tcaCompatibilityService->getColumnConfigs($childTable) as $config) {
                if (\in_array((string) ($config['type'] ?? ''), ['input', 'text'], true)) {
                    ++$textFields;
                }
            }

            return $textFields >= 2;
        } catch (\Throwable) {
            return false;
        }
    }
}
