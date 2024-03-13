<?php

declare(strict_types=1);

/***
 *
 * This file is part of the "ai_suite" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *
 ***/

namespace AutoDudes\AiSuite\Domain\Model\Dto;

use SimpleXMLElement;
use TYPO3\CMS\Core\Package\PackageInterface;

class XliffFile
{
    protected string $filename;
    protected PackageInterface $package;
    protected string $langKey;
    protected SimpleXMLElement $simpleXMLElement;
    protected array $rawData;
    protected array $formatedData;

    public function __construct(
        string $filename,
        PackageInterface $package,
        string $langKey,
        SimpleXMLElement $simpleXMLElement,
        array $rawData,
        array $formatedData
    ) {
        $this->filename = $filename;
        $this->package = $package;
        $this->langKey = $langKey;
        $this->simpleXMLElement = $simpleXMLElement;
        $this->rawData = $rawData;
        $this->formatedData = $formatedData;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getPackage(): PackageInterface
    {
        return $this->package;
    }

    public function getLangKey(): string
    {
        return $this->langKey;
    }

    public function getSimpleXMLElement(): SimpleXMLElement
    {
        return $this->simpleXMLElement;
    }

    public function getRawData(): array
    {
        return $this->rawData;
    }

    public function getFormatedData(): array
    {
        return $this->formatedData;
    }

    public function hasTranslationKey(string $translationKey): bool
    {
        return array_key_exists($translationKey, $this->formatedData);
    }
}
