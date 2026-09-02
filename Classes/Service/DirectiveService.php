<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

use TYPO3\CMS\Core\SingletonInterface;

class DirectiveService implements SingletonInterface
{
    private const MAX_POST_SIZE = 15 * 1024 * 1024;

    private const MAX_ITEMS_PER_REQUEST = 50;

    public function getEffectiveMaxUploadSize(): int
    {
        return self::MAX_POST_SIZE;
    }

    public function getEffectiveMaxItemsPerRequest(): int
    {
        return self::MAX_ITEMS_PER_REQUEST;
    }
}
