<?php

namespace AutoDudes\AiSuite\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

final class IsArrayViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('data', 'mixed', 'Value which should be checked', true);
    }

    public function render(): bool
    {
        return is_array($this->arguments['data']);
    }
}
