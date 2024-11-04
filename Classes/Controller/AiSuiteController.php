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

use AutoDudes\AiSuite\Domain\Repository\RequestsRepository;
use AutoDudes\AiSuite\Factory\SettingsFactory;
use AutoDudes\AiSuite\Utility\BackendUserUtility;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class AiSuiteController extends AbstractBackendController
{
    protected array $extConf;
    protected SettingsFactory $settingsFactory;
    protected RequestsRepository $requestsRepository;

    public function __construct(
        RequestsRepository $requestsRepository,
        SettingsFactory $settingsFactory
    ) {
        parent::__construct();
        $this->settingsFactory = $settingsFactory;
        $this->extConf = $this->settingsFactory->mergeExtConfAndUserGroupSettings();
        $this->requestsRepository = $requestsRepository;
    }

    /**
     * action dashboard
     */
    public function dashboardAction(): ResponseInterface
    {
        if ($this->extConf['aiSuiteApiKey'] === '' && BackendUserUtility::checkGroupSpecificInputs('aiSuiteApiKey') === '') {
            $this->addFlashMessage(
                LocalizationUtility::translate('aiSuite.module.missingAiSuiteApiKey.message', 'ai_suite'),
                LocalizationUtility::translate('aiSuite.module.missingAiSuiteApiKey.title', 'ai_suite'),
                ContextualFeedbackSeverity::NOTICE
            );
        }
        $answer = $this->requestService->sendDataRequest('getRequestsState');
        if ($answer->getType() === 'RequestsState') {
            $freeRequests = $answer->getResponseData()['free_requests'] ?? -1;
            $paidRequests = $answer->getResponseData()['paid_requests'] ?? -1;
            if (array_key_exists('free_requests', $answer->getResponseData()) && array_key_exists('free_requests', $answer->getResponseData())) {
                $this->moduleTemplate->assignMultiple([
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
                    ContextualFeedbackSeverity::ERROR
                );
            }
            BackendUtility::setUpdateSignal('updateTopbar');
        } else {
            $this->addFlashMessage(
                $answer->getResponseData()['message'],
                LocalizationUtility::translate('aiSuite.module.warningFetchingCreditsState.title', 'ai_suite'),
                ContextualFeedbackSeverity::WARNING
            );
        }

        return $this->htmlResponse($this->moduleTemplate->render());
    }
}
