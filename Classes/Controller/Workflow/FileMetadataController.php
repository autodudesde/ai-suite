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

namespace AutoDudes\AiSuite\Controller\Workflow;

use AutoDudes\AiSuite\Controller\AbstractBackendController;
use AutoDudes\AiSuite\Controller\Trait\AjaxResponseTrait;
use AutoDudes\AiSuite\Domain\Model\Dto\BackgroundTask;
use AutoDudes\AiSuite\Domain\Repository\BackgroundTaskRepository;
use AutoDudes\AiSuite\Domain\Repository\PagesRepository;
use AutoDudes\AiSuite\Domain\Repository\SysFileReferenceRepository;
use AutoDudes\AiSuite\Enumeration\GenerationLibraryEnumeration;
use AutoDudes\AiSuite\Service\AiSuiteContext;
use AutoDudes\AiSuite\Service\DirectiveService;
use AutoDudes\AiSuite\Service\MetadataService;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\TranslationService;
use AutoDudes\AiSuite\Service\ViewFactoryService;
use AutoDudes\AiSuite\Service\WorkflowProcessingService;
use AutoDudes\AiSuite\Service\WorkflowViewService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;

#[AsController]
class FileMetadataController extends AbstractBackendController
{
    use AjaxResponseTrait;

    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        UriBuilder $uriBuilder,
        PageRenderer $pageRenderer,
        FlashMessageService $flashMessageService,
        SendRequestService $requestService,
        TranslationService $translationService,
        EventDispatcher $eventDispatcher,
        AiSuiteContext $aiSuiteContext,
        protected readonly WorkflowProcessingService $workflowProcessingService,
        protected readonly WorkflowViewService $workflowViewService,
        protected readonly LoggerInterface $logger,
        protected readonly PageRepository $pageRepository,
        protected readonly PagesRepository $pagesRepository,
        protected readonly BackgroundTaskRepository $backgroundTaskRepository,
        protected readonly SysFileReferenceRepository $sysFileReferenceRepository,
        protected readonly DirectiveService $directiveService,
        protected readonly ViewFactoryService $viewFactoryService,
    ) {
        parent::__construct(
            $moduleTemplateFactory,
            $uriBuilder,
            $pageRenderer,
            $flashMessageService,
            $requestService,
            $translationService,
            $eventDispatcher,
            $aiSuiteContext,
        );
    }

    public function fileReferencesPrepareExecuteAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();

        try {
            $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibraryEnumeration::METADATA, 'createMetadata', ['text']);
            if ('Error' === $librariesAnswer->getType()) {
                return $this->jsonSuccess($response, $librariesAnswer->getResponseData()['message']);
            }
            $textGenerationLibraries = $librariesAnswer->getResponseData()['textGenerationLibraries'];
            $textGenerationLibraries = $this->aiSuiteContext->libraryService->filterVisionLibraries($textGenerationLibraries);
            $params['textGenerationLibraries'] = $this->aiSuiteContext->libraryService->prepareLibraries($textGenerationLibraries);
            $params['paidRequestsAvailable'] = $librariesAnswer->getResponseData()['paidRequestsAvailable'];

            $workflowData = ((array) $request->getParsedBody())['workflowFileReferencesPrepare'];
            $pageId = (int) $workflowData['startFromPid'];
            $availableLanguages = $this->aiSuiteContext->siteService->getAvailableLanguages(true, $pageId);
            $currentSysLanguage = $workflowData['sysLanguage'];
            $sysLanguageToUse = $currentSysLanguage;
            $notification = '';
            $this->aiSuiteContext->siteService->updateSelectedSysLanguage($availableLanguages, $sysLanguageToUse, $notification, $currentSysLanguage);

            $workflowData['sysLanguage'] = $sysLanguageToUse;
            $workflowData['showOnlyEmpty'] = (array_key_exists('showOnlyEmpty', $workflowData) && $workflowData['showOnlyEmpty']);
            $languageParts = explode('__', $workflowData['sysLanguage']);
            $foundPageUids = $this->pageRepository->getPageIdsRecursive(
                [(int) $workflowData['startFromPid']],
                (int) $workflowData['depth']
            );

            $fileReferenceMetadataColumns = $this->aiSuiteContext->metadataService->getFileMetadataColumns();
            $params['column'] = $workflowData['column'];
            $params['columnName'] = $fileReferenceMetadataColumns[$workflowData['column']];

            $foundFileReferences = $this->pagesRepository->fetchSysFileReferences(array_values($foundPageUids), $workflowData['column'], (int) $languageParts[1], $workflowData['showOnlyEmpty']);
            $params['unsupportedFileReferences'] = array_filter($foundFileReferences, function ($fileReference) {
                if (!$this->aiSuiteContext->metadataService->hasFilePermissions($fileReference['uid_local'])) {
                    return false;
                }

                return !in_array($fileReference['fileMimeType'], MetadataService::SUPPORTED_IMAGE_MIME_TYPES);
            });
            $params['fileReferences'] = array_filter($foundFileReferences, function ($fileReference) {
                if (!$this->aiSuiteContext->metadataService->hasFilePermissions($fileReference['uid_local'])) {
                    return false;
                }

                return in_array($fileReference['fileMimeType'], MetadataService::SUPPORTED_IMAGE_MIME_TYPES);
            });
            $sysFileReferenceUids = array_column($params['fileReferences'], 'uid');
            $alreadyPendingFiles = $this->backgroundTaskRepository->fetchAlreadyPendingEntries($sysFileReferenceUids, 'sys_file_reference', $params['column']);

            $params['alreadyPendingFileReferences'] = array_reduce($alreadyPendingFiles, function ($carry, $item) {
                $carry[$item['table_uid']] = $item['status'] ?? 'pending';

                return $carry;
            }, []);

            $params['maxAllowedFileSize'] = $this->directiveService->getEffectiveMaxUploadSize();
            $params['globalInstructions'] = $this->aiSuiteContext->globalInstructionService->buildGlobalInstruction('pages', 'metadata', $pageId);

            $output = $this->viewFactoryService->renderTemplate(
                $request,
                'FileReferencesPrepareExecute',
                'EXT:ai_suite/Resources/Private/Templates/Ajax/Workflow/',
                $params
            );

            return $this->jsonSuccess($response, [
                'parentUuid' => $this->aiSuiteContext->uuidService->generateUuid(),
                'content' => $output,
                'availableSysLanguages' => $availableLanguages,
                'notification' => $notification,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error while fileReferencesPrepareExecuteAction: '.$e->getMessage());

            return $this->jsonError(
                $response,
                $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.backgroundTask.errorFileReferencesPrepareExecuteAction'),
            );
        }
    }

    public function fileReferencesExecuteAction(ServerRequestInterface $serverRequest): ResponseInterface
    {
        $response = new Response();

        $workflowData = $this->validateParsedBody($serverRequest, 'workflowFileReferencesExecute', $response);
        if ($workflowData instanceof Response) {
            return $workflowData;
        }

        $fileReferences = json_decode($workflowData['fileReferences'], true);
        $languageParts = explode('__', $workflowData['sysLanguage']);
        $payload = [];
        $bulkPayload = [];
        $failedFileReferences = [];
        $allowedFileSize = $this->directiveService->getEffectiveMaxUploadSize();
        $fileSizeSumInBytes = 0;
        foreach ($fileReferences as $sysFileReferenceUid => $sysFileUid) {
            try {
                if (0 === (int) $sysFileUid) {
                    $fileReferenceRow = $this->sysFileReferenceRepository->findByUid((int) $sysFileReferenceUid);
                    if (0 === count($fileReferenceRow) || !array_key_exists('uid_local', $fileReferenceRow[0])) {
                        throw new \Exception($this->aiSuiteContext->localizationService->translate('aiSuite.error.fileReference.notFound', [$sysFileReferenceUid]));
                    }
                    $sysFileUid = (int) $fileReferenceRow[0]['uid_local'];
                }
                $fileContent = $this->aiSuiteContext->metadataService->getFileContent((int) $sysFileUid);
                $filename = $this->aiSuiteContext->metadataService->getFilename((int) $sysFileUid);
                $fileSize = strlen($fileContent);

                if (($fileSizeSumInBytes + $fileSize) >= $allowedFileSize && count($payload) > 0) {
                    $answer = $this->requestService->sendDataRequest(
                        'createMassAction',
                        [
                            'uuid' => $workflowData['parentUuid'],
                            'payload' => $payload,
                            'scope' => 'fileReference',
                            'type' => 'metadata',
                        ],
                        '',
                        $languageParts[0],
                        [
                            'text' => $workflowData['textAiModel'],
                        ]
                    );

                    if ('Error' === $answer->getType()) {
                        $this->logError($answer->getResponseData()['message'], $response, 503);

                        return $response;
                    }
                    $this->backgroundTaskRepository->insertBackgroundTasks($bulkPayload);
                    $payload = [];
                    $bulkPayload = [];
                    $fileSizeSumInBytes = 0;
                }

                $uuid = $this->aiSuiteContext->uuidService->generateUuid();
                $bulkPayload[] = new BackgroundTask(
                    'fileReference',
                    'metadata',
                    $workflowData['parentUuid'],
                    $uuid,
                    $workflowData['column'],
                    'sys_file_reference',
                    'uid',
                    $sysFileReferenceUid,
                    (int) $languageParts[1],
                    ''
                );
                $pageId = (int) $workflowData['startFromPid'];
                $globalInstructions = $this->aiSuiteContext->globalInstructionService->buildGlobalInstruction('pages', 'metadata', $pageId);
                $globalInstructionsOverride = $this->aiSuiteContext->globalInstructionService->checkOverridePredefinedPrompt('pages', 'metadata', [$pageId]);
                $payload[] = [
                    'field_label' => $workflowData['column'],
                    'request_content' => $fileContent,
                    'uuid' => $uuid,
                    'global_instructions' => $globalInstructions,
                    'override_predefined_prompt' => $globalInstructionsOverride,
                    'filename' => $filename,
                ];
                $fileSizeSumInBytes += $fileSize;
            } catch (\Exception $e) {
                $this->logger->error('Error while fetching file content for file with sys file reference uid '.$sysFileReferenceUid.': '.$e->getMessage());
                $failedFileReferences[] = $sysFileReferenceUid;
            }
        }

        $errorResponse = $this->sendWorkflowRequest(
            $payload,
            $bulkPayload,
            $workflowData['parentUuid'],
            'fileReference',
            'metadata',
            $languageParts[0],
            'text',
            $workflowData['textAiModel'],
            $response,
            $this->requestService,
            $this->backgroundTaskRepository,
        );
        if ($errorResponse instanceof Response) {
            return $errorResponse;
        }

        return $this->jsonSuccess($response, [
            'message' => $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.workflow.processing', ['file references']),
            'failedFileReferences' => $failedFileReferences,
        ]);
    }

    public function fileReferencesUpdateAction(ServerRequestInterface $serverRequest): ResponseInterface
    {
        $response = new Response();

        try {
            $workflowData = ((array) $serverRequest->getParsedBody())['workflowFileReferencesExecute'];
            $fileReferences = json_decode($workflowData['fileReferences'], true);

            $datamap = [];
            foreach ($fileReferences as $sysFileReferenceUid => $fileMetadataFieldValue) {
                $datamap['sys_file_reference'][$sysFileReferenceUid] = [
                    $workflowData['column'] => $fileMetadataFieldValue,
                ];
            }
            $this->executeDataHandler($datamap);

            return $this->jsonSuccess($response);
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());

            return $this->jsonError(
                $response,
                $this->aiSuiteContext->localizationService->translate('aiSuite.notification.generation.fileReferencesUpdateError'),
            );
        }
    }

    public function filelistFilesUpdateViewAction(ServerRequestInterface $serverRequest): ResponseInterface
    {
        $response = new Response();

        try {
            $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibraryEnumeration::METADATA, 'createMetadata', ['text']);
            if ('Error' === $librariesAnswer->getType()) {
                return $this->jsonSuccess($response, $librariesAnswer->getResponseData()['message']);
            }

            $viewProperties = $this->workflowViewService->filelistFileDirectorySupport($librariesAnswer);

            $output = $this->viewFactoryService->renderTemplate(
                $serverRequest,
                'FilelistFilesViewUpdate',
                'EXT:ai_suite/Resources/Private/Templates/Ajax/Workflow/',
                $viewProperties
            );

            return $this->jsonSuccess($response, [
                'content' => $output,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error while filesPrepareExecuteAction: '.$e->getMessage());

            return $this->jsonError(
                $response,
                $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.backgroundTask.errorFilesPrepareExecuteAction'),
            );
        }
    }

    public function filelistFilesExecuteAction(ServerRequestInterface $serverRequest): ResponseInterface
    {
        $response = new Response();

        $workflowData = $this->validateParsedBody($serverRequest, 'workflowFilesExecute', $response);
        if ($workflowData instanceof Response) {
            return $workflowData;
        }

        $scope = 'fileMetadata';

        $workflowDataFiles = json_decode($workflowData['files'], true);
        $languageParts = explode('__', $workflowData['sysLanguage']);

        $result = $this->workflowProcessingService->processFilelistFilesForMetadataGeneration(
            $workflowData,
            $workflowDataFiles,
            $languageParts,
            $scope,
            $this->requestService
        );

        $payload = $result['payload'];
        $bulkPayload = $result['bulkPayload'];
        $failedFilesMetadata = $result['failedFilesMetadata'];

        $errorResponse = $this->sendWorkflowRequest(
            $payload,
            $bulkPayload,
            $workflowData['parentUuid'],
            $scope,
            'metadata',
            $languageParts[0],
            'text',
            $workflowData['textAiModel'],
            $response,
            $this->requestService,
            $this->backgroundTaskRepository,
        );
        if ($errorResponse instanceof Response) {
            return $errorResponse;
        }

        return $this->jsonSuccess($response, [
            'message' => $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.workflow.processing', ['files']),
            'failedFiles' => $failedFilesMetadata,
        ]);
    }

    public function filelistFilesUpdateAction(ServerRequestInterface $serverRequest): ResponseInterface
    {
        $response = new Response();

        try {
            $workflowData = (array) $serverRequest->getParsedBody();
            $datamap = [];
            $fileSelection = array_key_exists('file-selection', $workflowData) ? json_decode($workflowData['file-selection'], true) : [];
            if (!is_array($fileSelection) || 0 === count($fileSelection)) {
                return $this->jsonError(
                    $response,
                    $this->aiSuiteContext->localizationService->translate('aiSuite.notification.generation.fileUpdateError.noFileSelected'),
                );
            }

            foreach ($fileSelection as $sysFileUid => $fields) {
                $sysFileUid = (int) $sysFileUid;
                $fileMetaData = $fields;
                foreach ($fileMetaData as $column => $value) {
                    if ($workflowData['options']['column'] === $column || 'all' === $workflowData['options']['column']) {
                        $datamap['sys_file_metadata'][$sysFileUid][$column] = $value;
                    }
                }
            }
            $this->executeDataHandler($datamap);

            return $this->jsonSuccess($response);
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());

            return $this->jsonError(
                $response,
                $this->aiSuiteContext->localizationService->translate('aiSuite.notification.generation.fileUpdateError'),
            );
        }
    }
}
