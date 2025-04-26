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

use AutoDudes\AiSuite\Domain\Repository\CustomPromptTemplateRepository;
use AutoDudes\AiSuite\Service\BackendUserService;
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

#[AsController]
class PromptTemplateController extends AbstractBackendController
{
    protected CustomPromptTemplateRepository $customPromptTemplateRepository;
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
        SiteService $siteService,
        TranslationService $translationService,
        SessionService $sessionService,
        CustomPromptTemplateRepository $customPromptTemplateRepository,
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
            $siteService,
            $translationService,
            $sessionService
        );
        $this->customPromptTemplateRepository = $customPromptTemplateRepository;
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
            if($this->request->getQueryParams()['id'] === '0') {
                $this->view->addFlashMessage(
                    $this->translationService->translate('aiSuite.module.customPromptTemplateStorageMessage'),
                    $this->translationService->translate('aiSuite.module.customPromptTemplateStorageTitle'),
                    ContextualFeedbackSeverity::WARNING
                );
            }
            $rootPageId = $this->request->getAttribute('site')->getRootPageId();
            $sites = $this->request->getAttribute('site');
            $allowedMounts = $this->backendUserService->getSearchableWebmounts($rootPageId, 10);
            $search = isset($this->request->getParsedBody()['search']) ? $this->request->getParsedBody()['search'] : '';
            $customPromptTemplates = $this->customPromptTemplateRepository->findByAllowedMounts($allowedMounts, $search);
            foreach ($customPromptTemplates as $key => $customPromptTemplate) {
                if ($sites instanceof NullSite) {
                    $customPromptTemplates[$key]['flag'] = '';
                } else {
                    if ($customPromptTemplate['sys_language_uid'] === -1) {
                        $customPromptTemplates[$key]['flag'] = 'flags-multiple';
                    } else {
                        $customPromptTemplates[$key]['flag'] = $sites->getLanguageById($customPromptTemplate['sys_language_uid'])->getFlagIdentifier() ?? '';
                    }
                }
            }
            $this->view->assignMultiple([
                'customPromptTemplates' => $customPromptTemplates,
                'search' => $search,
                'pid' => $this->request->getQueryParams()['id']
            ]);
        }  catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            $this->view->addFlashMessage(
                $e->getMessage(),
                $this->translationService->translate('aiSuite.error.default.title'),
                ContextualFeedbackSeverity::ERROR
            );
        }
        return $this->view->renderResponse('PromptTemplate/ManageCustomPromptTemplates');
    }

    public function updateServerPromptTemplatesAction(): ResponseInterface
    {
        $answer = $this->requestService->sendDataRequest('promptTemplates');

        if ($this->promptTemplateService->fetchPromptTemplates($answer)) {
            $this->view->addFlashMessage(
                $this->translationService->translate('aiSuite.module.updatePromptTemplatesSuccess')
            );
        } else {
            $this->view->addFlashMessage(
                $answer->getResponseData()['message'],
                $this->translationService->translate('aiSuite.module.updatePromptTemplatesError'),
                ContextualFeedbackSeverity::WARNING
            );
        }
        return $this->overviewAction();
    }


    public function deactivateAction(): ResponseInterface
    {
        $id = (int)$this->request->getQueryParams()['recordId'];
        $this->customPromptTemplateRepository->deactivateElement($id);
        return $this->manageCustomPromptTemplatesAction();
    }


    public function activateAction(): ResponseInterface
    {
        $id = (int)$this->request->getQueryParams()['recordId'];
        $this->customPromptTemplateRepository->activateElement($id);
        return $this->manageCustomPromptTemplatesAction();
    }


    public function deleteAction(): ResponseInterface
    {
        $id = (int)$this->request->getQueryParams()['recordId'];
        $this->customPromptTemplateRepository->deleteElement($id);
        return $this->manageCustomPromptTemplatesAction();
    }
}
