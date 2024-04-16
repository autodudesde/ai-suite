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

namespace AutoDudes\AiSuite\Utility;

use AutoDudes\AiSuite\Domain\Model\Dto\XlfInput;
use AutoDudes\AiSuite\Domain\Model\Dto\XliffFile;
use AutoDudes\AiSuite\Exception\EmptyXliffException;
use SimpleXMLElement;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Package\Exception\UnknownPackageException;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class XliffUtility
{
    /**
     * @throws FileNotFoundException
     * @throws UnknownPackageException
     * @throws Exception
     * @throws EmptyXliffException
     */
    public static function getTranslateValues(XlfInput $input): array
    {
        $sourceFile = self::readXliff($input->getExtensionKey(), $input->getFilename());
        $destinationFile = false;
        if ($input->getTranslationMode() === 'missingProperties') {
            $destinationFile = self::readXliff($input->getExtensionKey(), $input->getDestinationLanguage() . '.' . $input->getFilename(), false);
        }
        $neededTranslations = $sourceFile->getFormatedData();
        // cleanup translation items
        foreach ($neededTranslations as $transKey => $transValue) {
            if ($input->getTranslationMode() === 'missingProperties' && $destinationFile instanceof XliffFile && $destinationFile->hasTranslationKey($transKey)) {
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
    public static function readXliff(string $extKey, string $filename, bool $sourceFile = true): XliffFile
    {
        $packageManager = GeneralUtility::makeInstance(PackageManager::class);
        $package = $packageManager->getPackage($extKey);
        $packagePath = $package->getPackagePath();

        $file = $packagePath . 'Resources/Private/Language/' . $filename;
        try {
            $fileData = file_get_contents($file);
            if ($fileData === false) {
                throw new FileNotFoundException('File ' . $file . ' not found in EXT:' . $extKey . '.');
            }
        } catch (\Exception $e) {
            throw new FileNotFoundException('File ' . $file . ' not found in EXT:' . $extKey . '.');
        }
        $xmlData = new \SimpleXMLElement($fileData);
        $rawData = self::simpleXMLElementToArray($xmlData);
        if(empty($rawData['file']['body']) && $sourceFile) {
            throw new EmptyXliffException(LocalizationUtility::translate('aiSuite.module.sourceXliffFileEmpty.title', 'ai_suite'));
        } elseif (empty($rawData['file']['body']) && !$sourceFile) {
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


    public static function simpleXMLElementToArray(SimpleXMLElement $xml): string|array
    {
        $attributes = $xml->attributes();
        $children = $xml->children();
        $text = trim((string) $xml);

        if (count($children) == 0 && count($attributes) == 0) {
            return $text;
        }

        $arr = [];
        foreach ($attributes as $k => $v) {
            $arr['@' . $k] = (string) $v;
        }

        foreach ($children as $child) {
            $childName = $child->getName();

            if (count($xml->$childName) > 1) {
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

    protected static function xmlArrayToStructuredArray(array $rawData): array
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
            } else if (is_array($rawData['file']['body']['trans-unit'])) {
                $itemData = [];
                foreach ( $rawData['file']['body']['trans-unit'] as $key => $itemValue) {
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

    /**
     * @throws FileNotFoundException|UnknownPackageException
     */
    public static function writeXliff(XlfInput $input): bool
    {
        $translations = $input->getTranslations();
        $sourceFile = self::readXliff($input->getExtensionKey(), $input->getFilename());
        if ($input->getTranslationMode() === 'missingProperties') {
            $destinationFile = self::readXliff($input->getExtensionKey(), $input->getDestinationLanguage() . '.' .$input->getFilename(), false);
            $newTranslation = $sourceFile->getSimpleXMLElement();
            for ($i = 0; $i < count($sourceFile->getRawData()['file']['body']['trans-unit']); $i++) {
                $unitAttributes = self::getUnitAttributes($sourceFile, $i);
                if (array_key_exists($unitAttributes, $translations)) {
                    if(count($sourceFile->getFormatedData()) === 1) {
                        if($newTranslation->file->body->{"trans-unit"}->target->count() === 0) {
                            $newTranslation->file->body->{"trans-unit"}->addChild('target', $translations['' . $unitAttributes]);
                        }
                    } else {
                        $newTranslation->file->body->{"trans-unit"}[$i]->addChild('target', $translations['' . $unitAttributes]);
                    }
                } else {
                    $newTranslation->file->body->{"trans-unit"}[$i]->addChild('target', $destinationFile->getFormatedData()[$unitAttributes]['target']);
                }
            }
        } else {
            $newTranslation = $sourceFile->getSimpleXMLElement();
            $newTranslation->file->addAttribute('target-language', $input->getDestinationLanguage());
            for ($i = 0; $i < count($sourceFile->getRawData()['file']['body']['trans-unit']); $i++) {
                $unitAttributes = self::getUnitAttributes($sourceFile, $i);
                if (array_key_exists($unitAttributes, $translations)) {
                    if(count($sourceFile->getFormatedData()) === 1) {
                        if($newTranslation->file->body->{"trans-unit"}->target->count() === 0) {
                            $newTranslation->file->body->{"trans-unit"}->addChild('target', $translations['' . $unitAttributes]);
                        }
                    } else {
                        $newTranslation->file->body->{"trans-unit"}[$i]->addChild('target', $translations['' . $unitAttributes]);
                    }
                }
            }
        }

        $packageManager = GeneralUtility::makeInstance(PackageManager::class);
        $package = $packageManager->getPackage($input->getExtensionKey());
        $packagePath = $package->getPackagePath();
        $file = $packagePath . 'Resources/Private/Language/' . $input->getDestinationLanguage() . '.' . $input->getFilename();
        return $newTranslation->asXML($file);
    }

    public static function getUnitAttributes($sourceFile, int $i): string {
        if(array_key_exists($i, $sourceFile->getRawData()['file']['body']['trans-unit'])) {
            $unitAttributes = $sourceFile->getRawData()['file']['body']['trans-unit'][$i]["@id"];
        } else {
            $unitAttributes = array_key_first($sourceFile->getFormatedData());
        }
        return $unitAttributes;
    }
}
