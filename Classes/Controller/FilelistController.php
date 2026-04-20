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

namespace AutoDudes\AiSuite\Controller;

use AutoDudes\AiSuite\Enumeration\GenerationLibraryEnumeration;
use AutoDudes\AiSuite\Service\AiSuiteContext;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\TranslationService;
use AutoDudes\AiSuite\Service\WorkflowViewService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

#[AsController]
final class FilelistController extends AbstractBackendController
{
    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        UriBuilder $uriBuilder,
        PageRenderer $pageRenderer,
        FlashMessageService $flashMessageService,
        SendRequestService $requestService,
        TranslationService $translationService,
        EventDispatcher $eventDispatcher,
        AiSuiteContext $aiSuiteContext,
        protected readonly WorkflowViewService $workflowViewService,
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

    /**
     * @throws Exception
     */
    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $this->initialize($request);
        $identifier = $request->getAttribute('route')->getOption('_identifier');

        switch ($identifier) {
            case 'ai_suite_workflow_filelist_files_prepare':
                return $this->indexAction();

            case 'ai_suite_workflow_filelist_files_translate_prepare':
                return $this->translateIndexAction();

            default:
                return $this->overviewAction();
        }
    }

    public function overviewAction(): ResponseInterface
    {
        return $this->view->renderResponse('Filelist/Overview');
    }

    protected function indexAction(): ResponseInterface
    {
        $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibraryEnumeration::METADATA, 'createMetadata', ['text']);
        if ('Error' === $librariesAnswer->getType()) {
            $this->view->addFlashMessage(
                strip_tags($librariesAnswer->getResponseData()['message']),
                '',
                ContextualFeedbackSeverity::ERROR
            );

            return $this->view->renderResponse('Workflow/FilesPrepare');
        }
        $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/workflow/filelist-files-prepare.js');

        $viewProperties = $this->workflowViewService->filelistFileDirectorySupport($librariesAnswer);
        $this->view->assignMultiple($viewProperties);

        return $this->view->renderResponse('Workflow/FilesPrepare');
    }

    protected function translateIndexAction(): ResponseInterface
    {
        $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibraryEnumeration::TRANSLATE, 'translate', ['text']);
        if ('Error' === $librariesAnswer->getType()) {
            $this->view->addFlashMessage(
                strip_tags($librariesAnswer->getResponseData()['message']),
                '',
                ContextualFeedbackSeverity::ERROR
            );

            return $this->view->renderResponse('Workflow/FilesTranslationPrepare');
        }
        $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/workflow/filelist-files-translation-prepare.js');
        $availableSourceLanguages = $this->aiSuiteContext->siteService->getAvailableLanguages(true, 0, true);
        $availableTargetLanguages = $this->aiSuiteContext->siteService->getAvailableLanguages(true);
        $availableTargetLanguages = array_diff_key($availableTargetLanguages, $availableSourceLanguages);
        $sessionData = $this->aiSuiteContext->sessionService->getParametersForRoute('ai_suite_workflow_filelist_files_translate_prepare');
        if (isset($sessionData['workflowFilelistTranslationPrepare'])) {
            $this->view->assign('preSelection', $sessionData['workflowFilelistTranslationPrepare']);
        }

        $viewProperties = $this->workflowViewService->filelistFileTranslationDirectorySupport($librariesAnswer);
        $this->view->assignMultiple($viewProperties);
        $this->view->assignMultiple([
            'sourceLanguages' => $availableSourceLanguages,
            'targetLanguages' => $availableTargetLanguages,
        ]);

        return $this->view->renderResponse('Workflow/FilesTranslationPrepare');
    }
}
