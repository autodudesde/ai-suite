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

namespace AutoDudes\AiSuite\Service;

use AutoDudes\AiSuite\Domain\Model\Dto\XliffFile;
use AutoDudes\AiSuite\Exception\AiSuiteException;
use SimpleXMLElement;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Package\Exception\UnknownPackageException;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\SingletonInterface;

class XliffService implements SingletonInterface
{
    protected PackageManager $packageManager;

    public function __construct(
        PackageManager $packageManager
    ) {
        $this->packageManager = $packageManager;
    }

    /**
     * @throws FileNotFoundException
     * @throws UnknownPackageException
     * @throws Exception
     */
    public function getTranslateValues(string $extensionKey, string $filename, string $destinationLanguage, string $translationMode): array
    {
        $sourceFile = $this->readXliff($extensionKey, $filename);
        $destinationFile = false;
        if ($translationMode === 'missingProperties') {
            $destinationFile = $this->readXliff($extensionKey, $destinationLanguage . '.' . $filename, false);
        }
        $neededTranslations = $sourceFile->getFormatedData();
        foreach ($neededTranslations as $transKey => $transValue) {
            if ($translationMode === 'missingProperties' && $destinationFile instanceof XliffFile && $destinationFile->hasTranslationKey($transKey)) {
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
        if (empty($rawData['file']['body']) && $sourceFile) {
            throw new AiSuiteException('Agencies/TranslateXlf', 'aiSuite.module.sourceXliffFileEmpty.title');
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


    public function simpleXMLElementToArray(SimpleXMLElement $xml): string|array
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

    /**
     * @throws FileNotFoundException|UnknownPackageException
     */
    public function writeXliff(array $fileData, array $translations): bool
    {
        $sourceFile = $this->readXliff($fileData['extensionKey'], $fileData['filename']);
        if ($fileData['translationMode'] === 'missingProperties') {
            $destinationFile = $this->readXliff($fileData['extensionKey'], $fileData['destinationLanguage'] . '.' .$fileData['filename'], false);
            $newTranslation = $sourceFile->getSimpleXMLElement();
            for ($i = 0; $i < count($sourceFile->getRawData()['file']['body']['trans-unit']); $i++) {
                $unitAttributes = self::getUnitAttributes($sourceFile, $i);
                if (array_key_exists($unitAttributes, $translations)) {
                    if (count($sourceFile->getFormatedData()) === 1) {
                        if ($newTranslation->file->body->{"trans-unit"}->target->count() === 0) {
                            $newTranslation->file->body->{"trans-unit"}->addChild('target', $translations['' . $unitAttributes]);
                        }
                    } else {
                        $newTranslation->file->body->{"trans-unit"}[$i]->addChild('target', $translations['' . $unitAttributes]);
                    }
                } else {
                    if (array_key_exists($unitAttributes, $destinationFile->getFormatedData())) {
                        $newTranslation->file->body->{"trans-unit"}[$i]->addChild('target', $destinationFile->getFormatedData()[$unitAttributes]['target']);
                    }
                }
            }
        } else {
            $newTranslation = $sourceFile->getSimpleXMLElement();
            $newTranslation->file->addAttribute('target-language', $fileData['destinationLanguage']);
            for ($i = 0; $i < count($sourceFile->getRawData()['file']['body']['trans-unit']); $i++) {
                $unitAttributes = self::getUnitAttributes($sourceFile, $i);
                if (array_key_exists($unitAttributes, $translations)) {
                    if (count($sourceFile->getFormatedData()) === 1) {
                        if ($newTranslation->file->body->{"trans-unit"}->target->count() === 0) {
                            $newTranslation->file->body->{"trans-unit"}->addChild('target', $translations['' . $unitAttributes]);
                        }
                    } else {
                        $newTranslation->file->body->{"trans-unit"}[$i]->addChild('target', $translations['' . $unitAttributes]);
                    }
                }
            }
        }

        $package = $this->packageManager->getPackage($fileData['extensionKey']);
        $packagePath = $package->getPackagePath();
        $file = $packagePath . 'Resources/Private/Language/' . $fileData['destinationLanguage'] . '.' . $fileData['filename'];
        return $newTranslation->asXML($file);
    }

    public function getUnitAttributes($sourceFile, int $i): string
    {
        if (array_key_exists($i, $sourceFile->getRawData()['file']['body']['trans-unit'])) {
            $unitAttributes = $sourceFile->getRawData()['file']['body']['trans-unit'][$i]["@id"];
        } else {
            $unitAttributes = array_key_first($sourceFile->getFormatedData());
        }
        return $unitAttributes;
    }
}
