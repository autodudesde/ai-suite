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
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Utility\PromptTemplateUtility;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class PromptTemplateController extends AbstractBackendController
{
    protected array $extConf;
    protected SendRequestService $requestService;
    protected PromptTemplateUtility $promptTemplateUtility;

    public function __construct(
        array                 $extConf,
        SendRequestService    $requestService,
        PromptTemplateUtility $promptTemplateUtility
    )
    {
        parent::__construct($extConf);
        $this->extConf = $extConf;
        $this->requestService = $requestService;
        $this->promptTemplateUtility = $promptTemplateUtility;
    }

    public function overviewAction(): ResponseInterface
    {
        $this->moduleTemplate->assignMultiple([
            'sectionActive' => 'promptTemplate',
        ]);
        return $this->htmlResponse($this->moduleTemplate->render());
    }

    public function manageCustomPromptTemplatesAction(): ResponseInterface
    {
        $inputForm = $this->request->hasArgument('input') ? $this->request->getArgument('input') : '';
        $search = '';
        if (is_array($inputForm) && array_key_exists('search', $inputForm)) {
            $search = $inputForm['search'];
        }
        $rootPageId = $this->request->getAttribute('site')->getRootPageId();
        $this->moduleTemplate->assignMultiple([
            'sectionActive' => 'promptTemplate',
            'search' => $search,
            'pid' => $this->request->getQueryParams()['id'] ?? $rootPageId
        ]);
        return $this->htmlResponse($this->moduleTemplate->render());
    }

    public function updateServerPromptTemplatesAction(): ResponseInterface
    {
        $answer = $this->requestService->sendRequest(
            new ServerRequest(
                $this->extConf,
                'promptTemplates'
            )
        );

        if ($this->promptTemplateUtility->fetchPromptTemplates($answer)) {
            $this->addFlashMessage(
                LocalizationUtility::translate('aiSuite.module.updatePromptTemplatesSuccess', 'ai_suite')
            );
        } else {
            $this->addFlashMessage(
                $answer->getResponseData()['message'],
                LocalizationUtility::translate('aiSuite.module.updatePromptTemplatesError', 'ai_suite'),
                ContextualFeedbackSeverity::WARNING
            );
        }
        return $this->redirect('overview');
    }
}
