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

use AutoDudes\AiSuite\Enumeration\GenerationLibraryEnumeration;
use AutoDudes\AiSuite\Service\AiSuiteContext;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\TranslationService;
use AutoDudes\AiSuite\Service\XliffService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Package\Exception\UnknownPackageException;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

#[AsController]
class AgencyController extends AbstractBackendController
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
        protected readonly XliffService $xliffService,
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

        return match ($identifier) {
            'ai_suite_agencies_translate_xlf' => $this->translateXlfAction(),
            'ai_suite_agencies_validate_xlf' => $this->validateXlfAction(),
            'ai_suite_agencies_write_xlf' => $this->writeXlfAction(),
            default => $this->overviewAction(),
        };
    }

    public function overviewAction(): ResponseInterface
    {
        return $this->view->renderResponse('Agency/Overview');
    }

    public function translateXlfAction(): ResponseInterface
    {
        $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/agency/creation.js');
        $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibraryEnumeration::TRANSLATE, 'translate', ['text']);
        $this->view->assignMultiple([
            'allLanguagesList' => $this->aiSuiteContext->siteService->getAvailableLanguages(),
            'textGenerationLibraries' => $this->aiSuiteContext->libraryService->prepareLibraries($librariesAnswer->getResponseData()['textGenerationLibraries']),
            'paidRequestsAvailable' => $librariesAnswer->getResponseData()['paidRequestsAvailable'],
        ]);

        return $this->view->renderResponse('Agency/TranslateXlf');
    }

    public function validateXlfAction(): ResponseInterface
    {
        $parsedBody = (array) $this->request->getParsedBody();
        if (empty($parsedBody['destinationLanguage'])) {
            $this->view->addFlashMessage(
                $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.errorTargetLangMissing.message'),
                $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.errorTargetLangMissing.title'),
                ContextualFeedbackSeverity::WARNING
            );

            return $this->translateXlfAction();
        }
        if ('missingProperties' === $parsedBody['translationMode']) {
            try {
                $destinationFile = $this->xliffService->readXliff($parsedBody['extensionKey'], $parsedBody['destinationLanguage'].'.'.$parsedBody['filename'], false);
            } catch (FileNotFoundException $exception) {
                $this->logger->error($exception->getMessage());
                $this->view->addFlashMessage(
                    $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.destinationFileNotFound.message.prefix')
                    .$parsedBody['destinationLanguage'].'.'.$parsedBody['filename']
                    .$this->aiSuiteContext->localizationService->translate('module:aiSuite.module.destinationFileNotFound.message.suffix'),
                    $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.destinationFileNotFound.title'),
                    ContextualFeedbackSeverity::WARNING
                );
                $parsedBody['translationMode'] = 'all';
            } catch (\Exception $exception) {
                $this->logger->error($exception->getMessage());
                $this->view->addFlashMessage(
                    $exception->getMessage(),
                    $this->aiSuiteContext->localizationService->translate('aiSuite.notification.generation.error'),
                    ContextualFeedbackSeverity::ERROR
                );

                return $this->translateXlfAction();
            }
        }

        try {
            $translateAi = !empty($parsedBody['libraries']['textGenerationLibrary']) ? $parsedBody['libraries']['textGenerationLibrary'] : '';
            $sourceFile = $this->xliffService->readXliff($parsedBody['extensionKey'], $parsedBody['filename']);
            $neededTranslations = $this->xliffService->getTranslateValues(
                $parsedBody['extensionKey'],
                $parsedBody['filename'],
                $parsedBody['destinationLanguage'],
                $parsedBody['translationMode']
            );
            if (0 === count($neededTranslations)) {
                $this->view->addFlashMessage(
                    $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.noTranslationsNeeded.message'),
                    $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.noTranslationsNeeded.title'),
                    ContextualFeedbackSeverity::WARNING
                );

                return $this->translateXlfAction();
            }
            $answer = $this->requestService->sendDataRequest(
                'translate',
                [
                    'source_lang' => $sourceFile->getSourceLanguage(),
                    'target_lang' => $parsedBody['destinationLanguage'],
                    'translation_content' => $neededTranslations,
                ],
                '',
                '',
                [
                    'translate' => $translateAi,
                ]
            );
            if ('Error' === $answer->getType()) {
                $this->view->addFlashMessage(
                    $answer->getResponseData()['message'],
                    $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.errorFetchingTranslationResponse.title'),
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
                'allLanguagesList' => $this->aiSuiteContext->siteService->getAvailableLanguages(),
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
                $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.fetchingDataSuccessful.message'),
                $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.fetchingDataSuccessful.title'),
            );

            return $this->view->renderResponse('Agency/ValidateXlfResult');
        } catch (UnknownPackageException $exception) {
            $this->logger->error($exception->getMessage());
            $this->view->addFlashMessage(
                $exception->getMessage(),
                $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.errorExtensionNotFound.title'),
                ContextualFeedbackSeverity::ERROR
            );

            return $this->translateXlfAction();
        } catch (Exception|FileNotFoundException $exception) {
            $this->logger->error($exception->getMessage());
            $this->view->addFlashMessage(
                $exception->getMessage(),
                $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.errorFileNotFound.title'),
                ContextualFeedbackSeverity::ERROR
            );

            return $this->translateXlfAction();
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
            $this->view->addFlashMessage(
                $exception->getMessage(),
                $this->aiSuiteContext->localizationService->translate('aiSuite.notification.generation.error'),
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
        $parsedBody = (array) $this->request->getParsedBody();

        if ($this->xliffService->writeXliff(json_decode($parsedBody['fileData'], true), $parsedBody['translations'])) {
            $this->view->addFlashMessage(
                $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.translationGenerationSuccessful.message'),
                $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.translationGenerationSuccessful.title'),
            );
        } else {
            $this->view->addFlashMessage(
                $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.errorWritingTranslationFile.message'),
                $this->aiSuiteContext->localizationService->translate('aiSuite.notification.generation.error'),
                ContextualFeedbackSeverity::ERROR
            );
        }

        return $this->overviewAction();
    }
}
