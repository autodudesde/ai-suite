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

use AutoDudes\AiSuite\Domain\Model\Dto\PageStructureInput;
use AutoDudes\AiSuite\Domain\Repository\PagesRepository;
use AutoDudes\AiSuite\Enumeration\GenerationLibrariesEnumeration;
use AutoDudes\AiSuite\Exception\AiSuiteServerException;
use AutoDudes\AiSuite\Factory\PageStructureFactory;
use AutoDudes\AiSuite\Utility\BackendUserUtility;
use AutoDudes\AiSuite\Utility\LibraryUtility;
use AutoDudes\AiSuite\Utility\PromptTemplateUtility;
use AutoDudes\AiSuite\Utility\SiteUtility;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class PagesController extends AbstractBackendController
{
    protected PageStructureFactory $pageStructureFactory;
    protected PagesRepository $pagesRepository;

    public function __construct(
        PageStructureFactory $pageStructureFactory,
        PagesRepository $pagesRepository
    ) {
        parent::__construct();
        $this->pageStructureFactory = $pageStructureFactory;
        $this->pagesRepository = $pagesRepository;
    }

    public function overviewAction(): ResponseInterface
    {
        return $this->htmlResponse($this->moduleTemplate->render());
    }

    /**
     * @throws AiSuiteServerException
     */
    public function pageStructureAction(): ResponseInterface
    {
        $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/pages/creation.js');
        $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibrariesEnumeration::PAGETREE, 'pageTree', ['text']);
        if ($librariesAnswer->getType() === 'Error') {
            $this->addFlashMessage(
                $librariesAnswer->getResponseData()['message'],
                LocalizationUtility::translate('aiSuite.module.errorFetchingLibraries.title', 'ai_suite'),
                ContextualFeedbackSeverity::ERROR
            );
            return $this->redirect('overview');
        }
        $this->moduleTemplate->assignMultiple([
            'input' => PageStructureInput::createEmpty(),
            'pagesSelect' => $this->getPagesInWebMount(),
            'textGenerationLibraries' => LibraryUtility::prepareLibraries($librariesAnswer->getResponseData()['textGenerationLibraries']),
            'paidRequestsAvailable' => $librariesAnswer->getResponseData()['paidRequestsAvailable'],
            'promptTemplates' => PromptTemplateUtility::getAllPromptTemplates('pageTree'),
        ]);
        return $this->htmlResponse($this->moduleTemplate->render());
    }

    public function validatePageStructureResultAction(PageStructureInput $input): ResponseInterface
    {
        $textAi = !empty($this->request->getParsedBody()['libraries']['textGenerationLibrary']) ? $this->request->getParsedBody()['libraries']['textGenerationLibrary'] : '';
        $site = $this->request->getAttribute('site');
        $defaultLanguageIsoCode = $site->getDefaultLanguage()->getLocale()->getLanguageCode();
        if ($defaultLanguageIsoCode === '') {
            $availableLanguages = SiteUtility::getAvailableDefaultLanguages();
            $defaultLanguageIsoCode = array_key_first($availableLanguages) ?? 'en';
        }
        $answer = $this->requestService->sendDataRequest(
            'pageTree',
            [],
            $input->getPlainPrompt(),
            $defaultLanguageIsoCode,
            [
                'text' => $textAi,
            ],
        );
        if ($answer->getType() === 'Error') {
            $this->addFlashMessage(
                $answer->getResponseData()['message'],
                LocalizationUtility::translate('aiSuite.module.errorFetchingPagetreeResponse.title', 'ai_suite'),
                ContextualFeedbackSeverity::ERROR
            );
            return $this->redirect('pageStructure');
        }
        $input->setAiResult($answer->getResponseData()['pagetreeResult']);
        $this->moduleTemplate->assignMultiple([
            'input' => $input,
            'pagesSelect' => $this->getPagesInWebMount(),
            'textGenerationLibraries' => LibraryUtility::prepareLibraries(json_decode($input->getTextGenerationLibraries(), true), $textAi),
        ]);
        $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/pages/validation.js');
        $this->addFlashMessage(
            LocalizationUtility::translate('aiSuite.module.fetchingDataSuccessful.message', 'ai_suite'),
            LocalizationUtility::translate('aiSuite.module.fetchingDataSuccessful.title', 'ai_suite'),
        );
        return $this->htmlResponse($this->moduleTemplate->render());
    }

    public function createValidatedPageStructureAction(PageStructureInput $input): ResponseInterface
    {
        $selectedPageTreeContent = $this->request->getParsedBody()['selectedPageTreeContent'] ?? '';
        $input->setAiResult(json_decode($selectedPageTreeContent, true));
        $this->pageStructureFactory->createFromArray($input->getAiResult(), $input->getStartStructureFromPid());
        BackendUtility::setUpdateSignal('updatePageTree');
        $this->addFlashMessage(
            LocalizationUtility::translate('aiSuite.module.pagetreeGenerationSuccessful.title', 'ai_suite'),
            LocalizationUtility::translate('aiSuite.module.pagetreeGenerationSuccessful.title', 'ai_suite'),
        );
        return $this->redirect('overview');
    }

    private function getPagesInWebMount(): array
    {
        $foundPages = $this->pagesRepository->findAiStructurePages('uid');
        if (BackendUserUtility::isAdmin()) {
            $pagesSelect = [
                -1 => LocalizationUtility::translate('aiSuite.module.pages.newRootPage', 'ai_suite')
            ];
        }
        foreach ($foundPages as $page) {
            $pageInWebMount = BackendUserUtility::getBackendUser()->isInWebMount($page['uid']);
            if ($pageInWebMount !== null) {
                $pagesSelect[$page['uid']] = $page['title'];
            }
        }
        return $pagesSelect ?? [];
    }
}
