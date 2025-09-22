<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Hooks;

use B13\Container\Domain\Factory\ContainerFactory;
use B13\Container\Domain\Factory\Exception;
use B13\Container\Domain\Service\ContainerService;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CommandMapPostProcessingHook extends \B13\Container\Hooks\Datahandler\CommandMapPostProcessingHook
{
    protected ContainerService $containerService;
    public function __construct(ContainerFactory $containerFactory, ?ContainerService $containerService = null)
    {
        if ($containerService === null) {
            parent::__construct($containerFactory);
            $this->containerService = GeneralUtility::makeInstance(ContainerService::class);
        } else {
            parent::__construct($containerFactory, $containerService);
        }
    }

    protected function localizeOrCopyToLanguage(int $uid, int $language, string $command, DataHandler $dataHandler): void
    {
        try {
            $container = $this->containerFactory->buildContainer($uid);
            $last = $dataHandler->copyMappingArray['tt_content'][$uid] ?? null;
            if ($command === 'copyToLanguage') {
                $containerId = $last;
                $pos = $this->containerService->getAfterContainerElementTarget($container);
                // move next record after last child
                $cmd = ['tt_content' => [$last => [
                    'move' => [
                        'target' => $pos,
                        'action' => 'paste',
                        'update' => [],
                    ]
                ]]];
                $localDataHandler = GeneralUtility::makeInstance(DataHandler::class);
                $localDataHandler->enableLogging = $dataHandler->enableLogging;
                $localDataHandler->start([], $cmd, $dataHandler->BE_USER);
                $localDataHandler->process_cmdmap();
            } else {
                $containerId = $container->getUid();
            }
            $children = $container->getChildRecords();
            $children = array_reverse($children);
            $cmd = ['tt_content' => []];
            foreach ($children as $colPos => $record) {
                $cmd = ['tt_content' => [$record['uid'] => [$command => $language]]];
                $localDataHandler = GeneralUtility::makeInstance(DataHandler::class);
                $localDataHandler->enableLogging = $dataHandler->enableLogging;
                $localDataHandler->start([], $cmd, $dataHandler->BE_USER);
                $localDataHandler->process_cmdmap();

                foreach ($localDataHandler->copyMappingArray_merged as $tableKey => $table) {
                    foreach ($table as $ceSrcLangUid => $ceDestLangUid) {
                        $dataHandler->copyMappingArray_merged[$tableKey][$ceSrcLangUid] = $ceDestLangUid;
                    }
                }

                $newId = $localDataHandler->copyMappingArray['tt_content'][$record['uid']] ?? null;
                if ($newId === null) {
                    continue;
                }
                $cmd = ['tt_content' => [$newId => [
                    'move' => [
                        'target' => -$last,
                        'action' => 'paste',
                        'update' => [
                            'tx_container_parent' => $containerId,
                        ]
                    ]
                ]]];
                $localDataHandler = GeneralUtility::makeInstance(DataHandler::class);
                $localDataHandler->enableLogging = $dataHandler->enableLogging;
                $localDataHandler->start([], $cmd, $dataHandler->BE_USER);
                $localDataHandler->process_cmdmap();
                $last = $newId;
            }
        } catch (Exception $e) {
            // nothing todo
        }
    }

    /**
     * in b13/container, version 3.1.10: function structure changed
     */
    protected function localizeChildren(int $uid, int $language, string $command, DataHandler $dataHandler): void
    {
        try {
            $container = $this->containerFactory->buildContainer($uid);
            $children = $container->getChildRecords();
            foreach ($children as $record) {
                $cmd = ['tt_content' => [$record['uid'] => [$command => $language]]];
                $localDataHandler = GeneralUtility::makeInstance(DataHandler::class);
                $localDataHandler->enableLogging = $dataHandler->enableLogging;
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
