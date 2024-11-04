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

use AutoDudes\AiSuite\Domain\Model\Dto\ServerRequest\ServerRequest;
use AutoDudes\AiSuite\Domain\Repository\CustomPromptTemplateRepository;
use AutoDudes\AiSuite\Utility\BackendUserUtility;
use AutoDudes\AiSuite\Utility\PromptTemplateUtility;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Site\Entity\NullSite;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class PromptTemplateController extends AbstractBackendController
{
    protected CustomPromptTemplateRepository $customPromptTemplateRepository;

    public function __construct(CustomPromptTemplateRepository $customPromptTemplateRepository)
    {
        parent::__construct();
        $this->customPromptTemplateRepository = $customPromptTemplateRepository;
    }

    public function overviewAction(): ResponseInterface
    {
        $this->moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * @throws Exception
     */
    public function manageCustomPromptTemplatesAction(): ResponseInterface
    {
        $rootPageId = $this->request->getAttribute('site')->getRootPageId();
        $sites = $this->request->getAttribute('site');
        $allowedMounts = BackendUserUtility::getSearchableWebmounts($rootPageId, 10);
        $search = '';
        if ($this->request->hasArgument('input')) {
            $input = $this->request->getArgument('input');
            $search = $input['search'] ?? '';
        }
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
            'pid' => $this->request->getQueryParams()['id'] ?? $rootPageId
        ]);
        $this->moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    public function updateServerPromptTemplatesAction(): ResponseInterface
    {
        $answer = $this->requestService->sendDataRequest('promptTemplates');

        if (PromptTemplateUtility::fetchPromptTemplates($answer)) {
            $this->addFlashMessage(
                LocalizationUtility::translate('aiSuite.module.updatePromptTemplatesSuccess', 'ai_suite')
            );
        } else {
            $this->addFlashMessage(
                $answer->getResponseData()['message'],
                LocalizationUtility::translate('aiSuite.module.updatePromptTemplatesError', 'ai_suite'),
                AbstractMessage::WARNING
            );
        }
        return $this->redirect('overview');
    }

    public function deactivateAction(): ResponseInterface
    {
        $id = (int)$this->request->getQueryParams()['recordId'];
        $this->customPromptTemplateRepository->deactivateElement($id);
        return $this->redirect('manageCustomPromptTemplates');
    }

    public function activateAction(): ResponseInterface
    {
        $id = (int)$this->request->getQueryParams()['recordId'];
        $this->customPromptTemplateRepository->activateElement($id);
        return $this->redirect('manageCustomPromptTemplates');
    }

    public function deleteAction(): ResponseInterface
    {
        $id = (int)$this->request->getQueryParams()['recordId'];
        $this->customPromptTemplateRepository->deleteElement($id);
        return $this->redirect('manageCustomPromptTemplates');
    }
}
