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
use AutoDudes\AiSuite\Domain\Repository\BackgroundTaskRepository;
use AutoDudes\AiSuite\Domain\Repository\SysFileMetadataRepository;
use AutoDudes\AiSuite\Enumeration\GenerationLibraryEnumeration;
use AutoDudes\AiSuite\Service\AiSuiteContext;
use AutoDudes\AiSuite\Service\GlossarService;
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
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;

#[AsController]
class FileTranslationController extends AbstractBackendController
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
        protected readonly BackgroundTaskRepository $backgroundTaskRepository,
        protected readonly SysFileMetadataRepository $sysFileMetadataRepository,
        protected readonly GlossarService $glossarService,
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

    public function filelistFilesTranslateUpdateViewAction(ServerRequestInterface $serverRequest): ResponseInterface
    {
        $response = new Response();

        try {
            $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibraryEnumeration::TRANSLATE, 'translate', ['text']);
            if ('Error' === $librariesAnswer->getType()) {
                return $this->jsonError($response, $librariesAnswer->getResponseData()['message']);
            }

            $viewProperties = $this->workflowViewService->filelistFileTranslationDirectorySupport($librariesAnswer);

            $output = $this->viewFactoryService->renderTemplate(
                $serverRequest,
                'FilelistFilesTranslateViewUpdate',
                'EXT:ai_suite/Resources/Private/Templates/Ajax/Workflow/',
                $viewProperties
            );

            return $this->jsonSuccess($response, [
                'content' => $output,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error while filelistFilesTranslateUpdateViewAction: '.$e->getMessage());

            return $this->jsonError($response, $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.backgroundTask.errorFilesPrepareExecuteAction'));
        }
    }

    public function filelistFilesTranslateExecuteAction(ServerRequestInterface $serverRequest): ResponseInterface
    {
        $response = new Response();

        $workflowData = $this->validateParsedBody($serverRequest, 'workflowFilesTranslationExecute', $response);
        if ($workflowData instanceof Response) {
            return $workflowData;
        }

        $filesMetadataUidList = [];
        $files = [];
        $workflowDataFiles = json_decode($workflowData['files'], true);
        foreach ($workflowDataFiles as $sysFileMetaUid => $data) {
            $filesMetadataUidList[] = $sysFileMetaUid;
            $fileMetaData = $workflowDataFiles[$sysFileMetaUid];
            foreach ($fileMetaData as $column => $value) {
                if ($workflowData['column'] === $column || 'all' === $workflowData['column']) {
                    $files[$sysFileMetaUid][$column] = $value;
                }
            }
            $files[$sysFileMetaUid]['mode'] = $fileMetaData['mode'];
        }

        $metadataListFromRepo = [];
        if (count($filesMetadataUidList) > 0) {
            $metadataListFromRepo = $this->sysFileMetadataRepository->findByUidList($filesMetadataUidList);
        }

        $sourceLanguageParts = explode('__', $workflowData['sourceLanguage']);
        $targetLanguageParts = explode('__', $workflowData['targetLanguage']);

        $result = $this->workflowProcessingService->processFileMetadataTranslation(
            $files,
            $metadataListFromRepo,
            $workflowData['parentUuid'],
            $sourceLanguageParts[0],
            $targetLanguageParts[0],
            (int) $targetLanguageParts[1],
        );

        $payload = $result['payload'];
        $bulkPayload = $result['bulkPayload'];
        $failedFilesMetadata = $result['failedFilesMetadata'];
        $translatableContentForGlossary = $result['translatableContentForGlossary'];

        $glossarEntries = [];
        $deeplGlossary = [];
        if (!empty($workflowData['glossary'])) {
            $glossaryParts = explode('__', $workflowData['glossary']);
            if (3 === count($glossaryParts)) {
                $rootPageId = (int) $glossaryParts[0];
                $sourceLanguageId = (int) $glossaryParts[1];
                $targetLanguageId = (int) $glossaryParts[2];

                $translatableContent = (string) json_encode($translatableContentForGlossary, SendRequestService::JSON_SAFE_FLAGS);
                $glossarEntries = $this->glossarService->findGlossarEntries($translatableContent, $targetLanguageId, $sourceLanguageId);
                $deeplGlossary = $this->glossarService->findDeeplGlossary($rootPageId, $sourceLanguageId, $targetLanguageId);
            }
        }

        $extraParams = [
            'glossary' => json_encode($glossarEntries, SendRequestService::JSON_SAFE_FLAGS),
            'deepl_glossary_id' => $deeplGlossary['glossar_uuid'] ?? '',
        ];

        $errorResponse = $this->sendWorkflowRequest(
            $payload,
            $bulkPayload,
            $workflowData['parentUuid'],
            'metadata',
            'translation',
            '',
            'translate',
            $workflowData['textAiModel'],
            $response,
            $this->requestService,
            $this->backgroundTaskRepository,
            $extraParams,
        );

        if (null !== $errorResponse) {
            return $errorResponse;
        }

        return $this->jsonSuccess($response, [
            'message' => $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.workflow.processing', ['files']),
            'failedFiles' => $failedFilesMetadata,
        ]);
    }
}
