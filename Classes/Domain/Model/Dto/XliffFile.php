<?php

declare(strict_types=1);

/*
 *
 * This file is part of the "ai_suite" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *
 */

namespace AutoDudes\AiSuite\Domain\Model\Dto;

use TYPO3\CMS\Core\Package\PackageInterface;

class XliffFile
{
    /**
     * @param array<string, mixed> $formatedData
     * @param array<string, mixed> $rawData
     */
    public function __construct(
        protected readonly string $filename,
        protected readonly PackageInterface $package,
        protected readonly \SimpleXMLElement $simpleXMLElement,
        protected readonly array $rawData,
        protected readonly array $formatedData,
    ) {}

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getPackage(): PackageInterface
    {
        return $this->package;
    }

    public function getSimpleXMLElement(): \SimpleXMLElement
    {
        return $this->simpleXMLElement;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRawData(): array
    {
        return $this->rawData;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFormatedData(): array
    {
        return $this->formatedData;
    }

    public function getSourceLanguage(): string
    {
        return $this->rawData['file']['@attributes']['source-language'] ?? 'en';
    }

    public function hasTranslationKey(string $translationKey): bool
    {
        return array_key_exists($translationKey, $this->formatedData);
    }
}
