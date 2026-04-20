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
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;

#[AsController]
class PageTranslationController extends AbstractBackendController
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

    public function pagesTranslationPrepareExecuteAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();

        try {
            $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibraryEnumeration::TRANSLATE, 'translate', ['text']);
            if ('Error' === $librariesAnswer->getType()) {
                return $this->jsonError($response, $librariesAnswer->getResponseData()['message']);
            }

            $textTranslationLibraries = $librariesAnswer->getResponseData()['textGenerationLibraries'];
            $params['textTranslationLibraries'] = $this->aiSuiteContext->libraryService->prepareLibraries($textTranslationLibraries);
            $params['paidRequestsAvailable'] = $librariesAnswer->getResponseData()['paidRequestsAvailable'];

            $workflowData = ((array) $request->getParsedBody())['workflowPagesTranslationPrepare'];
            $pageId = (int) $workflowData['startFromPid'];
            $availableSourceLanguages = $this->aiSuiteContext->siteService->getAvailableLanguages(true, $pageId, true);
            $currentSourceLanguage = $workflowData['sourceLanguage'];
            $sourceLanguageToUse = $currentSourceLanguage;
            $notificationSourceLanguage = '';
            $this->aiSuiteContext->siteService->updateSelectedSysLanguage($availableSourceLanguages, $sourceLanguageToUse, $notificationSourceLanguage, $currentSourceLanguage, 'sourceLanguage');
            $workflowData['sourceLanguage'] = $sourceLanguageToUse;

            $availableTargetLanguages = $this->aiSuiteContext->siteService->getAvailableLanguages(true, $pageId);
            $currentTargetLanguage = $workflowData['targetLanguage'];
            $targetLanguageToUse = $currentTargetLanguage;
            $notificationTargetLanguage = '';
            $this->aiSuiteContext->siteService->updateSelectedSysLanguage($availableTargetLanguages, $targetLanguageToUse, $notificationTargetLanguage, $currentTargetLanguage, 'targetLanguage');
            $workflowData['targetLanguage'] = $targetLanguageToUse;

            $sourceLanguageParts = explode('__', $workflowData['sourceLanguage']);
            $targetLanguageParts = explode('__', $workflowData['targetLanguage']);

            $foundPageUids = $this->pageRepository->getPageIdsRecursive(
                [(int) $workflowData['startFromPid']],
                (int) $workflowData['depth']
            );

            $params['sourceLanguage'] = $workflowData['sourceLanguage'];
            $params['targetLanguage'] = $workflowData['targetLanguage'];
            $params['translationScope'] = $workflowData['translationScope'];
            $params['pages'] = $this->pagesRepository->fetchPagesForTranslation(array_values($foundPageUids), (int) $sourceLanguageParts[1], (int) $targetLanguageParts[1], $workflowData);

            $pagesUids = array_column($params['pages'], 'uid');
            if (count($pagesUids) > 0) {
                $alreadyPendingPages = $this->backgroundTaskRepository->fetchAlreadyPendingEntriesForTranslation($pagesUids, 'pages', (int) $targetLanguageParts[1]);
                $params['alreadyPendingPages'] = array_column($alreadyPendingPages, 'status', 'table_uid');
            }

            $params['globalInstructions'] = $this->aiSuiteContext->globalInstructionService->buildGlobalInstruction('pages', 'translation', $pageId);

            $output = $this->viewFactoryService->renderTemplate(
                $request,
                'PagesTranslationPrepareExecute',
                'EXT:ai_suite/Resources/Private/Templates/Ajax/Workflow/',
                $params
            );

            return $this->jsonSuccess($response, [
                'parentUuid' => $this->aiSuiteContext->uuidService->generateUuid(),
                'content' => $output,
                'availableSourceLanguages' => $availableSourceLanguages,
                'availableTargetLanguages' => $availableTargetLanguages,
                'notificationSourceLanguage' => $notificationSourceLanguage,
                'notificationTargetLanguage' => $notificationTargetLanguage,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error while pagesTranslationPrepareExecuteAction: '.$e->getMessage());

            return $this->jsonError($response, $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.backgroundTask.errorPagesTranslationPrepareExecuteAction'));
        }
    }

    public function pagesTranslationExecuteAction(ServerRequestInterface $serverRequest): ResponseInterface
    {
        $response = new Response();
        $workflowData = ((array) $serverRequest->getParsedBody())['workflowPagesTranslationExecute'];
        $pages = json_decode($workflowData['pages'], true);
        $sourceLanguageParts = explode('__', $workflowData['sourceLanguage']);
        $targetLanguageParts = explode('__', $workflowData['targetLanguage']);

        $result = $this->workflowProcessingService->processPageTranslation(
            $pages,
            $workflowData['parentUuid'],
            $workflowData['translationScope'],
            $sourceLanguageParts[0],
            $targetLanguageParts[0],
            (int) $sourceLanguageParts[1],
            (int) $targetLanguageParts[1],
        );

        $payload = $result['payload'];
        $bulkPayload = $result['bulkPayload'];
        $failedPages = $result['failedPages'];

        $errorResponse = $this->sendWorkflowRequest(
            $payload,
            $bulkPayload,
            $workflowData['parentUuid'],
            'page-translation',
            'translation',
            '',
            'translate',
            $workflowData['textAiModel'],
            $response,
            $this->requestService,
            $this->backgroundTaskRepository,
        );

        if (null !== $errorResponse) {
            return $errorResponse;
        }

        return $this->jsonSuccess($response, [
            'message' => $this->aiSuiteContext->localizationService->translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang_module.xlf:aiSuite.module.workflow.translation.processing', ['pages']),
            'failedPages' => $failedPages,
        ]);
    }

    public function pagesTranslationApplyAction(ServerRequestInterface $serverRequest): ResponseInterface
    {
        return new Response();
    }

    public function pagesTranslationRetryAction(ServerRequestInterface $serverRequest): ResponseInterface
    {
        return new Response();
    }
}
