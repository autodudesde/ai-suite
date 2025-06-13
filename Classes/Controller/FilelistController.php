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
use AutoDudes\AiSuite\Service\LibraryService;
use AutoDudes\AiSuite\Service\MassActionService;
use AutoDudes\AiSuite\Service\PromptTemplateService;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\SessionService;
use AutoDudes\AiSuite\Service\SiteService;
use AutoDudes\AiSuite\Service\TranslationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Http\ResponseFactory;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;

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
        SiteService $siteService,
        TranslationService $translationService,
        ResponseFactory $responseFactory,
        SessionService $sessionService,
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
            $siteService,
            $translationService,
            $sessionService,
            $responseFactory
        );
    }

    /**
     * @throws Exception
     */
    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $this->initialize($request);
        return $this->indexAction();
    }

    protected function indexAction(): ResponseInterface {
        $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibrariesEnumeration::METADATA, 'createMetadata', ['text']);
        if ($librariesAnswer->getType() === 'Error') {
            $this->view->addFlashMessage(
                strip_tags($librariesAnswer->getResponseData()['message']),
                '',
                AbstractMessage::ERROR
            );
            return $this->htmlResponse(
                $this->view->setContent(
                    $this->renderView(
                        'MassAction/FilesPrepare',
                        []
                    )
                )->renderContent()
            );
        }
//        $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/mass-action/filelist-files-prepare.js');
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/AiSuite/MassAction/FilelistFilesPrepare');

        $viewProperties = $this->massActionService->filelistFileDirectorySupport($librariesAnswer);

        return $this->htmlResponse(
            $this->view->setContent(
                $this->renderView(
                    'MassAction/FilesPrepare',
                    $viewProperties
                )
            )->renderContent()
        );
    }
}
