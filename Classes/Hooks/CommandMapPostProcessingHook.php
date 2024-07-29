<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Hooks;

use B13\Container\Domain\Factory\ContainerFactory;
use B13\Container\Domain\Factory\Exception;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CommandMapPostProcessingHook extends \B13\Container\Hooks\Datahandler\CommandMapPostProcessingHook
{
    public function __construct(ContainerFactory $containerFactory)
    {
        parent::__construct($containerFactory);
    }

    protected function localizeOrCopyToLanguage(int $uid, int $language, string $command, DataHandler $dataHandler): void
    {
        try {
            $container = $this->containerFactory->buildContainer($uid);
            $children = $container->getChildRecords();
            $children = array_reverse($children);
            $cmd = ['tt_content' => []];
            foreach ($children as $colPos => $record) {
                $cmd['tt_content'][$record['uid']] = [$command => $language];
            }
            if (count($cmd['tt_content']) > 0) {
                $localDataHandler = GeneralUtility::makeInstance(DataHandler::class);
                $localDataHandler->start([], $cmd, $dataHandler->BE_USER);
                $localDataHandler->process_cmdmap();
                foreach ($localDataHandler->copyMappingArray_merged as $tableKey => $table) {
                    foreach ($table as $ceSrcLangUid => $ceDestLangUid) {
                        $dataHandler->copyMappingArray_merged[$tableKey][$ceSrcLangUid] = $ceDestLangUid;
                    }
                }
            }
        } catch (Exception $e) {
            // nothing todo
        }
    }
}
