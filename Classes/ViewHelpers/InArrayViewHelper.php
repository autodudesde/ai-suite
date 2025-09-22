<?php

namespace AutoDudes\AiSuite\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

final class InArrayViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('needle', 'mixed', 'Value which should be checked', true);
        $this->registerArgument('haystack', 'array', 'Array with values', true);
    }

    public function render(): string
    {
        if (array_key_exists($this->arguments['needle'], $this->arguments['haystack'])) {
            return $this->arguments['haystack'][$this->arguments['needle']];
        }
        return '';
    }
}
