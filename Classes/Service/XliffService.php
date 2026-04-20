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

namespace AutoDudes\AiSuite\Service;

use AutoDudes\AiSuite\Domain\Model\Dto\XliffFile;
use AutoDudes\AiSuite\Exception\AiSuiteException;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Package\Exception\UnknownPackageException;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\SingletonInterface;

class XliffService implements SingletonInterface
{
    public function __construct(
        protected readonly PackageManager $packageManager,
        protected readonly LocalizationService $localizationService,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws FileNotFoundException
     * @throws UnknownPackageException
     * @throws Exception
     */
    public function getTranslateValues(string $extensionKey, string $filename, string $destinationLanguage, string $translationMode): array
    {
        $sourceFile = $this->readXliff($extensionKey, $filename);
        $destinationFile = false;
        if ('missingProperties' === $translationMode) {
            $destinationFile = $this->readXliff($extensionKey, $destinationLanguage.'.'.$filename, false);
        }
        $neededTranslations = $sourceFile->getFormatedData();
        foreach ($neededTranslations as $transKey => $transValue) {
            if ('missingProperties' === $translationMode && $destinationFile instanceof XliffFile && $destinationFile->hasTranslationKey($transKey)) {
                unset($neededTranslations[$transKey]);
            } else {
                unset($neededTranslations[$transKey]['originalData']);
            }
        }

        return $neededTranslations;
    }

    /**
     * @throws FileNotFoundException
     * @throws UnknownPackageException
     * @throws \Exception
     */
    public function readXliff(string $extKey, string $filename, bool $sourceFile = true): XliffFile
    {
        $package = $this->packageManager->getPackage($extKey);
        $packagePath = $package->getPackagePath();

        $file = $packagePath.'Resources/Private/Language/'.$filename;

        try {
            $fileData = file_get_contents($file);
            if (false === $fileData) {
                throw new FileNotFoundException($this->localizationService->translate('aiSuite.error.file.notFound', [$file, $extKey]));
            }
        } catch (\Exception $e) {
            throw new FileNotFoundException($this->localizationService->translate('aiSuite.error.file.notFound', [$file, $extKey]));
        }
        $xmlData = new \SimpleXMLElement($fileData);

        /** @var array<string, mixed> $rawData */
        $rawData = self::simpleXMLElementToArray($xmlData);
        if (empty($rawData['file']['body']) && $sourceFile) {
            throw new AiSuiteException('Agencies/TranslateXlf', 'aiSuite.module.sourceXliffFileEmpty.title');
        }
        if (empty($rawData['file']['body']) && !$sourceFile) {
            $rawData['file']['body'] = [];
        }

        return new XliffFile(
            $filename,
            $package,
            $xmlData,
            $rawData,
            self::xmlArrayToStructuredArray($rawData)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function simpleXMLElementToArray(\SimpleXMLElement $xml): array|string
    {
        $attributes = $xml->attributes();
        $children = $xml->children();
        $text = trim((string) $xml);

        if (0 == count($children) && 0 == count($attributes)) {
            return $text;
        }

        $arr = [];
        foreach ($attributes as $k => $v) {
            $arr['@'.$k] = (string) $v;
        }

        foreach ($children as $child) {
            $childName = $child->getName();

            if (count($xml->{$childName}) > 1) {
                if (!isset($arr[$childName]) || !is_array($arr[$childName])) {
                    $arr[$childName] = [];
                }
                $arr[$childName][] = self::simpleXMLElementToArray($child);
            } else {
                $arr[$childName] = self::simpleXMLElementToArray($child);
            }
        }

        if (!empty($text)) {
            $arr['_text'] = $text;
        }

        return $arr;
    }

    /**
     * @param array<string, mixed> $fileData
     * @param array<string, mixed> $translations
     *
     * @throws FileNotFoundException|UnknownPackageException
     */
    public function writeXliff(array $fileData, array $translations): bool
    {
        $sourceFile = $this->readXliff($fileData['extensionKey'], $fileData['filename']);
        if ('missingProperties' === $fileData['translationMode']) {
            $destinationFile = $this->readXliff($fileData['extensionKey'], $fileData['destinationLanguage'].'.'.$fileData['filename'], false);
            $newTranslation = $sourceFile->getSimpleXMLElement();
            for ($i = 0; $i < count($sourceFile->getRawData()['file']['body']['trans-unit']); ++$i) {
                $unitAttributes = self::getUnitAttributes($sourceFile, $i);
                if (array_key_exists($unitAttributes, $translations)) {
                    if (1 === count($sourceFile->getFormatedData())) {
                        if (0 === $newTranslation->file->body->{'trans-unit'}->target->count()) {
                            $safeValue = $this->escapeForXml((string) $translations[''.$unitAttributes]);
                            $newTranslation->file->body->{'trans-unit'}->addChild('target', $safeValue);
                        }
                    } else {
                        $safeValue = $this->escapeForXml((string) $translations[''.$unitAttributes]);
                        $newTranslation->file->body->{'trans-unit'}[$i]->addChild('target', $safeValue);
                    }
                } else {
                    if (array_key_exists($unitAttributes, $destinationFile->getFormatedData())) {
                        $safeValue = $this->escapeForXml((string) $destinationFile->getFormatedData()[$unitAttributes]['target']);
                        $newTranslation->file->body->{'trans-unit'}[$i]->addChild('target', $safeValue);
                    }
                }
            }
        } else {
            $newTranslation = $sourceFile->getSimpleXMLElement();
            $newTranslation->file->addAttribute('target-language', $fileData['destinationLanguage']);
            for ($i = 0; $i < count($sourceFile->getRawData()['file']['body']['trans-unit']); ++$i) {
                $unitAttributes = self::getUnitAttributes($sourceFile, $i);
                if (array_key_exists($unitAttributes, $translations)) {
                    if (1 === count($sourceFile->getFormatedData())) {
                        if (0 === $newTranslation->file->body->{'trans-unit'}->target->count()) {
                            $safeValue = $this->escapeForXml((string) $translations[''.$unitAttributes]);
                            $newTranslation->file->body->{'trans-unit'}->addChild('target', $safeValue);
                        }
                    } else {
                        $safeValue = $this->escapeForXml((string) $translations[''.$unitAttributes]);
                        $newTranslation->file->body->{'trans-unit'}[$i]->addChild('target', $safeValue);
                    }
                }
            }
        }

        $package = $this->packageManager->getPackage($fileData['extensionKey']);
        $packagePath = $package->getPackagePath();
        $file = $packagePath.'Resources/Private/Language/'.$fileData['destinationLanguage'].'.'.$fileData['filename'];

        return $newTranslation->asXML($file);
    }

    public function getUnitAttributes(XliffFile $sourceFile, int $i): string
    {
        if (array_key_exists($i, $sourceFile->getRawData()['file']['body']['trans-unit'])) {
            $unitAttributes = $sourceFile->getRawData()['file']['body']['trans-unit'][$i]['@id'];
        } else {
            $unitAttributes = array_key_first($sourceFile->getFormatedData());
        }

        return $unitAttributes;
    }

    /**
     * @param array<string, mixed> $rawData
     *
     * @return array<string, mixed>
     */
    protected function xmlArrayToStructuredArray(array $rawData): array
    {
        $data = [];
        if (array_key_exists('file', $rawData) && array_key_exists('body', $rawData['file']) && array_key_exists('trans-unit', $rawData['file']['body'])) {
            if (array_key_exists('0', $rawData['file']['body']['trans-unit'])) {
                foreach ($rawData['file']['body']['trans-unit'] as $langItem) {
                    if (is_array($langItem) && array_key_exists('@id', $langItem)) {
                        $data[$langItem['@id']] = [
                            'originalData' => $langItem,
                            'source' => array_key_exists('source', $langItem) ? $langItem['source'] : '',
                            'target' => array_key_exists('target', $langItem) ? $langItem['target'] : '',
                        ];
                    }
                }
            } elseif (is_array($rawData['file']['body']['trans-unit'])) {
                $itemData = [];
                foreach ($rawData['file']['body']['trans-unit'] as $key => $itemValue) {
                    $itemData[$key] = $itemValue;
                }
                $data[$itemData['@id']] = [
                    'originalData' => $itemData,
                    'source' => array_key_exists('source', $itemData) ? $itemData['source'] : '',
                    'target' => array_key_exists('target', $itemData) ? $itemData['target'] : '',
                ];
            }
        }

        return $data;
    }

    private function escapeForXml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
