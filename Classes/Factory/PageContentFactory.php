<?php

/***
 *
 * This file is part of the "ai_suite" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *
 ***/

namespace AutoDudes\AiSuite\Factory;

use AutoDudes\AiSuite\Domain\Model\Dto\PageContent;
use AutoDudes\AiSuite\Service\FileNameSanitizerService;
use Symfony\Component\Filesystem\Filesystem;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFolderAccessPermissionsException;
use TYPO3\CMS\Core\Resource\StorageRepository;

class PageContentFactory
{
    protected DataHandler $dataHandler;
    protected StorageRepository $storageRepository;
    protected Filesystem $filesystem;
    protected LinkService $linkService;

    public function __construct(
        DataHandler $dataHandler,
        StorageRepository $storageRepository,
        Filesystem $filesystem,
        LinkService $linkService
    ) {
        $this->dataHandler = $dataHandler;
        $this->storageRepository = $storageRepository;
        $this->filesystem = $filesystem;
        $this->linkService = $linkService;
    }

    /**
     * @throws Exception
     * @throws InsufficientFolderAccessPermissionsException
     */
    public function createContentElementData(
        PageContent $content,
        array $contentElementTextData,
        array $contentElementImageData,
        array $contentElementIrreFields
    ): void
    {
        $data = [];
        $newStrings = [];
        // content element without text or textarea fields, set inital data
        if(count($contentElementTextData) === 0) {
            $newStrings['tt_content'] = $this->newStringPlaceholder('tt_content');
            $data['tt_content'][$newStrings['tt_content']]["colPos"] = $content->getColPos();
            $data['tt_content'][$newStrings['tt_content']]["CType"] = $content->getCType();
            $data['tt_content'][$newStrings['tt_content']]["pid"] = $content->getPid();
            $data['tt_content'][$newStrings['tt_content']]["sys_language_uid"] = $content->getSysLanguageUid();
        }

        foreach ($contentElementTextData as $table => $fieldsArray) {
            $newStrings[$table] = [];
            foreach ($fieldsArray as $key => $fields) {
                if($table === 'tt_content') {
                    $newStrings[$table] = $this->newStringPlaceholder($table);
                    $data[$table][$newStrings[$table]]["colPos"] = $content->getColPos();
                    $data[$table][$newStrings[$table]]["CType"] = $content->getCType();
                    $data[$table][$newStrings[$table]]["pid"] = $content->getPid();
                    $data[$table][$newStrings[$table]]["sys_language_uid"] = $content->getSysLanguageUid();
                    foreach($fields as $fieldName => $fieldValue) {
                        $data[$table][$newStrings[$table]][$fieldName] = $fieldValue;
                    }
                } else {
                    $newStrings[$table][$key] = $this->newStringPlaceholder($table, $key);
                    $data[$table][$newStrings[$table][$key]]["pid"] = $content->getPid();
                    $data[$table][$newStrings[$table][$key]]["sys_language_uid"] = $content->getSysLanguageUid();
                    foreach($fields as $fieldName => $fieldValue) {
                        $data[$table][$newStrings[$table][$key]][$fieldName] = $fieldValue;
                    }
                }
            }
        }
        // add irre relations
        foreach ($contentElementIrreFields as $table => $parentTable) {
            if(!array_key_exists($table, $newStrings)) {
                continue;
            }
            if(is_array($newStrings[$table])) {
                $data[$parentTable][$newStrings[$parentTable]][$table] = implode(',', $newStrings[$table]);
            } else {
                $data[$parentTable][$newStrings[$parentTable]][$table] = $newStrings[$table];
            }
        }
        // add image relations
        foreach($contentElementImageData as $table => $fieldsArray) {
            foreach ($fieldsArray as $key => $fields) {
                foreach ($fields as $fieldName => $fieldData) {
                    // add file to fileadmin
                    $newFileUid = $this->addImage($fieldData['newImageUrl'], $fieldData['imageTitle'] ?? '');
                    $newString = $this->newStringPlaceholder($table.'_sys_file_refrence', $key);
                    $data['sys_file_reference'][$newString] = [
                        'table_local' => 'sys_file',
                        'uid_local' => $newFileUid,
                        'tablenames' => $table,
                        'uid_foreign' => $table === 'tt_content' ? $newStrings[$table] : $newStrings[$table][$key],
                        'fieldname' => $fieldName,
                        'pid' => $content->getPid()
                    ];
                    if($table === 'tt_content') {
                        $data[$table][$newStrings[$table]][$fieldName] = $newString;
                    } else {
                        $data[$table][$newStrings[$table][$key]][$fieldName] = $newString;
                    }
                }
            }
        }

        $this->dataHandler->start(
            $data,
            []
        );
        $this->dataHandler->process_datamap();

        if(count($this->dataHandler->errorLog) > 0) {
            throw new Exception('Error while creating content element with message: '. $this->dataHandler->errorLog[0]);
        }

        $tempUid = array_key_first($data['tt_content']);

        $uid = $this->dataHandler->substNEWwithIDs[$tempUid];
        $cmd['tt_content'][$uid]['move'] = $content->getUidPid();
        $this->dataHandler->start([], $cmd);
        $this->dataHandler->process_cmdmap();
    }

    /**
     * @throws InsufficientFolderAccessPermissionsException
     */
    public function addImage(string $imageUrl, string $imageTitle): int
    {
        $title = empty($imageTitle) ? 'ai-generated-image-' . time() . '.jpg' : $imageTitle . '.jpg';
        $fileName = FileNameSanitizerService::sanitize($title);

        $storage = $this->storageRepository->getDefaultStorage();
        $defaultFolder = $storage->getDefaultFolder();
        $defaultFolder->hasFolder('ai-images') ?: $defaultFolder->createFolder('ai-images');
        $aiImagesFolder = $defaultFolder->getSubfolder('ai-images');
        $destinationPath = Environment::getPublicPath() . $aiImagesFolder->getPublicUrl();

        $this->filesystem->copy(
            $imageUrl,
            $destinationPath . $fileName
        );

        $newFile = $aiImagesFolder->getFile($fileName);
        return $newFile->getUid();
    }

    protected function newStringPlaceholder(string $table, $key = 0): string
    {
        return 'NEW' .
            substr(
                md5(time() . $table . $key . rand(0, 100000)),
                0,
                22
            );
    }
}
