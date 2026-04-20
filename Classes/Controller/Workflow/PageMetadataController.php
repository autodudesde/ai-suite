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
use AutoDudes\AiSuite\Domain\Repository\PagesRepository;
use AutoDudes\AiSuite\Enumeration\GenerationLibraryEnumeration;
use AutoDudes\AiSuite\Service\AiSuiteContext;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\TranslationService;
use AutoDudes\AiSuite\Service\ViewFactoryService;
use AutoDudes\AiSuite\Service\WorkflowProcessingService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\PreviewUriBuilder;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;

#[AsController]
class PageMetadataController extends AbstractBackendController
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
        protected readonly LoggerInterface $logger,
        protected readonly PageRepository $pageRepository,
        protected readonly PagesRepository $pagesRepository,
        protected readonly BackgroundTaskRepository $backgroundTaskRepository,
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

    public function pagesPrepareExecuteAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();

        try {
            $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibraryEnumeration::METADATA, 'createMetadata', ['text']);
            if ('Error' === $librariesAnswer->getType()) {
                return $this->jsonError($response, $librariesAnswer->getResponseData()['message']);
            }
            $textGenerationLibraries = $librariesAnswer->getResponseData()['textGenerationLibraries'];
            $textGenerationLibraries = $this->aiSuiteContext->libraryService->filterNonVisionLibraries($textGenerationLibraries);
            $params['textGenerationLibraries'] = $this->aiSuiteContext->libraryService->prepareLibraries($textGenerationLibraries);
            $params['paidRequestsAvailable'] = $librariesAnswer->getResponseData()['paidRequestsAvailable'];

            $workflowData = ((array) $request->getParsedBody())['workflowPagesPrepare'];
            $pageId = (int) $workflowData['startFromPid'];
            $availableLanguages = $this->aiSuiteContext->siteService->getAvailableLanguages(true, $pageId);
            $currentSysLanguage = $workflowData['sysLanguage'];
            $sysLanguageToUse = $currentSysLanguage;
            $notification = '';
            $this->aiSuiteContext->siteService->updateSelectedSysLanguage($availableLanguages, $sysLanguageToUse, $notification, $currentSysLanguage);

            $workflowData['sysLanguage'] = $sysLanguageToUse;
            $workflowData['showOnlyEmpty'] = (array_key_exists('showOnlyEmpty', $workflowData) && $workflowData['showOnlyEmpty']);
            $foundPageUids = $this->pageRepository->getPageIdsRecursive(
                [(int) $workflowData['startFromPid']],
                (int) $workflowData['depth']
            );
            $pageMetadataColumns = $this->aiSuiteContext->metadataService->getMetadataColumns();
            $params['column'] = $workflowData['column'];
            $params['columnName'] = $pageMetadataColumns[$workflowData['column']];
            $params['pages'] = $this->pagesRepository->fetchNecessaryPageData($workflowData, array_values($foundPageUids));
            $pagesUids = array_column($params['pages'], 'uid');
            if (count($pagesUids) > 0) {
                $alreadyPendingPages = $this->backgroundTaskRepository->fetchAlreadyPendingEntries($pagesUids, 'pages', $params['column']);

                $params['alreadyPendingPages'] = array_reduce($alreadyPendingPages, function ($carry, $item) {
                    $carry[$item['table_uid']] = $item['status'] ?? 'pending';

                    return $carry;
                }, []);
            }
            $params['globalInstructions'] = $this->aiSuiteContext->globalInstructionService->buildGlobalInstruction('pages', 'metadata', $pageId);

            $output = $this->viewFactoryService->renderTemplate(
                $request,
                'PagesPrepareExecute',
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
            $this->logger->error('Error while pagesPrepareExecuteAction: '.$e->getMessage());

            return $this->jsonError($response, $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.backgroundTask.errorPagesPrepareExecuteAction'));
        }
    }

    public function pagesExecuteAction(ServerRequestInterface $serverRequest): ResponseInterface
    {
        $response = new Response();

        $workflowData = $this->validateParsedBody($serverRequest, 'workflowPagesExecute', $response);
        if ($workflowData instanceof Response) {
            return $workflowData;
        }

        $pages = json_decode($workflowData['pages'], true);
        $languageParts = explode('__', $workflowData['sysLanguage']);

        $contentFetcher = function (int $pageUid, int $languageId): string {
            $page = $this->pageRepository->getPage($pageUid);
            $previewUriPageId = $pageUid;
            if (1 === $page['is_siteroot'] && $page['l10n_parent'] > 0) {
                $previewUriPageId = $page['l10n_parent'];
            }
            $previewUri = PreviewUriBuilder::create($previewUriPageId)
                ->withLanguage($languageId)
                ->buildUri()
            ;
            if (null === $previewUri) {
                return '';
            }
            $url = $this->aiSuiteContext->siteService->buildAbsoluteUri($previewUri);

            return $this->aiSuiteContext->metadataService->fetchContentFromUrl($url);
        };

        $result = $this->workflowProcessingService->processPageMetadataGeneration(
            $workflowData,
            $pages,
            $languageParts,
            $contentFetcher,
            $this->requestService,
        );

        $payload = $result['payload'];
        $bulkPayload = $result['bulkPayload'];
        $failedPages = $result['failedPages'];

        $errorResponse = $this->sendWorkflowRequest(
            $payload,
            $bulkPayload,
            $workflowData['parentUuid'],
            'page',
            'metadata',
            $languageParts[0],
            'text',
            $workflowData['textAiModel'],
            $response,
            $this->requestService,
            $this->backgroundTaskRepository,
        );
        if (null !== $errorResponse) {
            return $errorResponse;
        }

        return $this->jsonSuccess($response, [
            'message' => $this->aiSuiteContext->localizationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang_module.xlf:aiSuite.module.workflow.processing', ['pages']),
            'failedPages' => $failedPages,
        ]);
    }

    public function pagesUpdateAction(ServerRequestInterface $serverRequest): ResponseInterface
    {
        $response = new Response();

        try {
            $workflowData = ((array) $serverRequest->getParsedBody())['workflowPagesExecute'];
            $pages = json_decode($workflowData['pages'], true);

            $datamap = [];
            foreach ($pages as $pageUid => $pageMetadataFieldValue) {
                $datamap['pages'][$pageUid] = [
                    $workflowData['column'] => $pageMetadataFieldValue,
                ];
            }
            $this->executeDataHandler($datamap);

            return $this->jsonSuccess($response);
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());

            return $this->jsonError($response, $this->aiSuiteContext->localizationService->translate('aiSuite.notification.generation.pageUpdateError'));
        }
    }
}
