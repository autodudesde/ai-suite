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

use AutoDudes\AiSuite\Enumeration\GenerationLibrariesEnumeration;
use AutoDudes\AiSuite\Service\BackendUserService;
use AutoDudes\AiSuite\Service\LibraryService;
use AutoDudes\AiSuite\Service\PromptTemplateService;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\SiteService;
use AutoDudes\AiSuite\Service\TranslationService;
use AutoDudes\AiSuite\Service\XliffService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Package\Exception\UnknownPackageException;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Exception;

#[AsController]
class AgenciesController extends AbstractBackendController
{
    protected LoggerInterface $logger;
    protected XliffService $xliffService;

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
        XliffService $xliffService,
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
            $translationService
        );
        $this->xliffService = $xliffService;
        $this->logger = $logger;
    }

    /**
     * @throws Exception
     */
    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $this->initialize($request);
        $identifier = $request->getAttribute('route')->getOption('_identifier');
        return match ($identifier) {
            'ai_suite_agencies_translate_xlf' => $this->translateXlfAction(),
            'ai_suite_agencies_validate_xlf' => $this->validateXlfAction(),
            'ai_suite_agencies_write_xlf' => $this->writeXlfAction(),
            default => $this->overviewAction(),
        };
    }

    public function overviewAction(): ResponseInterface
    {
        return $this->view->renderResponse('Agencies/Overview');
    }

    public function translateXlfAction(): ResponseInterface
    {
        $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/agencies/creation.js');
        $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibrariesEnumeration::GOOGLE_TRANSLATE,'translate', ['translate']);
        $this->view->assignMultiple([
            'allLanguagesList' => $this->siteService->getAvailableLanguages(),
            'translateGenerationLibraries' => $this->libraryService->prepareLibraries($librariesAnswer->getResponseData()['translateGenerationLibraries']),
            'paidRequestsAvailable' => $librariesAnswer->getResponseData()['paidRequestsAvailable']
        ]);
        return $this->view->renderResponse('Agencies/TranslateXlf');
    }

    public function validateXlfAction(): ResponseInterface
    {
        $parsedBody = $this->request->getParsedBody();
        if (empty($parsedBody['destinationLanguage'])) {
            $this->view->addFlashMessage(
                $this->translationService->translate('aiSuite.module.errorTargetLangMissing.message'),
                $this->translationService->translate('aiSuite.module.errorTargetLangMissing.title'),
                ContextualFeedbackSeverity::WARNING
            );
            return $this->translateXlfAction();
        }
        if ($parsedBody['destinationLanguage'] === 'missingProperties') {
            try {
                $destinationFile = $this->xliffService->readXliff($parsedBody['extensionKey'], $parsedBody['destinationLanguage'] . '.' . $parsedBody['filename'], false);
            } catch (FileNotFoundException $exception) {
                $this->logger->error($exception->getMessage());
                $this->view->addFlashMessage(
                    $this->translationService->translate('aiSuite.module.destinationFileNotFound.message.prefix') .
                    $parsedBody['destinationLanguage'] . '.' . $parsedBody['filename'] .
                    $this->translationService->translate('aiSuite.module.destinationFileNotFound.message.suffix'),
                    $this->translationService->translate('aiSuite.module.destinationFileNotFound.title'),
                    ContextualFeedbackSeverity::WARNING
                );
                $parsedBody['translationMode'] = 'all';
            } catch (\Exception $exception) {
                $this->logger->error($exception->getMessage());
                $this->view->addFlashMessage(
                    $exception->getMessage(),
                    $this->translationService->translate('AiSuite.notification.generation.error'),
                    ContextualFeedbackSeverity::ERROR
                );
                return $this->translateXlfAction();
            }
        }
        try {
            $translateAi = !empty($parsedBody['libraries']['translateGenerationLibrary']) ? $parsedBody['libraries']['translateGenerationLibrary'] : '';
            $neededTranslations = $this->xliffService->getTranslateValues(
                $parsedBody['extensionKey'],
                $parsedBody['filename'],
                $parsedBody['destinationLanguage'],
                $parsedBody['translationMode']
            );
            if (count($neededTranslations) === 0) {
                $this->view->addFlashMessage(
                    $this->translationService->translate('aiSuite.module.noTranslationsNeeded.message'),
                    $this->translationService->translate('aiSuite.module.noTranslationsNeeded.title'),
                    ContextualFeedbackSeverity::WARNING
                );
                return $this->translateXlfAction();
            }
            $answer = $this->requestService->sendDataRequest(
                'translate',
                [
                    'source_lang' => 'en',
                    'target_lang' => $parsedBody['destinationLanguage'],
                    'translation_content' => $neededTranslations,
                ],
                '',
                '',
                [
                    'translate' => $translateAi,
                ]
            );
            if ($answer->getType() === 'Error') {
                $this->view->addFlashMessage(
                    $answer->getResponseData()['message'],
                    $this->translationService->translate('aiSuite.module.errorFetchingTranslationResponse.title'),
                    ContextualFeedbackSeverity::ERROR
                );
                return $this->translateXlfAction();
            }
            $translations = $answer->getResponseData()['translations'];
            $originalValues = $this->xliffService->readXliff($parsedBody['extensionKey'], $parsedBody['filename'])->getFormatedData();
            foreach ($originalValues as $origKey => $origValue) {
                if (array_key_exists($origKey, $translations)) {
                    $originalValues[$origKey]['translated'] = $translations[$origKey];
                } else {
                    unset($originalValues[$origKey]);
                }
            }

            $this->view->assignMultiple([
                'allLanguagesList' => $this->siteService->getAvailableLanguages(),
                'translations' => $translations,
                'originalValues' => $originalValues,
                'fileData' => json_encode([
                    'extensionKey' => $parsedBody['extensionKey'],
                    'filename' => $parsedBody['filename'],
                    'destinationLanguage' => $parsedBody['destinationLanguage'],
                    'translationMode' => $parsedBody['translationMode'],
                ]),
            ]);
            $this->view->addFlashMessage(
                $this->translationService->translate('aiSuite.module.fetchingDataSuccessful.message'),
                $this->translationService->translate('aiSuite.module.fetchingDataSuccessful.title'),
            );
            return $this->view->renderResponse('Agencies/ValidateXlfResult');
        } catch (UnknownPackageException $exception) {
            $this->logger->error($exception->getMessage());
            $this->view->addFlashMessage(
                $exception->getMessage(),
                $this->translationService->translate('aiSuite.module.errorExtensionNotFound.title'),
                ContextualFeedbackSeverity::ERROR
            );
            return $this->translateXlfAction();
        } catch (FileNotFoundException|Exception $exception) {
            $this->logger->error($exception->getMessage());
            $this->view->addFlashMessage(
                $exception->getMessage(),
                $this->translationService->translate('aiSuite.module.errorFileNotFound.title'),
                ContextualFeedbackSeverity::ERROR
            );
            return $this->translateXlfAction();
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
            $this->view->addFlashMessage(
                $exception->getMessage(),
                $this->translationService->translate('AiSuite.notification.generation.error'),
                ContextualFeedbackSeverity::ERROR
            );
            return $this->translateXlfAction();
        }
    }

    /**
     * @throws UnknownPackageException
     */
    public function writeXlfAction(): ResponseInterface
    {
        $parsedBody = $this->request->getParsedBody();

        if ($this->xliffService->writeXliff(json_decode($parsedBody['fileData'], true), $parsedBody['translations'])) {
            $this->view->addFlashMessage(
                $this->translationService->translate('aiSuite.module.translationGenerationSuccessful.message'),
                $this->translationService->translate('aiSuite.module.translationGenerationSuccessful.title'),
            );
        } else {
            $this->view->addFlashMessage(
                $this->translationService->translate('aiSuite.module.errorWritingTranslationFile.message'),
                $this->translationService->translate('AiSuite.notification.generation.error'),
                ContextualFeedbackSeverity::ERROR
            );
        }
        return $this->overviewAction();
    }
}
