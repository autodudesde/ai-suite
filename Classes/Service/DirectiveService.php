<?php

namespace AutoDudes\AiSuite\Service;

use TYPO3\CMS\Core\SingletonInterface;

class DirectiveService implements SingletonInterface
{
    private const MAX_POST_SIZE = 15 * 1024 * 1024;

    public function getEffectiveMaxUploadSize(): int
    {
        return self::MAX_POST_SIZE;
    }
}
