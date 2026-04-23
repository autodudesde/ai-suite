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

use AutoDudes\AiSuite\Domain\Repository\CustomPromptTemplateRepository;
use AutoDudes\AiSuite\Domain\Repository\GlobalInstructionsRepository;
use AutoDudes\AiSuite\Service\AiSuiteContext;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\TranslationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Site\Entity\NullSite;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

#[AsController]
class PromptTemplateController extends AbstractBackendController
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
        protected readonly CustomPromptTemplateRepository $customPromptTemplateRepository,
        protected readonly GlobalInstructionsRepository $globalInstructionsRepository,
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
     * @throws RouteNotFoundException
     */
    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $this->initialize($request);
        $identifier = $request->getAttribute('route')->getOption('_identifier');

        switch ($identifier) {
            case 'ai_suite_prompt_update_serverprompttemplates':
                return $this->updateServerPromptTemplatesAction();

            case 'ai_suite_prompt_manage_customprompttemplates':
                return $this->manageCustomPromptTemplatesAction();

            case 'ai_suite_prompt_activate_customprompttemplates':
                return $this->activateAction();

            case 'ai_suite_prompt_deactivate_customprompttemplates':
                return $this->deactivateAction();

            case 'ai_suite_prompt_delete_customprompttemplates':
                return $this->deleteAction();

            default:
                return $this->overviewAction();
        }
    }

    public function overviewAction(): ResponseInterface
    {
        $this->view->assign('pid', $this->request->getQueryParams()['id'] ?? $this->request->getAttribute('site')->getRootPageId());

        return $this->view->renderResponse('PromptTemplate/Overview');
    }

    public function manageCustomPromptTemplatesAction(): ResponseInterface
    {
        try {
            if ('0' === $this->request->getQueryParams()['id']) {
                $this->view->addFlashMessage(
                    $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.customPromptTemplateStorageMessage'),
                    $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.customPromptTemplateStorageTitle'),
                    ContextualFeedbackSeverity::WARNING
                );
            }
            $rootPageId = $this->request->getAttribute('site')->getRootPageId();
            $sites = $this->request->getAttribute('site');
            $allowedMounts = $this->aiSuiteContext->backendUserService->getSearchableWebmounts($rootPageId, 10);
            $parsedBody = (array) $this->request->getParsedBody();
            $search = $parsedBody['search'] ?? '';
            $customPromptTemplates = $this->customPromptTemplateRepository->findByAllowedMounts($allowedMounts, $search);
            foreach ($customPromptTemplates as $key => $customPromptTemplate) {
                if ($sites instanceof NullSite) {
                    $customPromptTemplates[$key]['flag'] = '';
                } else {
                    if (-1 === $customPromptTemplate['sys_language_uid']) {
                        $customPromptTemplates[$key]['flag'] = 'flags-multiple';
                    } else {
                        $customPromptTemplates[$key]['flag'] = $sites->getLanguageById($customPromptTemplate['sys_language_uid'])->getFlagIdentifier() ?? '';
                    }
                }
            }
            $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/prompt-templates/manage-custom-prompt-templates.js');
            $this->view->assignMultiple([
                'customPromptTemplates' => $customPromptTemplates,
                'search' => $search,
                'pid' => $this->request->getQueryParams()['id'],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            $this->view->addFlashMessage(
                $e->getMessage(),
                $this->aiSuiteContext->localizationService->translate('aiSuite.error.default.title'),
                ContextualFeedbackSeverity::ERROR
            );
        }

        return $this->view->renderResponse('PromptTemplate/ManageCustomPromptTemplates');
    }

    public function updateServerPromptTemplatesAction(): ResponseInterface
    {
        $answer = $this->requestService->sendDataRequest('promptTemplates');

        if ($this->aiSuiteContext->promptTemplateService->fetchPromptTemplates($answer)) {
            $this->view->addFlashMessage(
                $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.updatePromptTemplatesSuccess')
            );
        } else {
            $this->view->addFlashMessage(
                $answer->getResponseData()['message'],
                $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.updatePromptTemplatesError'),
                ContextualFeedbackSeverity::WARNING
            );
        }

        return $this->overviewAction();
    }

    public function deactivateAction(): ResponseInterface
    {
        $id = (int) $this->request->getQueryParams()['recordId'];
        $this->customPromptTemplateRepository->deactivateElement($id);

        return $this->manageCustomPromptTemplatesAction();
    }

    public function activateAction(): ResponseInterface
    {
        $id = (int) $this->request->getQueryParams()['recordId'];
        $this->customPromptTemplateRepository->activateElement($id);

        return $this->manageCustomPromptTemplatesAction();
    }

    public function deleteAction(): ResponseInterface
    {
        $id = (int) $this->request->getQueryParams()['recordId'];
        $this->customPromptTemplateRepository->deleteElement($id);

        return $this->manageCustomPromptTemplatesAction();
    }
}
