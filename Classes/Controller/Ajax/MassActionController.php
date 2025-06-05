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
        LibraryService $libraryService,
        UuidService $uuidService,
        SiteService $siteService,
        TranslationService $translationService,
        LoggerInterface $logger,
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
            $libraryService,
            $uuidService,
            $siteService,
            $translationService,
            $logger
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
            $textGenerationLibraries = array_filter($textGenerationLibraries, function($library) {
                return $library['name'] !== 'Vision';
            });
            $params['textGenerationLibraries'] = $this->libraryService->prepareLibraries($textGenerationLibraries);
            $params['paidRequestsAvailable'] = $librariesAnswer->getResponseData()['paidRequestsAvailable'];

            $massActionData = $request->getParsedBody()['massActionPagesPrepare'];
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
            if(count($pagesUids) > 0) {
                $alreadyPendingPages = $this->backgroundTaskRepository->fetchAlreadyPendingEntries($pagesUids, 'pages', $params['column']);

                $params['alreadyPendingPages'] = array_reduce($alreadyPendingPages, function($carry, $item) {
                    $carry[$item['table_uid']] = $item['status'];
                    return $carry;
                }, []);
            }

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

    public function pagesExecuteAction(ServerRequestInterface $serverRequest): ResponseInterface {
        $response = new Response();
        $massActionData = $serverRequest->getParsedBody()['massActionPagesExecute'];
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
                    (int)$languageParts[1]
                );
                $payload[] = [
                    'field_label' => $massActionData['column'],
                    'request_content' => $pageContent,
                    'uuid' => $uuid,
                ];
            } catch (\Exception $e) {
                $this->logger->error('Error while fetching page content for page with uid ' . $pageUid . ': ' . $e->getMessage());
                $failedPages[] = $pageUid;
            }
        }
        if(count($payload) > 0) {
            $answer = $this->requestService->sendDataRequest(
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

    public function pagesUpdateAction(ServerRequestInterface $serverRequest): ResponseInterface {
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
            if(count($dataHandler->errorLog) > 0) {
                throw new \Exception(implode(', ', $dataHandler->errorLog), 3608910312);
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
            $textGenerationLibraries = array_filter($textGenerationLibraries, function($library) {
                return $library['name'] === 'Vision';
            });
            $params['textGenerationLibraries'] = $this->libraryService->prepareLibraries($textGenerationLibraries);
            $params['paidRequestsAvailable'] = $librariesAnswer->getResponseData()['paidRequestsAvailable'];

            $massActionData = $request->getParsedBody()['massActionFileReferencesPrepare'];
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
            $params['unsupportedFileReferences'] = array_filter($foundFileReferences, function($fileReference) {
                return !in_array($fileReference['fileMimeType'], $this->supportedMimeTypes);
            });
            $params['fileReferences'] = array_filter($foundFileReferences, function($fileReference) {
                return in_array($fileReference['fileMimeType'], $this->supportedMimeTypes);
            });
            $sysFileReferenceUids = array_column($params['fileReferences'], 'uid');
            $alreadyPendingFiles = $this->backgroundTaskRepository->fetchAlreadyPendingEntries($sysFileReferenceUids, 'sys_file_reference', $params['column']);

            $params['alreadyPendingFileReferences'] = array_reduce($alreadyPendingFiles, function($carry, $item) {
                $carry[$item['table_uid']] = $item['status'];
                return $carry;
            }, []);

            $params['maxAllowedFileSize'] = $this->directiveService->getEffectiveMaxUploadSize();

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

    public function fileReferencesExecuteAction(ServerRequestInterface $serverRequest): ResponseInterface {
        $response = new Response();
        $massActionData = $serverRequest->getParsedBody()['massActionFileReferencesExecute'];
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
                        throw new \Exception('No file reference row found for sys file reference uid ' . $sysFileReferenceUid . '. It seems that the file reference was deleted in the meanwhile or is are some other inconsistency with the database.', 2506481516);
                    }
                    $sysFileUid = (int)$fileReferenceRow[0]["uid_local"];
                }
                $fileContent = $this->metadataService->getFileContent((int)$sysFileUid);
                $fileSize = strlen($fileContent);

                if (($fileSizeSumInBytes + $fileSize) >= $allowedFileSize && count($payload) > 0) {
                    $answer = $this->requestService->sendDataRequest(
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
                    (int)$languageParts[1]
                );
                $payload[] = [
                    'field_label' => $massActionData['column'],
                    'request_content' => $fileContent,
                    'uuid' => $uuid,
                ];
                $fileSizeSumInBytes += $fileSize;
            } catch (\Exception $e) {
                $this->logger->error('Error while fetching file content for file with sys file reference uid ' . $sysFileReferenceUid . ': ' . $e->getMessage());
                $failedFileReferences[] = $sysFileReferenceUid;
            }
        }
        if(count($payload) > 0) {
            $answer = $this->requestService->sendDataRequest(
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

    public function fileReferencesUpdateAction(ServerRequestInterface $serverRequest): ResponseInterface {
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
            if(count($dataHandler->errorLog) > 0) {
                throw new \Exception(implode(', ', $dataHandler->errorLog), 9208016106);
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

    public function filelistFilesUpdateViewAction(ServerRequestInterface $serverRequest): ResponseInterface {
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

    public function filelistFilesExecuteAction(ServerRequestInterface $serverRequest): ResponseInterface {
        $response = new Response();
        $massActionData = $serverRequest->getParsedBody()['massActionFilesExecute'];
        $scope = 'fileMetadata';

        $filesMetadataUidList = [];
        $files = [];
        $massActionDataFiles = json_decode($massActionData['files'], true);
        foreach ($massActionDataFiles as $sysFileMetaUid => $v) {
            $sysFileMetaUid = (int)$sysFileMetaUid;
            $filesMetadataUidList[] = $sysFileMetaUid;
            $fileMetaData = $massActionDataFiles[$sysFileMetaUid];
            foreach ($fileMetaData as $column => $value) {
                if ($massActionData['column'] === $column || $massActionData['column'] === 'all') {
                    $files[$sysFileMetaUid][$column] = $value;
                }
            }
        }
        $metadataListFromRepo = $this->sysFileMetadataRepository->findByUidList($filesMetadataUidList);
        $languageParts = explode('__', $massActionData['sysLanguage']);
        $payload = [];
        $bulkPayload = [];
        $failedFilesMetadata = [];
        $allowedFileSize = $this->directiveService->getEffectiveMaxUploadSize();
        $fileSizeSumInBytes = 0;
        foreach ($files as $sysFileMetaUid => $columns) {
            foreach ($columns as $column => $value) {
                try {
                    $fileUid = (int)$metadataListFromRepo[$sysFileMetaUid]['file'];
                    $fileContent = $this->metadataService->getFileContent($fileUid);
                    $fileSize = strlen($fileContent);

                    if (($fileSizeSumInBytes + $fileSize) >= $allowedFileSize && count($payload) > 0) {
                        $answer = $this->requestService->sendDataRequest(
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
                        $sysFileMetaUid,
                        (int)$languageParts[1]
                    );
                    $payload[] = [
                        'field_label' => $column,
                        'request_content' => $fileContent,
                        'uuid' => $uuid,
                    ];
                    $fileSizeSumInBytes += $fileSize;
                } catch (\Exception $e) {
                    $this->logger->error('Error while fetching file content for file ' . $fileUid . ' with sys file metadata uid ' . $sysFileMetaUid . ': ' . $e->getMessage());
                    $failedFilesMetadata[] = $fileUid;
                }
            }
        }
        if(count($payload) > 0) {
            $answer = $this->requestService->sendDataRequest(
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

    public function filelistFilesUpdateAction(ServerRequestInterface $serverRequest): ResponseInterface {
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
            if(count($dataHandler->errorLog) > 0) {
                throw new \Exception(implode(', ', $dataHandler->errorLog), 6006998867);
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
}
