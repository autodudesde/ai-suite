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

use AutoDudes\AiSuite\Events\BeforeAiSuiteAjaxTemplateRenderEvent;
use AutoDudes\AiSuite\Service\BackendUserService;
use AutoDudes\AiSuite\Service\GlobalInstructionService;
use AutoDudes\AiSuite\Service\LibraryService;
use AutoDudes\AiSuite\Service\PromptTemplateService;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\SiteService;
use AutoDudes\AiSuite\Service\TranslationService;
use AutoDudes\AiSuite\Service\UuidService;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;

abstract class AbstractAjaxController
{
    protected SendRequestService $requestService;
    protected BackendUserService $backendUserService;
    protected PromptTemplateService $promptTemplateService;
    protected GlobalInstructionService $globalInstructionService;
    protected LibraryService $libraryService;
    protected UuidService $uuidService;
    protected SiteService $siteService;
    protected TranslationService $translationService;
    protected ViewFactoryInterface $viewFactory;
    protected LoggerInterface $logger;
    protected EventDispatcher $eventDispatcher;

    public function __construct(
        BackendUserService $backendUserService,
        SendRequestService $requestService,
        PromptTemplateService $promptTemplateService,
        GlobalInstructionService $globalInstructionService,
        LibraryService $libraryService,
        UuidService $uuidService,
        SiteService $siteService,
        TranslationService $translationService,
        ViewFactoryInterface $viewFactory,
        LoggerInterface $logger,
        EventDispatcher $eventDispatcher
    ) {
        $this->backendUserService = $backendUserService;
        $this->requestService = $requestService;
        $this->promptTemplateService = $promptTemplateService;
        $this->globalInstructionService = $globalInstructionService;
        $this->libraryService = $libraryService;
        $this->uuidService = $uuidService;
        $this->siteService = $siteService;
        $this->translationService = $translationService;
        $this->viewFactory = $viewFactory;
        $this->logger = $logger;
        $this->eventDispatcher = $eventDispatcher;
    }
    protected function getContentFromTemplate(
        ServerRequestInterface $request,
        string $templateName,
        string $templateRootPath,
        array $params = []
    ): string {
        $viewFactoryData = new ViewFactoryData(
            templateRootPaths: [$templateRootPath],
            partialRootPaths: ['EXT:ai_suite/Resources/Private/Partials'],
            layoutRootPaths: ['EXT:ai_suite/Resources/Private/Layouts'],
            request: $request,
        );
        $view = $this->viewFactory->create($viewFactoryData);
        $params['inlineStyles'] = file_get_contents(GeneralUtility::getFileAbsFileName('EXT:ai_suite/Resources/Public/Css/Ajax/wizard-general.css'));

        $event = new BeforeAiSuiteAjaxTemplateRenderEvent($request, $params);
        $this->eventDispatcher->dispatch($event);
        $params = $event->getParams();

        $view->assignMultiple($params);
        return $view->render($templateName);
    }

    protected function logError(string $errorMessage, Response $response, int $statusCode = 400): void
    {
        $this->logger->error($errorMessage);
        $response->withStatus($statusCode);
        $response->getBody()->write(json_encode(['success' => false, 'status' => $statusCode,'error' => $errorMessage]));
    }
}
