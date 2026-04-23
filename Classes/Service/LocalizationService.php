<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\SingletonInterface;

class LocalizationService implements SingletonInterface
{
    private const DEFAULT_XLIFF = 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:';
    private const MODULE_XLIFF = 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_module.xlf:';
    private const TCA_XLIFF = 'LLL:EXT:ai_suite/Resources/Private/Language/locallang_tca.xlf:';

    private const PREFIX_MAP = [
        'module:' => self::MODULE_XLIFF,
        'tca:' => self::TCA_XLIFF,
    ];

    /**
     * Translate a label key.
     *
     * - "someKey"         → locallang.xlf:someKey
     * - "module:someKey"  → locallang_module.xlf:someKey
     * - "tca:someKey"     → locallang_tca.xlf:someKey
     * - "LLL:EXT:..."     → used as-is
     *
     * @param list<mixed> $arguments
     */
    public function translate(string $xlfKey, array $arguments = []): string
    {
        if (str_starts_with($xlfKey, 'LLL:')) {
            $fullKey = $xlfKey;
        } else {
            $fullKey = self::DEFAULT_XLIFF.$xlfKey;
            foreach (self::PREFIX_MAP as $prefix => $xliffBase) {
                if (str_starts_with($xlfKey, $prefix)) {
                    $fullKey = $xliffBase.substr($xlfKey, strlen($prefix));

                    break;
                }
            }
        }

        $translated = $this->getLanguageService()->sL($fullKey);

        if ([] !== $arguments) {
            return sprintf($translated, ...$arguments);
        }

        return $translated;
    }

    public function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
