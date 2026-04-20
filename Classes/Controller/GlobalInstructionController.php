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

use AutoDudes\AiSuite\Controller\Trait\AjaxResponseTrait;
use AutoDudes\AiSuite\Domain\Repository\GlobalInstructionsRepository;
use AutoDudes\AiSuite\Domain\Repository\PagesRepository;
use AutoDudes\AiSuite\Service\AiSuiteContext;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\TranslationService;
use AutoDudes\AiSuite\Service\UuidService;
use AutoDudes\AiSuite\Service\ViewFactoryService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Site\Entity\NullSite;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

#[AsController]
class GlobalInstructionController extends AbstractBackendController
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
        protected readonly GlobalInstructionsRepository $globalInstructionsRepository,
        protected readonly PagesRepository $pagesRepository,
        protected readonly LoggerInterface $logger,
        protected readonly ViewFactoryService $viewFactoryService,
        protected readonly UuidService $uuidService,
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

            if ('0' === $pid) {
                $this->view->addFlashMessage(
                    $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.globalInstructionStorageMessage'),
                    $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.globalInstructionStorageTitle'),
                    ContextualFeedbackSeverity::WARNING
                );
            }

            $sites = $this->request->getAttribute('site');
            $allowedMounts = $this->aiSuiteContext->backendUserService->getSearchableWebmounts($rootPageId, 10);
            $search = ((array) $this->request->getParsedBody())['search'] ?? '';

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
                'pid' => $pid,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            $this->view->addFlashMessage(
                $e->getMessage(),
                $this->aiSuiteContext->localizationService->translate('aiSuite.error.default.title'),
                ContextualFeedbackSeverity::ERROR
            );
        }

        return $this->view->renderResponse('GlobalInstructions/Overview');
    }

    public function deactivateGlobalInstructionAction(): ResponseInterface
    {
        $id = (int) $this->request->getQueryParams()['recordId'];
        $this->globalInstructionsRepository->deactivateElement($id);

        return $this->overviewAction();
    }

    public function activateGlobalInstructionAction(): ResponseInterface
    {
        $id = (int) $this->request->getQueryParams()['recordId'];
        $this->globalInstructionsRepository->activateElement($id);

        return $this->overviewAction();
    }

    public function deleteGlobalInstructionAction(): ResponseInterface
    {
        $id = (int) $this->request->getQueryParams()['recordId'];
        $this->globalInstructionsRepository->deleteElement($id);

        return $this->overviewAction();
    }

    public function previewAction(ServerRequestInterface $request): ResponseInterface
    {
        $success = false;
        $output = '';
        $response = new Response();

        try {
            $parsedBody = (array) $request->getParsedBody();
            $params = [
                'globalInstructions' => '',
            ];
            if ('files' === $parsedBody['context'] && !empty($parsedBody['targetFolder'])) {
                $params['globalInstructions'] = $this->aiSuiteContext->globalInstructionService->buildGlobalInstruction($parsedBody['context'], $parsedBody['scope'], null, $parsedBody['targetFolder']);
            }
            if ('pages' === $parsedBody['context'] && !empty($parsedBody['pageId'])) {
                $params['globalInstructions'] = $this->aiSuiteContext->globalInstructionService->buildGlobalInstruction($parsedBody['context'], $parsedBody['scope'], (int) $parsedBody['pageId']);
            }
            $output = $this->viewFactoryService->renderTemplate(
                $request,
                'Preview',
                'EXT:ai_suite/Resources/Private/Templates/Ajax/GlobalInstruction/',
                $params
            );
            $success = true;
        } catch (\Throwable $exception) {
            $this->logger->error($exception->getMessage());
        }
        $response->getBody()->write(
            (string) json_encode(
                [
                    'success' => $success,
                    'output' => $output,
                ]
            )
        );

        return $response;
    }

    /**
     * @param list<array<string, mixed>> $instructions
     *
     * @return list<array<string, mixed>>
     */
    private function prepareGlobalInstructions(array $instructions, SiteInterface $sites, string $type): array
    {
        foreach ($instructions as $key => $globalInstruction) {
            $instructions[$key]['flag'] = $this->getFlagIdentifier($globalInstruction, $sites);

            if ('pages' === $type) {
                $selectedPages = $globalInstruction['selected_pages'] ? explode(',', $globalInstruction['selected_pages']) : [];
                $instructions[$key]['selected_pages'] = $this->pagesRepository->getPageTitlesForPages($selectedPages);
            } elseif ('files' === $type) {
                $selectedDirectories = $globalInstruction['selected_directories'] ? explode(',', $globalInstruction['selected_directories']) : [];
                $instructions[$key]['selected_directories'] = $selectedDirectories;
            }
        }

        return $instructions;
    }

    /**
     * @param array<string, mixed> $globalInstruction
     */
    private function getFlagIdentifier(array $globalInstruction, SiteInterface $sites): string
    {
        if ($sites instanceof NullSite) {
            return '';
        }
        if (-1 === $globalInstruction['sys_language_uid']) {
            return 'flags-multiple';
        }

        return $sites->getLanguageById($globalInstruction['sys_language_uid'])->getFlagIdentifier() ?? '';
    }
}
