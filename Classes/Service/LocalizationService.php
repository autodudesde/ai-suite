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
    private const MCP_XLIFF = 'LLL:EXT:ai_suite_mcp/Resources/Private/Language/locallang_mcp.xlf:aiSuite.mcp.';

    private const PREFIX_MAP = [
        'module:' => self::MODULE_XLIFF,
        'tca:' => self::TCA_XLIFF,
        'mcp:' => self::MCP_XLIFF,
    ];

    /**
     * Translate a label key.
     *
     * - "someKey"             → locallang.xlf:someKey
     * - "module:someKey"      → locallang_module.xlf:someKey
     * - "tca:someKey"         → locallang_tca.xlf:someKey
     * - "mcp:someKey"         → ai_suite_mcp/locallang_mcp.xlf:aiSuite.mcp.someKey
     * - "LLL:EXT:..."         → used as-is
     * - "core.db.foo:bar.baz" → TYPO3 short-form domain reference, passed through to sL
     *
     * @param list<mixed> $arguments
     */
    public function translate(string $xlfKey, array $arguments = []): string
    {
        if (str_starts_with($xlfKey, 'LLL:')) {
            $fullKey = $xlfKey;
        } else {
            $fullKey = null;
            foreach (self::PREFIX_MAP as $prefix => $xliffBase) {
                if (str_starts_with($xlfKey, $prefix)) {
                    $fullKey = $xliffBase.substr($xlfKey, strlen($prefix));

                    break;
                }
            }
            if (null === $fullKey) {
                $fullKey = str_contains($xlfKey, ':') ? $xlfKey : self::DEFAULT_XLIFF.$xlfKey;
            }
        }

        $translated = $this->getLanguageService()->sL($fullKey);

        if ([] !== $arguments) {
            try {
                return sprintf($translated, ...$arguments);
            } catch (\ArgumentCountError|\ValueError) {
                return $translated;
            }
        }

        return $translated;
    }

    public function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
