<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class HmacService
{
    public function __construct(
        private readonly Typo3Version $typo3Version,
    ) {}

    public function hmac(string $input, string $additionalSecret): string
    {
        if ('' === (string) ($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] ?? '') || '' === $additionalSecret) {
            throw new \LogicException('An HMAC needs the encryptionKey and a non-empty additional secret.', 1760900000);
        }

        if ($this->typo3Version->getMajorVersion() >= 13) {
            return GeneralUtility::makeInstance(HashService::class)->hmac($input, $additionalSecret);
        }

        // @phpstan-ignore-next-line staticMethod.notFound — v12 fallback, GeneralUtility::hmac() was removed in v14.
        return GeneralUtility::hmac($input, $additionalSecret);
    }

    public function validate(string $input, string $additionalSecret, string $hmac): bool
    {
        return hash_equals($this->hmac($input, $additionalSecret), $hmac);
    }
}
