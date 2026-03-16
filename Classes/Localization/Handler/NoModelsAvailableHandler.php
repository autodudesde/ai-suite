<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Localization\Handler;

use TYPO3\CMS\Backend\Localization\LocalizationHandlerInterface;
use TYPO3\CMS\Backend\Localization\LocalizationInstructions;
use TYPO3\CMS\Backend\Localization\LocalizationResult;
use TYPO3\CMS\Core\Localization\LanguageService;

class NoModelsAvailableHandler implements LocalizationHandlerInterface
{
    private const LLL_PREFIX = 'LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:aiSuite.localizationHandler.noModelsAvailable.';

    public function getIdentifier(): string
    {
        return 'ai-suite-no-models';
    }

    public function getLabel(): string
    {
        return $this->getLanguageService()->sL(self::LLL_PREFIX . 'label');
    }

    public function getDescription(): string
    {
        return $this->getLanguageService()->sL(self::LLL_PREFIX . 'description');
    }

    public function getIconIdentifier(): string
    {
        return 'tx-aisuite-extension';
    }

    public function isAvailable(LocalizationInstructions $instructions): bool
    {
        return true;
    }

    public function processLocalization(LocalizationInstructions $instructions): LocalizationResult
    {
        return LocalizationResult::error([
            $this->getLanguageService()->sL(self::LLL_PREFIX . 'error'),
        ]);
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
