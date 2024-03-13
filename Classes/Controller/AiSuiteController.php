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
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class AiSuiteController extends AbstractBackendController
{
    protected array $extConf;
    protected SendRequestService $requestService;

    public function __construct(
        array $extConf,
        SendRequestService $requestService
    ) {
        parent::__construct($extConf);
        $this->extConf = $extConf;
        $this->requestService = $requestService;
    }

    /**
     * action dashboard
     */
    public function dashboardAction(): ResponseInterface
    {
        $freeRequests = '-';
        $paidRequests = '-';
        $openAiStatus = '-';
        $openAiState = '-';

        $answer = $this->requestService->sendRequest(
            new ServerRequest(
                $this->extConf,
                'getRequestsState'
            )
        );
        if ($answer->getType() === 'RequestsState') {
            $freeRequests = $answer->getResponseData()['free_requests'];
            $paidRequests = $answer->getResponseData()['paid_requests'];
        } else {
            $this->addFlashMessage(
                $answer->getResponseData()['message'],
                LocalizationUtility::translate('aiSuite.module.warningFetchingRequestsState.title', 'ai_suite'),
                ContextualFeedbackSeverity::WARNING
            );
        }

        $answer = $this->requestService->sendRequest(
            new ServerRequest(
                $this->extConf,
                'openAiStatus'
            )
        );
        if ($answer->getType() === 'OpenAiStatus') {
            $openAiStatus = $answer->getResponseData()['status'];
            $openAiState = $answer->getResponseData()['state'];
        } else {
            $this->addFlashMessage(
                $answer->getResponseData()['message'],
                LocalizationUtility::translate('aiSuite.module.warningFetchingOpenAiStatus.title', 'ai_suite'),
                ContextualFeedbackSeverity::WARNING
            );
        }

        $this->moduleTemplate->assignMultiple([
            'sectionActive' => 'dashboard',
            'freeRequests' => $freeRequests,
            'paidRequests' => $paidRequests,
            'openAiStatus' => $openAiStatus,
            'openAiState' => $openAiState
        ]);
        return $this->htmlResponse($this->moduleTemplate->render());
    }
}
