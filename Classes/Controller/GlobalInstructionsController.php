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

use AutoDudes\AiSuite\Domain\Repository\GlobalInstructionsRepository;
use AutoDudes\AiSuite\Domain\Repository\PagesRepository;
use AutoDudes\AiSuite\Service\BackendUserService;
use AutoDudes\AiSuite\Service\GlobalInstructionService;
use AutoDudes\AiSuite\Service\LibraryService;
use AutoDudes\AiSuite\Service\PromptTemplateService;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\SessionService;
use AutoDudes\AiSuite\Service\SiteService;
use AutoDudes\AiSuite\Service\TranslationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Site\Entity\NullSite;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;

#[AsController]
class GlobalInstructionsController extends AbstractBackendController
{
    protected GlobalInstructionsRepository $globalInstructionsRepository;
    protected PagesRepository $pagesRepository;
    protected LoggerInterface $logger;

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
        GlobalInstructionsRepository $globalInstructionsRepository,
        PagesRepository $pageRepository,
        LoggerInterface $logger
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
        $this->globalInstructionsRepository = $globalInstructionsRepository;
        $this->pagesRepository = $pageRepository;
        $this->logger = $logger;
    }

    /**
     * @throws RouteNotFoundException
     */
    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $this->initialize($request);
        $identifier = $request->getAttribute('route')->getOption('_identifier');
        switch ($identifier) {
            case 'ai_suite_global_instructions_activate':
                return $this->activateGlobalInstructionAction();
            case 'ai_suite_global_instructions_deactivate':
                return $this->deactivateGlobalInstructionAction();
            case 'ai_suite_global_instructions_delete':
                return $this->deleteGlobalInstructionAction();
            default:
                return $this->overviewAction();
        }
    }

    public function overviewAction(): ResponseInterface
    {
        try {
            $rootPageId = $this->request->getAttribute('site')->getRootPageId();
            $pid = $this->request->getQueryParams()['id'] ?? $rootPageId;

            if ($pid === '0') {
                $this->view->addFlashMessage(
                    $this->translationService->translate('aiSuite.module.globalInstructionStorageMessage'),
                    $this->translationService->translate('aiSuite.module.globalInstructionStorageTitle'),
                    ContextualFeedbackSeverity::WARNING
                );
            }

            $sites = $this->request->getAttribute('site');
            $allowedMounts = $this->backendUserService->getSearchableWebmounts($rootPageId, 10);
            $search = $this->request->getParsedBody()['search'] ?? '';

            $globalInstructionsPages = $this->prepareGlobalInstructions(
                $this->globalInstructionsRepository->findByAllowedMounts($allowedMounts, $search, ''),
                $sites,
                'pages'
            );

            $globalInstructionsFiles = $this->prepareGlobalInstructions(
                $this->globalInstructionsRepository->findByAllowedMounts($allowedMounts, $search, '', 'files'),
                $sites,
                'files'
            );

            $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/global-instructions/overview.js');
            $this->view->assignMultiple([
                'globalInstructionsPages' => $globalInstructionsPages,
                'globalInstructionsFiles' => $globalInstructionsFiles,
                'search' => $search,
                'pid' => $pid
            ]);
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            $this->view->addFlashMessage(
                $e->getMessage(),
                $this->translationService->translate('aiSuite.error.default.title'),
                ContextualFeedbackSeverity::ERROR
            );
        }
        return $this->view->renderResponse('GlobalInstructions/Overview');
    }

    public function deactivateGlobalInstructionAction(): ResponseInterface
    {
        $id = (int)$this->request->getQueryParams()['recordId'];
        $this->globalInstructionsRepository->deactivateElement($id);
        return $this->overviewAction();
    }

    public function activateGlobalInstructionAction(): ResponseInterface
    {
        $id = (int)$this->request->getQueryParams()['recordId'];
        $this->globalInstructionsRepository->activateElement($id);
        return $this->overviewAction();
    }

    public function deleteGlobalInstructionAction(): ResponseInterface
    {
        $id = (int)$this->request->getQueryParams()['recordId'];
        $this->globalInstructionsRepository->deleteElement($id);
        return $this->overviewAction();
    }

    private function prepareGlobalInstructions(array $instructions, $sites, string $type): array
    {
        foreach ($instructions as $key => $globalInstruction) {
            $instructions[$key]['flag'] = $this->getFlagIdentifier($globalInstruction, $sites);

            if ($type === 'pages') {
                $selectedPages = $globalInstruction['selected_pages'] ? explode(',', $globalInstruction['selected_pages']) : [];
                $instructions[$key]['selected_pages'] = $this->pagesRepository->getPageTitlesForPages($selectedPages);
            } elseif ($type === 'files') {
                $selectedDirectories = $globalInstruction['selected_directories'] ? explode(',', $globalInstruction['selected_directories']) : [];
                $instructions[$key]['selected_directories'] = $selectedDirectories;
            }
        }

        return $instructions;
    }

    private function getFlagIdentifier(array $globalInstruction, $sites): string
    {
        if ($sites instanceof NullSite) {
            return '';
        }
        if ($globalInstruction['sys_language_uid'] === -1) {
            return 'flags-multiple';
        }
        return $sites->getLanguageById($globalInstruction['sys_language_uid'])->getFlagIdentifier() ?? '';
    }
}
