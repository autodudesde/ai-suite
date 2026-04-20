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

use AutoDudes\AiSuite\Domain\Repository\PagesRepository;
use AutoDudes\AiSuite\Enumeration\GenerationLibraryEnumeration;
use AutoDudes\AiSuite\Factory\PageStructureFactory;
use AutoDudes\AiSuite\Service\AiSuiteContext;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\TranslationService;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

#[AsController]
class PagesController extends AbstractBackendController
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
        protected readonly PageStructureFactory $pageStructureFactory,
        protected readonly PagesRepository $pagesRepository,
        protected readonly LoggerInterface $logger,
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
            case 'ai_suite_page_create_pagetree':
                return $this->pageStructureAction();

            case 'ai_suite_page_validate_pagetree':
                return $this->validatePageStructureResultAction();

            case 'ai_suite_page_validate_pagetree_create':
                return $this->createValidatedPageStructureAction();

            default:
                return $this->overviewAction();
        }
    }

    public function overviewAction(): ResponseInterface
    {
        return $this->view->renderResponse('Pages/Overview');
    }

    public function pageStructureAction(): ResponseInterface
    {
        try {
            $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/pages/creation.js');
            $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibraryEnumeration::PAGETREE, 'pageTree', ['text']);
            $this->view->assignMultiple([
                'pagesSelect' => $this->getPagesInWebMount(),
                'textGenerationLibraries' => $this->aiSuiteContext->libraryService->prepareLibraries($librariesAnswer->getResponseData()['textGenerationLibraries']),
                'paidRequestsAvailable' => $librariesAnswer->getResponseData()['paidRequestsAvailable'],
                'promptTemplates' => $this->aiSuiteContext->promptTemplateService->getAllPromptTemplates('pageTree'),
                'sysLanguages' => $this->aiSuiteContext->siteService->getAvailableLanguages(),
            ]);
        } catch (\Throwable $e) {
            $this->view->assign('error', true);
            $this->logger->error($e->getMessage());
            $this->view->addFlashMessage(
                $e->getMessage(),
                $this->aiSuiteContext->localizationService->translate('aiSuite.error.default.title'),
                ContextualFeedbackSeverity::ERROR
            );
        }

        return $this->view->renderResponse('Pages/PageStructure');
    }

    public function validatePageStructureResultAction(): ResponseInterface
    {
        try {
            $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/pages/validation.js');
            $parsedBody = (array) $this->request->getParsedBody();
            $textAi = $parsedBody['libraries']['textGenerationLibrary'] ?? '';
            $paidRequestsAvailable = isset($parsedBody['paidRequestsAvailable']) ? '1' === $parsedBody['paidRequestsAvailable'] : false;
            $globalInstructions = $this->aiSuiteContext->globalInstructionService->buildGlobalInstruction('pages', 'pageTree', (int) $parsedBody['startStructureFromPid']);
            $prompt = $globalInstructions."\n".($parsedBody['plainPrompt'] ?? '');
            if (-1 === (int) $parsedBody['startStructureFromPid']) {
                $langIsoCode = $parsedBody['sysLanguage'] ?? '';
            } else {
                $langIsoCode = $this->aiSuiteContext->siteService->getIsoCodeByLanguageId(0, (int) $parsedBody['startStructureFromPid']);
            }
            // $globalInstructions = $this->aiSuiteContext->globalInstructionService->buildGlobalInstruction('pages', 'pageTree', $parsedBody['startStructureFromPid']);
            $answer = $this->requestService->sendDataRequest(
                'pageTree',
                [
                    'global_instructions' => $globalInstructions,
                ],
                $prompt,
                $langIsoCode,
                [
                    'text' => $textAi,
                ],
            );
            if ('Error' === $answer->getType()) {
                $this->view->addFlashMessage(
                    $answer->getResponseData()['message'],
                    $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.errorFetchingPagetreeResponse.title'),
                    ContextualFeedbackSeverity::ERROR
                );

                return $this->pageStructureAction();
            }
            $this->view->assignMultiple([
                'aiResult' => $answer->getResponseData()['pagetreeResult'],
                'prompt' => $parsedBody['plainPrompt'] ?? '',
                'promptTemplates' => $this->aiSuiteContext->promptTemplateService->getAllPromptTemplates('pageTree'),
                'selectedPid' => $parsedBody['startStructureFromPid'] ?? 0,
                'pagesSelect' => $this->getPagesInWebMount(),
                'textGenerationLibraries' => $this->aiSuiteContext->libraryService->prepareLibraries(json_decode($parsedBody['textGenerationLibraries'], true), $textAi),
                'paidRequestsAvailable' => $paidRequestsAvailable,
                'sysLanguages' => $this->aiSuiteContext->siteService->getAvailableLanguages(),
                'selectedSysLanguage' => $langIsoCode,
                'globalInstructions' => $globalInstructions,
            ]);
            $this->view->addFlashMessage(
                $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.fetchingDataSuccessful.message'),
                $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.fetchingDataSuccessful.title'),
            );
        } catch (\Throwable $e) {
            $this->view->assign('error', true);
            $this->logger->error($e->getMessage());
            $this->view->addFlashMessage(
                $e->getMessage(),
                $this->aiSuiteContext->localizationService->translate('aiSuite.error.default.title'),
                ContextualFeedbackSeverity::ERROR
            );
        }

        return $this->view->renderResponse('Pages/ValidatePageStructureResult');
    }

    public function createValidatedPageStructureAction(): ResponseInterface
    {
        try {
            $createParsedBody = (array) $this->request->getParsedBody();
            $selectedPageTreeContent = $createParsedBody['selectedPageTreeContent'] ?? '';
            $startStructureFromPid = $createParsedBody['startStructureFromPid'] ?? 0;
            $this->pageStructureFactory->createFromArray(json_decode($selectedPageTreeContent, true), (int) $startStructureFromPid);
            BackendUtility::setUpdateSignal('updatePageTree');
            $this->view->addFlashMessage(
                $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.pagetreeGenerationSuccessful.title'),
                $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.pagetreeGenerationSuccessful.title'),
            );
        } catch (\Throwable $e) {
            $this->view->assign('error', true);
            $this->logger->error($e->getMessage());
            $this->view->addFlashMessage(
                $e->getMessage(),
                $this->aiSuiteContext->localizationService->translate('aiSuite.error.default.title'),
                ContextualFeedbackSeverity::ERROR
            );
        }

        return $this->overviewAction();
    }

    /**
     * @return array<int|string, mixed>
     */
    private function getPagesInWebMount(): array
    {
        $pagesSelect = [];
        if ($this->aiSuiteContext->backendUserService->getBackendUser()?->isAdmin() ?? false) {
            $pagesSelect = [
                -1 => $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.pages.newRootPage'),
            ];
        }

        return $pagesSelect + $this->aiSuiteContext->backendUserService->fetchAccessablePages();
    }
}
