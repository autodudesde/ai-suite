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
use SimpleXMLElement;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Package\Exception\UnknownPackageException;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class XliffUtility
{
    /**
     * @throws FileNotFoundException
     * @throws UnknownPackageException
     * @throws Exception
     */
    public static function getTranslateValues(XlfInput $input): array
    {
        $sourceFile = self::readXliff($input->getExtensionKey(), $input->getFilename());
        $destinationFile = false;
        if ($input->getTranslationMode() === 'missingProperties') {
            $destinationFile = self::readXliff($input->getExtensionKey(), $input->getDestinationLanguage() . '.' . $input->getFilename());
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
    public static function readXliff(string $extKey, string $filename, string $langKey = 'en'): XliffFile
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
        return new XliffFile(
            $filename,
            $package,
            $langKey,
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
            foreach ($rawData['file']['body']['trans-unit'] as $langItem) {
                if (array_key_exists('@id', $langItem)) {
                    $data[$langItem['@id']] = [
                        'originalData' => $langItem,
                        'source' => array_key_exists('source', $langItem) ? $langItem['source'] : '',
                    ];
                }
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
            $destinationFile = self::readXliff($input->getExtensionKey(), $input->getFilename());
            $newTranslation = $destinationFile->getSimpleXMLElement();
        } else {
            $newTranslation = $sourceFile->getSimpleXMLElement();
        }
        $newTranslation->file->addAttribute('target-language', $input->getDestinationLanguage());
        if($input->getTranslationMode() === 'missingProperties') {
            $newTranslation->file->body->addChild('note', 'This file was automatically generated by the AI Suite extension. Please do not edit this file manually.');
        }

        for ($i = 0; $i < count($newTranslation->file->body->{"trans-unit"}); $i++) {
            $unitAttributes = $newTranslation->file->body->{"trans-unit"}[$i]->attributes()["id"];
            if (array_key_exists('' . $unitAttributes[0], $translations)) {
                $newTranslation->file->body->{"trans-unit"}[$i]->addChild('target', $translations['' . $unitAttributes[0]]);
            }
        }

        $packageManager = GeneralUtility::makeInstance(PackageManager::class);
        $package = $packageManager->getPackage($input->getExtensionKey());
        $packagePath = $package->getPackagePath();
        $file = $packagePath . 'Resources/Private/Language/' . $input->getDestinationLanguage() . '.' . $input->getFilename();
        return $newTranslation->asXML($file);
    }
}
