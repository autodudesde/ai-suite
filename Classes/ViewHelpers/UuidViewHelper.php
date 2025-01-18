<?php

namespace AutoDudes\AiSuite\ViewHelpers;

use AutoDudes\AiSuite\Service\UuidService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

final class UuidViewHelper extends AbstractViewHelper
{
    public function render(): string
    {
        $uuidService = GeneralUtility::makeInstance(UuidService::class);
        return $uuidService->generateUuid();
    }
}
