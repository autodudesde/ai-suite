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

namespace AutoDudes\AiSuite\Controller;

use AutoDudes\AiSuite\Enumeration\GenerationLibrariesEnumeration;
use AutoDudes\AiSuite\Service\BackendUserService;
use AutoDudes\AiSuite\Service\GlobalInstructionService;
use AutoDudes\AiSuite\Service\LibraryService;
use AutoDudes\AiSuite\Service\MassActionService;
use AutoDudes\AiSuite\Service\PromptTemplateService;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\SessionService;
use AutoDudes\AiSuite\Service\SiteService;
use AutoDudes\AiSuite\Service\TranslationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;

#[AsController]
final class FilelistController extends AbstractBackendController
{
    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        IconFactory $iconFactory,
        UriBuilder $uriBuilder,
        PageRenderer $pageRenderer,
        FlashMessageService $flashMessageService,
        SendRequestService $requestService,
        BackendUserService $backendUserService,
        LibraryService $libraryService,
        PromptTemplateService $promptTemplateService,
        GlobalInstructionService $globalInstructionService,
        SiteService $siteService,
        TranslationService $translationService,
        SessionService $sessionService,
        EventDispatcher $eventDispatcher,
        protected MassActionService $massActionService
    ) {
        parent::__construct(
            $moduleTemplateFactory,
            $iconFactory,
            $uriBuilder,
            $pageRenderer,
            $flashMessageService,
            $requestService,
            $backendUserService,
            $libraryService,
            $promptTemplateService,
            $globalInstructionService,
            $siteService,
            $translationService,
            $sessionService,
            $eventDispatcher
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
            case 'ai_suite_massaction_filelist_files_prepare':
                return $this->indexAction();
            case 'ai_suite_massaction_filelist_files_translate_prepare':
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
        $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibrariesEnumeration::METADATA, 'createMetadata', ['text']);
        if ($librariesAnswer->getType() === 'Error') {
            $this->view->addFlashMessage(
                strip_tags($librariesAnswer->getResponseData()['message']),
                '',
                ContextualFeedbackSeverity::ERROR
            );
            return $this->view->renderResponse('MassAction/FilesPrepare');
        }
        $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/mass-action/filelist-files-prepare.js');

        $viewProperties = $this->massActionService->filelistFileDirectorySupport($librariesAnswer);
        $this->view->assignMultiple($viewProperties);

        return $this->view->renderResponse('MassAction/FilesPrepare');
    }

    protected function translateIndexAction(): ResponseInterface
    {
        $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibrariesEnumeration::TRANSLATE, 'translate', ['text']);
        if ($librariesAnswer->getType() === 'Error') {
            $this->view->addFlashMessage(
                strip_tags($librariesAnswer->getResponseData()['message']),
                '',
                ContextualFeedbackSeverity::ERROR
            );
            return $this->view->renderResponse('MassAction/FilesTranslationPrepare');
        }
        $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/mass-action/filelist-files-translation-prepare.js');
        $availableSourceLanguages = $this->siteService->getAvailableLanguages(true, 0, true);
        $availableTargetLanguages = $this->siteService->getAvailableLanguages(true);
        $availableTargetLanguages = array_diff_key($availableTargetLanguages, $availableSourceLanguages);
        $sessionData = $this->sessionService->getParametersForRoute('ai_suite_massaction_filelist_files_translate_prepare');
        if (isset($sessionData['massActionFilelistTranslationPrepare'])) {
            $this->view->assign('preSelection', $sessionData['massActionFilelistTranslationPrepare']);
        }

        $viewProperties = $this->massActionService->filelistFileTranslationDirectorySupport($librariesAnswer);
        $this->view->assignMultiple($viewProperties);
        $this->view->assignMultiple([
            'sourceLanguages' => $availableSourceLanguages,
            'targetLanguages' => $availableTargetLanguages,
        ]);

        return $this->view->renderResponse('MassAction/FilesTranslationPrepare');
    }
}
