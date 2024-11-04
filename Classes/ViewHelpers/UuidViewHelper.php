<?php

namespace AutoDudes\AiSuite\ViewHelpers;

use AutoDudes\AiSuite\Utility\UuidUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

final class UuidViewHelper extends AbstractViewHelper
{
    public function render(): string
    {
        return UuidUtility::generateUuid();
    }
}
