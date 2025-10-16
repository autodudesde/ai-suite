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

namespace AutoDudes\AiSuite\Controller\Ajax;

use AutoDudes\AiSuite\Domain\Model\Dto\BackgroundTask;
use AutoDudes\AiSuite\Domain\Repository\BackgroundTaskRepository;
use AutoDudes\AiSuite\Domain\Repository\PagesRepository;
use AutoDudes\AiSuite\Domain\Repository\SysFileMetadataRepository;
use AutoDudes\AiSuite\Domain\Repository\SysFileReferenceRepository;
use AutoDudes\AiSuite\Enumeration\GenerationLibrariesEnumeration;
use AutoDudes\AiSuite\Service\BackendUserService;
use AutoDudes\AiSuite\Service\DirectiveService;
use AutoDudes\AiSuite\Service\GlobalInstructionService;
use AutoDudes\AiSuite\Service\LibraryService;
use AutoDudes\AiSuite\Service\MassActionService;
use AutoDudes\AiSuite\Service\MetadataService;
use AutoDudes\AiSuite\Service\PromptTemplateService;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\SiteService;
use AutoDudes\AiSuite\Service\TranslationService;
use AutoDudes\AiSuite\Service\UuidService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\PreviewUriBuilder;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;

#[AsController]
class MassActionController extends AbstractAjaxController
{
    protected Context $context;

    protected PageRepository $pageRepository;

    protected PagesRepository $pagesRepository;

    protected BackgroundTaskRepository $backgroundTaskRepository;

    protected SysFileMetadataRepository $sysFileMetadataRepository;
    protected MetadataService $metadataService;

    protected MassActionService $massActionService;

    protected array $supportedMimeTypes;

    protected SysFileReferenceRepository $sysFileReferenceRepository;

    protected DirectiveService $directiveService;

    public function __construct(
        BackendUserService $backendUserService,
        SendRequestService $requestService,
        PromptTemplateService $promptTemplateService,
        GlobalInstructionService $globalInstructionService,
        LibraryService $libraryService,
        UuidService $uuidService,
        SiteService $siteService,
        TranslationService $translationService,
        LoggerInterface $logger,
        EventDispatcher $eventDispatcher,
        Context $context,
        PageRepository $pageRepository,
        PagesRepository $pagesRepository,
        BackgroundTaskRepository $backgroundTaskRepository,
        SysFileMetadataRepository $sysFileMetadataRepository,
        MetadataService $metadataService,
        MassActionService $massActionService,
        SysFileReferenceRepository $sysFileReferenceRepository,
        DirectiveService $directiveService
    ) {
        parent::__construct(
            $backendUserService,
            $requestService,
            $promptTemplateService,
            $globalInstructionService,
            $libraryService,
            $uuidService,
            $siteService,
            $translationService,
            $logger,
            $eventDispatcher
        );
        $this->context = $context;
        $this->pageRepository = $pageRepository;
        $this->pagesRepository = $pagesRepository;
        $this->backgroundTaskRepository = $backgroundTaskRepository;
        $this->sysFileMetadataRepository = $sysFileMetadataRepository;
        $this->metadataService = $metadataService;
        $this->massActionService = $massActionService;
        $this->sysFileReferenceRepository = $sysFileReferenceRepository;
        $this->directiveService = $directiveService;

        $this->supportedMimeTypes = [
            "image/jpeg",
            "image/png",
            "image/gif",
            "image/webp",
        ];
    }

    public function pagesPrepareExecuteAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        try {
            $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibrariesEnumeration::METADATA, 'createMetadata', ['text']);
            if ($librariesAnswer->getType() === 'Error') {
                $response->getBody()->write(
                    json_encode(
                        [
                            'success' => false,
                            'output' => $librariesAnswer->getResponseData()['message']
                        ]
                    )
                );
                return $response;
            }
            $textGenerationLibraries = $librariesAnswer->getResponseData()['textGenerationLibraries'];
            $textGenerationLibraries = array_filter($textGenerationLibraries, function ($library) {
                return $library['name'] !== 'Vision';
            });
            $params['textGenerationLibraries'] = $this->libraryService->prepareLibraries($textGenerationLibraries);
            $params['paidRequestsAvailable'] = $librariesAnswer->getResponseData()['paidRequestsAvailable'];

            $massActionData = $request->getParsedBody()['massActionPagesPrepare'];
            $pageId = (int)$massActionData['startFromPid'];
            $availableLanguages = $this->siteService->getAvailableLanguages(true, $pageId);
            $currentSysLanguage = $massActionData['sysLanguage'];
            $sysLanguageToUse = $currentSysLanguage;
            $notification = '';
            $this->siteService->updateSelectedSysLanguage($availableLanguages, $sysLanguageToUse, $notification, $currentSysLanguage);

            $massActionData['sysLanguage'] = $sysLanguageToUse;
            $massActionData['showOnlyEmpty'] = (array_key_exists('showOnlyEmpty', $massActionData) && $massActionData['showOnlyEmpty']);
            $foundPageUids = $this->pageRepository->getPageIdsRecursive(
                [(int)$massActionData['startFromPid']],
                (int)$massActionData['depth']
            );
            $pageMetadataColumns = $this->metadataService->getMetadataColumns();
            $params['column'] = $massActionData['column'];
            $params['columnName'] = $pageMetadataColumns[$massActionData['column']];
            $params['pages'] = $this->pagesRepository->fetchNecessaryPageData($massActionData, $foundPageUids);
            $pagesUids = array_column($params['pages'], 'uid');
            if (count($pagesUids) > 0) {
                $alreadyPendingPages = $this->backgroundTaskRepository->fetchAlreadyPendingEntries($pagesUids, 'pages', $params['column']);

                $params['alreadyPendingPages'] = array_reduce($alreadyPendingPages, function ($carry, $item) {
                    $carry[$item['table_uid']] = $item['status'] ?? 'pending';
                    return $carry;
                }, []);
            }
            $params['globalInstructions'] = $this->globalInstructionService->buildGlobalInstruction('pages', 'metadata', $pageId);

            $output = $this->getContentFromTemplate(
                $request,
                'PagesPrepareExecute',
                'EXT:ai_suite/Resources/Private/Templates/Ajax/MassAction/',
                'MassAction',
                $params,
                false
            );
            $response->getBody()->write(
                json_encode(
                    [
                        'success' => true,
                        'output' => [
                            'parentUuid' => $this->uuidService->generateUuid(),
                            'content' => $output,
                            'availableSysLanguages' => $availableLanguages,
                            'notification' => $notification,
                        ],
                    ]
                )
            );
        } catch (\Exception $e) {
            $this->logger->error('Error while pagesPrepareExecuteAction: ' . $e->getMessage());
            $response->getBody()->write(
                json_encode(
                    [
                        'success' => false,
                        'error' => $this->translationService->translate('AiSuite.backgroundTasks.errorPagesPrepareExecuteAction')
                    ]
                )
            );
        }
        return $response;
    }

    public function pagesExecuteAction(ServerRequestInterface $serverRequest): ResponseInterface
    {
        $response = new Response();

        $parsedBody = $serverRequest->getParsedBody();
        if (!is_array($parsedBody) || !array_key_exists('massActionPagesExecute', $parsedBody)) {
            $this->logger->error('Invalid request: empty parsedBody or missing massActionPagesExecute key in pagesExecuteAction');
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $this->translationService->translate('AiSuite.error.invalidRequest')
            ]));
            return $response;
        }

        $massActionData = $parsedBody['massActionPagesExecute'];
        $pages = json_decode($massActionData['pages'], true);
        $languageParts = explode('__', $massActionData['sysLanguage']);
        $payload = [];
        $bulkPayload = [];
        $failedPages = [];
        foreach ($pages as $pageUid => $pageSlug) {
            $page = $this->pageRepository->getPage($pageUid);
            $previewUriPageId = $pageUid;
            if ($page['is_siteroot'] === 1 && $page['l10n_parent'] > 0) {
                $previewUriPageId = $page['l10n_parent'];
            }
            $previewUriBuilder = PreviewUriBuilder::create($previewUriPageId);
            $previewUri = $previewUriBuilder
                ->withLanguage((int)$languageParts[1])
                ->buildUri();
            $url = $this->siteService->buildAbsoluteUri($previewUri);
            try {
                $pageContent = $this->metadataService->fetchContentFromUrl($url);
                $uuid = $this->uuidService->generateUuid();
                $bulkPayload[] = new BackgroundTask(
                    'page',
                    'metadata',
                    $massActionData['parentUuid'],
                    $uuid,
                    $massActionData['column'],
                    'pages',
                    'uid',
                    $pageUid,
                    (int)$languageParts[1],
                    ''
                );
                $globalInstructions = $this->globalInstructionService->buildGlobalInstruction('pages', 'metadata', $pageUid);
                $globalInstructionsOverride = $this->globalInstructionService->checkOverridePredefinedPrompt('pages', 'metadata', [$pageUid]);
                $payload[] = [
                    'field_label' => $massActionData['column'],
                    'request_content' => $pageContent,
                    'uuid' => $uuid,
                    'global_instructions' => $globalInstructions,
                    'override_predefined_prompt' => $globalInstructionsOverride
                ];
            } catch (\Exception $e) {
                $this->logger->error('Error while fetching page content for page with uid ' . $pageUid . ': ' . $e->getMessage());
                $failedPages[] = $pageUid;
            }
        }
        if (count($payload) > 0) {
            $requestService = GeneralUtility::makeInstance(SendRequestService::class);
            $answer = $requestService->sendDataRequest(
                'createMassAction',
                [
                    'uuid' => $massActionData['parentUuid'],
                    'payload' => $payload,
                    'scope' => 'page',
                    'type' => 'metadata'
                ],
                '',
                $languageParts[0],
                [
                    'text' => $massActionData['textAiModel'],
                ]
            );
            if ($answer->getType() === 'Error') {
                $this->logError($answer->getResponseData()['message'], $response, 503);
                return $response;
            }
            $this->backgroundTaskRepository->insertBackgroundTasks($bulkPayload);
        }
        $response->getBody()->write(
            json_encode(
                [
                    'success' => true,
                    'output' => [
                        'message' => $this->translationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:AiSuite.massAction.processing', ['pages']),
                        'failedPages' => $failedPages,
                    ],
                ]
            )
        );
        return $response;
    }

    public function pagesUpdateAction(ServerRequestInterface $serverRequest): ResponseInterface
    {
        $response = new Response();
        try {
            $massActionData = $serverRequest->getParsedBody()['massActionPagesExecute'];
            $pages = json_decode($massActionData['pages'], true);

            $datamap = [];
            foreach ($pages as $pageUid => $pageMetadataFieldValue) {
                $datamap['pages'][$pageUid] = [
                    $massActionData['column'] => $pageMetadataFieldValue,
                ];
            }
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start($datamap, []);
            $dataHandler->process_datamap();
            if (count($dataHandler->errorLog) > 0) {
                throw new \Exception(implode(', ', $dataHandler->errorLog));
            }
            $response->getBody()->write(
                json_encode(
                    [
                        'success' => true,
                    ]
                )
            );
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            $response->getBody()->write(
                json_encode(
                    [
                        'success' => true,
                        'error' => $this->translationService->translate('AiSuite.notification.generation.pageUpdateError')
                    ]
                )
            );
        }
        return $response;
    }

    public function fileReferencesPrepareExecuteAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        try {
            $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibrariesEnumeration::METADATA, 'createMetadata', ['text']);
            if ($librariesAnswer->getType() === 'Error') {
                $response->getBody()->write(
                    json_encode(
                        [
                            'success' => true,
                            'output' => $librariesAnswer->getResponseData()['message']
                        ]
                    )
                );
                return $response;
            }
            $textGenerationLibraries = $librariesAnswer->getResponseData()['textGenerationLibraries'];
            $textGenerationLibraries = array_filter($textGenerationLibraries, function ($library) {
                return $library['name'] === 'Vision';
            });
            $params['textGenerationLibraries'] = $this->libraryService->prepareLibraries($textGenerationLibraries);
            $params['paidRequestsAvailable'] = $librariesAnswer->getResponseData()['paidRequestsAvailable'];

            $massActionData = $request->getParsedBody()['massActionFileReferencesPrepare'];
            $pageId = (int)$massActionData['startFromPid'];
            $availableLanguages = $this->siteService->getAvailableLanguages(true, $pageId);
            $currentSysLanguage = $massActionData['sysLanguage'];
            $sysLanguageToUse = $currentSysLanguage;
            $notification = '';
            $this->siteService->updateSelectedSysLanguage($availableLanguages, $sysLanguageToUse, $notification, $currentSysLanguage);

            $massActionData['sysLanguage'] = $sysLanguageToUse;
            $massActionData['showOnlyEmpty'] = (array_key_exists('showOnlyEmpty', $massActionData) && $massActionData['showOnlyEmpty']);
            $languageParts = explode('__', $massActionData['sysLanguage']);
            $foundPageUids = $this->pageRepository->getPageIdsRecursive(
                [(int)$massActionData['startFromPid']],
                (int)$massActionData['depth']
            );

            $fileReferenceMetadataColumns = $this->metadataService->getFileMetadataColumns();
            $params['column'] = $massActionData['column'];
            $params['columnName'] = $fileReferenceMetadataColumns[$massActionData['column']];

            $foundFileReferences = $this->pagesRepository->fetchSysFileReferences($foundPageUids, $massActionData['column'], (int)$languageParts[1], $massActionData['showOnlyEmpty']);
            $params['unsupportedFileReferences'] = array_filter($foundFileReferences, function ($fileReference) {
                if (!$this->metadataService->hasFilePermissions($fileReference['uid_local'])) {
                    return false;
                }
                return !in_array($fileReference['fileMimeType'], $this->supportedMimeTypes);
            });
            $params['fileReferences'] = array_filter($foundFileReferences, function ($fileReference) {
                if (!$this->metadataService->hasFilePermissions($fileReference['uid_local'])) {
                    return false;
                }
                return in_array($fileReference['fileMimeType'], $this->supportedMimeTypes);
            });
            $sysFileReferenceUids = array_column($params['fileReferences'], 'uid');
            $alreadyPendingFiles = $this->backgroundTaskRepository->fetchAlreadyPendingEntries($sysFileReferenceUids, 'sys_file_reference', $params['column']);

            $params['alreadyPendingFileReferences'] = array_reduce($alreadyPendingFiles, function ($carry, $item) {
                $carry[$item['table_uid']] = $item['status'] ?? 'pending';
                return $carry;
            }, []);

            $params['maxAllowedFileSize'] = $this->directiveService->getEffectiveMaxUploadSize();
            $params['globalInstructions'] = $this->globalInstructionService->buildGlobalInstruction('pages', 'metadata', $pageId);

            $output = $this->getContentFromTemplate(
                $request,
                'FileReferencesPrepareExecute',
                'EXT:ai_suite/Resources/Private/Templates/Ajax/MassAction/',
                'MassAction',
                $params,
                false
            );
            $response->getBody()->write(
                json_encode(
                    [
                        'success' => true,
                        'output' => [
                            'parentUuid' => $this->uuidService->generateUuid(),
                            'content' => $output,
                            'availableSysLanguages' => $availableLanguages,
                            'notification' => $notification,
                        ],
                    ]
                )
            );
        } catch (\Exception $e) {
            $this->logger->error('Error while fileReferencesPrepareExecuteAction: ' . $e->getMessage());
            $response->getBody()->write(
                json_encode(
                    [
                        'success' => false,
                        'error' => $this->translationService->translate('AiSuite.backgroundTasks.errorFileReferencesPrepareExecuteAction')
                    ]
                )
            );
        }
        return $response;
    }

    public function fileReferencesExecuteAction(ServerRequestInterface $serverRequest): ResponseInterface
    {
        $response = new Response();

        $parsedBody = $serverRequest->getParsedBody();
        if (!is_array($parsedBody) || !array_key_exists('massActionFileReferencesExecute', $parsedBody)) {
            $this->logger->error('Invalid request: empty parsedBody or missing massActionFileReferencesExecute key in fileReferencesExecuteAction');
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $this->translationService->translate('AiSuite.error.invalidRequest')
            ]));
            return $response;
        }

        $massActionData = $parsedBody['massActionFileReferencesExecute'];
        $fileReferences = json_decode($massActionData['fileReferences'], true);
        $languageParts = explode('__', $massActionData['sysLanguage']);
        $payload = [];
        $bulkPayload = [];
        $failedFileReferences = [];
        $allowedFileSize = $this->directiveService->getEffectiveMaxUploadSize();
        $fileSizeSumInBytes = 0;
        foreach ($fileReferences as $sysFileReferenceUid => $sysFileUid) {
            try {
                if ((int)$sysFileUid === 0) {
                    $fileReferenceRow = $this->sysFileReferenceRepository->findByUid((int)$sysFileReferenceUid);
                    if (count($fileReferenceRow) === 0 || !array_key_exists('uid_local', $fileReferenceRow[0])) {
                        throw new \Exception($this->translationService->translate('tx_aisuite.error.fileReference.notFound', [$sysFileReferenceUid]));
                    }
                    $sysFileUid = (int)$fileReferenceRow[0]["uid_local"];
                }
                $fileContent = $this->metadataService->getFileContent((int)$sysFileUid);
                $fileSize = strlen($fileContent);

                if (($fileSizeSumInBytes + $fileSize) >= $allowedFileSize && count($payload) > 0) {
                    $requestService = GeneralUtility::makeInstance(SendRequestService::class);
                    $answer = $requestService->sendDataRequest(
                        'createMassAction',
                        [
                            'uuid' => $massActionData['parentUuid'],
                            'payload' => $payload,
                            'scope' => 'fileReference',
                            'type' => 'metadata'
                        ],
                        '',
                        $languageParts[0],
                        [
                            'text' => $massActionData['textAiModel'],
                        ]
                    );

                    if ($answer->getType() === 'Error') {
                        $this->logError($answer->getResponseData()['message'], $response, 503);
                        return $response;
                    }
                    $this->backgroundTaskRepository->insertBackgroundTasks($bulkPayload);
                    $payload = [];
                    $bulkPayload = [];
                    $fileSizeSumInBytes = 0;
                }

                $uuid = $this->uuidService->generateUuid();
                $bulkPayload[] = new BackgroundTask(
                    'fileReference',
                    'metadata',
                    $massActionData['parentUuid'],
                    $uuid,
                    $massActionData['column'],
                    'sys_file_reference',
                    'uid',
                    $sysFileReferenceUid,
                    (int)$languageParts[1],
                    ''
                );
                $pageId = (int)$massActionData['startFromPid'];
                $globalInstructions = $this->globalInstructionService->buildGlobalInstruction('pages', 'metadata', $pageId);
                $globalInstructionsOverride = $this->globalInstructionService->checkOverridePredefinedPrompt('pages', 'metadata', [$pageId]);
                $payload[] = [
                    'field_label' => $massActionData['column'],
                    'request_content' => $fileContent,
                    'uuid' => $uuid,
                    'global_instructions' => $globalInstructions,
                    'override_predefined_prompt' => $globalInstructionsOverride
                ];
                $fileSizeSumInBytes += $fileSize;
            } catch (\Exception $e) {
                $this->logger->error('Error while fetching file content for file with sys file reference uid ' . $sysFileReferenceUid . ': ' . $e->getMessage());
                $failedFileReferences[] = $sysFileReferenceUid;
            }
        }
        if (count($payload) > 0) {
            $requestService = GeneralUtility::makeInstance(SendRequestService::class);
            $answer = $requestService->sendDataRequest(
                'createMassAction',
                [
                    'uuid' => $massActionData['parentUuid'],
                    'payload' => $payload,
                    'scope' => 'fileReference',
                    'type' => 'metadata'
                ],
                '',
                $languageParts[0],
                [
                    'text' => $massActionData['textAiModel'],
                ]
            );
            if ($answer->getType() === 'Error') {
                $this->logError($answer->getResponseData()['message'], $response, 503);
                return $response;
            }
            $this->backgroundTaskRepository->insertBackgroundTasks($bulkPayload);
        }
        $response->getBody()->write(
            json_encode(
                [
                    'success' => true,
                    'output' => [
                        'message' => $this->translationService->translate('AiSuite.massAction.processing', ['file references']),
                        'failedFileReferences' => $failedFileReferences,
                    ],
                ]
            )
        );
        return $response;
    }

    public function fileReferencesUpdateAction(ServerRequestInterface $serverRequest): ResponseInterface
    {
        $response = new Response();
        try {
            $massActionData = $serverRequest->getParsedBody()['massActionFileReferencesExecute'];
            $fileReferences = json_decode($massActionData['fileReferences'], true);

            $datamap = [];
            foreach ($fileReferences as $sysFileReferenceUid => $fileMetadataFieldValue) {
                $datamap['sys_file_reference'][$sysFileReferenceUid] = [
                    $massActionData['column'] => $fileMetadataFieldValue,
                ];
            }
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start($datamap, []);
            $dataHandler->process_datamap();
            if (count($dataHandler->errorLog) > 0) {
                throw new \Exception(implode(', ', $dataHandler->errorLog));
            }
            $response->getBody()->write(
                json_encode(
                    [
                        'success' => true,
                    ]
                )
            );
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            $response->getBody()->write(
                json_encode(
                    [
                        'success' => false,
                        'error' => $this->translationService->translate('AiSuite.notification.generation.fileReferencesUpdateError')
                    ]
                )
            );
        }
        return $response;
    }

    public function filelistFilesUpdateViewAction(ServerRequestInterface $serverRequest): ResponseInterface
    {
        $response = new Response();
        try {
            $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibrariesEnumeration::METADATA, 'createMetadata', ['text']);
            if ($librariesAnswer->getType() === 'Error') {
                $response->getBody()->write(
                    json_encode(
                        [
                            'success' => true,
                            'output' => $librariesAnswer->getResponseData()['message']
                        ]
                    )
                );
                return $response;
            }

            $viewProperties = $this->massActionService->filelistFileDirectorySupport($librariesAnswer);

            $output = $this->getContentFromTemplate(
                $serverRequest,
                'FilelistFilesViewUpdate',
                'EXT:ai_suite/Resources/Private/Templates/Ajax/MassAction/',
                'MassAction',
                $viewProperties
            );
            $response->getBody()->write(
                json_encode(
                    [
                        'success' => true,
                        'output' => [
                            'content' => $output,
                        ],
                    ]
                )
            );

        } catch (\Exception $e) {
            $this->logger->error('Error while filesPrepareExecuteAction: ' . $e->getMessage());
            $response->getBody()->write(
                json_encode(
                    [
                        'success' => false,
                        'error' => $this->translationService->translate('AiSuite.backgroundTasks.errorFilesPrepareExecuteAction')
                    ]
                )
            );
        }
        return $response;
    }

    public function filelistFilesExecuteAction(ServerRequestInterface $serverRequest): ResponseInterface
    {
        $response = new Response();

        $parsedBody = $serverRequest->getParsedBody();
        if (!is_array($parsedBody) || !array_key_exists('massActionFilesExecute', $parsedBody)) {
            $this->logger->error('Invalid request: empty parsedBody or missing massActionFilesExecute key in filelistFilesExecuteAction');
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $this->translationService->translate('AiSuite.error.invalidRequest')
            ]));
            return $response;
        }

        $massActionData = $parsedBody['massActionFilesExecute'];
        $scope = 'fileMetadata';

        $filesMetadataUidList = [];
        $files = [];
        $massActionDataFiles = json_decode($massActionData['files'], true);
        foreach ($massActionDataFiles as $sysFileMetaUid => $data) {
            $filesMetadataUidList[] = $sysFileMetaUid;
            $fileMetaData = $massActionDataFiles[$sysFileMetaUid];
            foreach ($fileMetaData as $column => $value) {
                if ($massActionData['column'] === $column || $massActionData['column'] === 'all') {
                    $files[$sysFileMetaUid][$column] = $value;
                }
            }
        }
        $metadataListFromRepo = [];
        if (count($filesMetadataUidList) > 0) {
            $metadataListFromRepo = $this->sysFileMetadataRepository->findByUidList($filesMetadataUidList);
        }
        $languageParts = explode('__', $massActionData['sysLanguage']);
        $payload = [];
        $bulkPayload = [];
        $failedFilesMetadata = [];
        $allowedFileSize = $this->directiveService->getEffectiveMaxUploadSize();
        $fileSizeSumInBytes = 0;
        foreach ($files as $sysFileMetaUid => $columns) {
            foreach ($columns as $column => $value) {
                try {
                    if ($column === 'mode') {
                        continue;
                    }
                    $fileUid = (int)$metadataListFromRepo[$sysFileMetaUid]['file'];
                    $defaultSysFileMetaUid = (int)$sysFileMetaUid;
                    $targetLanguageId = (int)$languageParts[1];

                    $fileContent = $this->metadataService->getFileContent($fileUid);
                    $fileSize = strlen($fileContent);

                    if (($fileSizeSumInBytes + $fileSize) >= $allowedFileSize && count($payload) > 0) {
                        $requestService = GeneralUtility::makeInstance(SendRequestService::class);
                        $answer = $requestService->sendDataRequest(
                            'createMassAction',
                            [
                                'uuid' => $massActionData['parentUuid'],
                                'payload' => $payload,
                                'scope' => $scope,
                                'type' => 'metadata'
                            ],
                            '',
                            $languageParts[0],
                            [
                                'text' => $massActionData['textAiModel'],
                            ]
                        );

                        if ($answer->getType() === 'Error') {
                            $this->logError($answer->getResponseData()['message'], $response, 503);
                            return $response;
                        }
                        $this->backgroundTaskRepository->insertBackgroundTasks($bulkPayload);
                        $payload = [];
                        $bulkPayload = [];
                        $fileSizeSumInBytes = 0;
                    }

                    $uuid = $this->uuidService->generateUuid();

                    $bulkPayload[] = new BackgroundTask(
                        $scope,
                        'metadata',
                        $massActionData['parentUuid'],
                        $uuid,
                        $column,
                        'sys_file_metadata',
                        'uid',
                        $defaultSysFileMetaUid,
                        $targetLanguageId,
                        $columns['mode'] ?? ''
                    );
                    $folderCombinedIdentifier = $this->massActionService->getFolderCombinedIdentifier($fileUid);
                    $globalInstructions = $this->globalInstructionService->buildGlobalInstruction('files', 'metadata', null, $folderCombinedIdentifier);
                    $globalInstructionsOverride = $this->globalInstructionService->checkOverridePredefinedPrompt('files', 'metadata', [$folderCombinedIdentifier]);
                    $payload[] = [
                        'field_label' => $column,
                        'request_content' => $fileContent,
                        'uuid' => $uuid,
                        'global_instructions' => $globalInstructions,
                        'override_predefined_prompt' => $globalInstructionsOverride
                    ];
                    $fileSizeSumInBytes += $fileSize;
                } catch (\Exception $e) {
                    $this->logger->error('Error while fetching file content for file ' . $fileUid . ' with sys file metadata uid ' . $sysFileMetaUid . ': ' . $e->getMessage());
                    $failedFilesMetadata[] = $fileUid;
                }
            }
        }
        if (count($payload) > 0) {
            $requestService = GeneralUtility::makeInstance(SendRequestService::class);
            $answer = $requestService->sendDataRequest(
                'createMassAction',
                [
                    'uuid' => $massActionData['parentUuid'],
                    'payload' => $payload,
                    'scope' => $scope,
                    'type' => 'metadata'
                ],
                '',
                $languageParts[0],
                [
                    'text' => $massActionData['textAiModel'],
                ]
            );
            if ($answer->getType() === 'Error') {
                $this->logError($answer->getResponseData()['message'], $response, 503);
                return $response;
            }
            $this->backgroundTaskRepository->insertBackgroundTasks($bulkPayload);
        }
        $response->getBody()->write(
            json_encode(
                [
                    'success' => true,
                    'output' => [
                        'message' => $this->translationService->translate('AiSuite.massAction.processing', ['files']),
                        'failedFiles' => $failedFilesMetadata,
                    ],
                ]
            )
        );
        return $response;
    }

    public function filelistFilesUpdateAction(ServerRequestInterface $serverRequest): ResponseInterface
    {
        $response = new Response();
        try {
            $massActionData = $serverRequest->getParsedBody();
            $datamap = [];
            $fileSelection = array_key_exists('file-selection', $massActionData) ? json_decode($massActionData['file-selection'], true) : [];
            if (!is_array($fileSelection) || count($fileSelection) === 0) {
                $response->getBody()->write(
                    json_encode(
                        [
                            'success' => false,
                            'error' => $this->translationService->translate('AiSuite.notification.generation.fileUpdateError.noFileSelected')
                        ]
                    )
                );
                return $response;
            }

            foreach ($fileSelection as $sysFileUid => $fields) {
                $sysFileUid = (int)$sysFileUid;
                $fileMetaData = $fields;
                foreach ($fileMetaData as $column => $value) {
                    if ($massActionData['options']['column'] === $column || $massActionData['options']['column'] === 'all') {
                        $datamap['sys_file_metadata'][$sysFileUid][$column] = $value;
                    }
                }
            }
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start($datamap, []);
            $dataHandler->process_datamap();
            if (count($dataHandler->errorLog) > 0) {
                throw new \Exception(implode(', ', $dataHandler->errorLog));
            }
            $response->getBody()->write(
                json_encode(
                    [
                        'success' => true,
                    ]
                )
            );
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            $response->getBody()->write(
                json_encode(
                    [
                        'success' => false,
                        'error' => $this->translationService->translate('AiSuite.notification.generation.fileUpdateError')
                    ]
                )
            );
        }
        return $response;
    }

    public function pagesTranslationPrepareExecuteAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        try {
            $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibrariesEnumeration::TRANSLATE, 'translate', ['text']);
            if ($librariesAnswer->getType() === 'Error') {
                $response->getBody()->write(
                    json_encode([
                        'success' => false,
                        'output' => $librariesAnswer->getResponseData()['message']
                    ])
                );
                return $response;
            }

            $textTranslationLibraries = $librariesAnswer->getResponseData()['textGenerationLibraries'];
            $params['textTranslationLibraries'] = $this->libraryService->prepareLibraries($textTranslationLibraries);
            $params['paidRequestsAvailable'] = $librariesAnswer->getResponseData()['paidRequestsAvailable'];

            $massActionData = $request->getParsedBody()['massActionPagesTranslationPrepare'];
            $pageId = (int)$massActionData['startFromPid'];
            $availableSourceLanguages = $this->siteService->getAvailableLanguages(true, $pageId, true);
            $currentSourceLanguage = $massActionData['sourceLanguage'];
            $sourceLanguageToUse = $currentSourceLanguage;
            $notificationSourceLanguage = '';
            $this->siteService->updateSelectedSysLanguage($availableSourceLanguages, $sourceLanguageToUse, $notificationSourceLanguage, $currentSourceLanguage, 'sourceLanguage');
            $massActionData['sourceLanguage'] = $sourceLanguageToUse;

            $availableTargetLanguages = $this->siteService->getAvailableLanguages(true, $pageId);
            $currentTargetLanguage = $massActionData['targetLanguage'];
            $targetLanguageToUse = $currentTargetLanguage;
            $notificationTargetLanguage = '';
            $this->siteService->updateSelectedSysLanguage($availableTargetLanguages, $targetLanguageToUse, $notificationTargetLanguage, $currentTargetLanguage, 'targetLanguage');
            $massActionData['targetLanguage'] = $targetLanguageToUse;

            $sourceLanguageParts = explode('__', $massActionData['sourceLanguage']);
            $targetLanguageParts = explode('__', $massActionData['targetLanguage']);

            $foundPageUids = $this->pageRepository->getPageIdsRecursive(
                [(int)$massActionData['startFromPid']],
                (int)$massActionData['depth']
            );

            $params['sourceLanguage'] = $massActionData['sourceLanguage'];
            $params['targetLanguage'] = $massActionData['targetLanguage'];
            $params['translationScope'] = $massActionData['translationScope'];
            $params['pages'] = $this->pagesRepository->fetchPagesForTranslation($foundPageUids, (int)$sourceLanguageParts[1], (int)$targetLanguageParts[1], $massActionData);

            $pagesUids = array_column($params['pages'], 'uid');
            if (count($pagesUids) > 0) {
                $alreadyPendingPages = $this->backgroundTaskRepository->fetchAlreadyPendingEntriesForTranslation($pagesUids, 'pages', (int)$targetLanguageParts[1]);
                $params['alreadyPendingPages'] = array_reduce($alreadyPendingPages, function ($carry, $item) {
                    $carry[$item['table_uid']] = $item['status'];
                    return $carry;
                }, []);
            }

            $output = $this->getContentFromTemplate(
                $request,
                'PagesTranslationPrepareExecute',
                'EXT:ai_suite/Resources/Private/Templates/Ajax/MassAction/',
                'MassAction',
                $params
            );

            $response->getBody()->write(
                json_encode([
                    'success' => true,
                    'output' => [
                        'parentUuid' => $this->uuidService->generateUuid(),
                        'content' => $output,
                        'availableSourceLanguages' => $availableSourceLanguages,
                        'availableTargetLanguages' => $availableTargetLanguages,
                        'notificationSourceLanguage' => $notificationSourceLanguage,
                        'notificationTargetLanguage' => $notificationTargetLanguage,
                    ],
                ])
            );
        } catch (\Exception $e) {
            $this->logger->error('Error while pagesTranslationPrepareExecuteAction: ' . $e->getMessage());
            $response->getBody()->write(
                json_encode([
                    'success' => false,
                    'error' => $this->translationService->translate('AiSuite.backgroundTasks.errorPagesTranslationPrepareExecuteAction')
                ])
            );
        }
        return $response;
    }

    public function pagesTranslationExecuteAction(ServerRequestInterface $serverRequest): ResponseInterface
    {
        $response = new Response();
        $massActionData = $serverRequest->getParsedBody()['massActionPagesTranslationExecute'];
        $pages = json_decode($massActionData['pages'], true);
        $sourceLanguageParts = explode('__', $massActionData['sourceLanguage']);
        $targetLanguageParts = explode('__', $massActionData['targetLanguage']);

        $payload = [];
        $bulkPayload = [];
        $failedPages = [];

        foreach ($pages as $pageUid => $pageData) {
            try {
                $translatableContent = $this->translationService->collectPageTranslatableContent(
                    (int)$pageUid,
                    (int)$sourceLanguageParts[1],
                    $massActionData['translationScope'],
                    (int)$targetLanguageParts[1]
                );

                if (empty($translatableContent)) {
                    $failedPages[] = $pageUid;
                    continue;
                }

                $uuid = $this->uuidService->generateUuid();
                $bulkPayload[] = new BackgroundTask(
                    'page-translation',
                    'translation',
                    $massActionData['parentUuid'],
                    $uuid,
                    $massActionData['translationScope'],
                    'pages',
                    'uid',
                    $pageUid,
                    (int)$targetLanguageParts[1],
                    ''
                );
                $payload[] = [
                    'source_page_uid' => $pageUid,
                    'source_language' => $sourceLanguageParts[0],
                    'target_language' => $targetLanguageParts[0],
                    'translation_scope' => $massActionData['translationScope'],
                    'translatable_content' => $translatableContent,
                    'uuid' => $uuid,
                ];
            } catch (\Exception $e) {
                $this->logger->error('Error while collecting translatable content for page ' . $pageUid . ': ' . $e->getMessage());
                $failedPages[] = $pageUid;
            }
        }

        if (count($payload) > 0) {
            $requestService = GeneralUtility::makeInstance(SendRequestService::class);
            $answer = $requestService->sendDataRequest(
                'createMassAction',
                [
                    'uuid' => $massActionData['parentUuid'],
                    'payload' => $payload,
                    'scope' => 'page-translation',
                    'type' => 'translation'
                ],
                '',
                '',
                [
                    'translate' => $massActionData['textAiModel']
                ]
            );

            if ($answer->getType() === 'Error') {
                $this->logError($answer->getResponseData()['message'], $response, 503);
                return $response;
            }
            $this->backgroundTaskRepository->insertBackgroundTasks($bulkPayload);
        }

        $response->getBody()->write(
            json_encode([
                'success' => true,
                'output' => [
                    'message' => $this->translationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:AiSuite.massAction.translation.processing', ['pages']),
                    'failedPages' => $failedPages,
                ],
            ])
        );
        return $response;
    }


}
