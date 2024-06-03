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
use AutoDudes\AiSuite\Domain\Repository\RequestsRepository;
use AutoDudes\AiSuite\Service\SendRequestService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class AiSuiteController extends AbstractBackendController
{
    protected array $extConf;
    protected SendRequestService $requestService;

    protected RequestsRepository $requestsRepository;

    public function __construct(
        array $extConf,
        SendRequestService $requestService,
        RequestsRepository $requestsRepository
    ) {
        parent::__construct($extConf);
        $this->extConf = $extConf;
        $this->requestService = $requestService;
        $this->requestsRepository = $requestsRepository;
    }

    /**
     * action dashboard
     */
    public function dashboardAction(): ResponseInterface
    {
        $openAiStatus = '-';
        $openAiState = '-';

        if($this->extConf['aiSuiteApiKey'] === '') {
            $this->addFlashMessage(
                LocalizationUtility::translate('aiSuite.module.missingAiSuiteApiKey.message', 'ai_suite'),
                LocalizationUtility::translate('aiSuite.module.missingAiSuiteApiKey.title', 'ai_suite'),
                AbstractMessage::NOTICE
            );
        }
        $answer = $this->requestService->sendRequest(
            new ServerRequest(
                $this->extConf,
                'getRequestsState'
            )
        );
        if ($answer->getType() === 'RequestsState') {
            $freeRequests = $answer->getResponseData()['free_requests'] ?? -1;
            $paidRequests = $answer->getResponseData()['paid_requests'] ?? -1;
            if(array_key_exists('free_requests', $answer->getResponseData()) && array_key_exists('free_requests', $answer->getResponseData())) {
                $this->view->assignMultiple([
                    'freeRequests' => $answer->getResponseData()['free_requests'],
                    'paidRequests' => $answer->getResponseData()['paid_requests']
                ]);
            }
            try {
                $this->requestsRepository->setRequests($freeRequests, $paidRequests);
            } catch (\Exception $e) {
                $this->addFlashMessage(
                    $e->getMessage(),
                    LocalizationUtility::translate('aiSuite.error_no_credits_table', 'ai_suite'),
                    AbstractMessage::ERROR
                );
            }
            BackendUtility::setUpdateSignal('updateTopbar');
        } else {
            $this->addFlashMessage(
                $answer->getResponseData()['message'],
                LocalizationUtility::translate('aiSuite.module.warningFetchingCreditsState.title', 'ai_suite'),
                AbstractMessage::WARNING
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
                AbstractMessage::WARNING
            );
        }

        $this->view->assignMultiple([
            'sectionActive' => 'dashboard',
            'openAiStatus' => $openAiStatus,
            'openAiState' => $openAiState
        ]);
        $this->moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }
}
