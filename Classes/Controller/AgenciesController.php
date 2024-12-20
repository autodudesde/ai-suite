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

use AutoDudes\AiSuite\Domain\Model\Dto\XlfInput;
use AutoDudes\AiSuite\Enumeration\GenerationLibrariesEnumeration;
use AutoDudes\AiSuite\Exception\EmptyXliffException;
use AutoDudes\AiSuite\Utility\SiteUtility;
use AutoDudes\AiSuite\Utility\XliffUtility;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use TYPO3\CMS\Core\Package\Exception\UnknownPackageException;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class AgenciesController extends AbstractBackendController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function overviewAction(): ResponseInterface
    {
        return $this->htmlResponse($this->moduleTemplate->render('Agencies/Overview'));
    }

    public function translateXlfAction(): ResponseInterface
    {
        $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/agencies/creation.js');
        $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibrariesEnumeration::GOOGLE_TRANSLATE,'translate', ['translate']);
        if ($librariesAnswer->getType() === 'Error') {
            $this->addFlashMessage(
                $librariesAnswer->getResponseData()['message'],
                LocalizationUtility::translate('aiSuite.module.errorFetchingLibraries.title', 'ai_suite'),
                ContextualFeedbackSeverity::ERROR
            );
            return $this->redirect('overview');
        }
        $this->moduleTemplate->assignMultiple([
            'allLanguagesList' => SiteUtility::getAvailableLanguages(),
            'input' => XlfInput::createEmpty(),
            'translateGenerationLibraries' => $librariesAnswer->getResponseData()['translateGenerationLibraries'],
            'paidRequestsAvailable' => $librariesAnswer->getResponseData()['paidRequestsAvailable']
        ]);
        return $this->htmlResponse($this->moduleTemplate->render('Agencies/TranslateXlf'));
    }

    public function validateXlfResultAction(XlfInput $input): ResponseInterface
    {
        if (empty($input->getDestinationLanguage())) {
            $this->addFlashMessage(
                LocalizationUtility::translate('aiSuite.module.errorTargetLangMissing.message', 'ai_suite'),
                LocalizationUtility::translate('aiSuite.module.errorTargetLangMissing.title', 'ai_suite'),
                ContextualFeedbackSeverity::WARNING
            );
            return $this->redirect('translateXlf');
        }
        if ($input->getTranslationMode() === 'missingProperties') {
            try {
                $destinationFile = XliffUtility::readXliff($input->getExtensionKey(), $input->getDestinationLanguage() . '.' . $input->getFilename(), false);
            } catch (FileNotFoundException $exception) {
                $this->logger->error($exception->getMessage());
                $this->addFlashMessage(
                    LocalizationUtility::translate('aiSuite.module.destinationFileNotFound.message.prefix', 'ai_suite') .
                    $input->getDestinationLanguage() . '.' . $input->getFilename() .
                    LocalizationUtility::translate('aiSuite.module.destinationFileNotFound.message.suffix', 'ai_suite'),
                    LocalizationUtility::translate('aiSuite.module.destinationFileNotFound.title', 'ai_suite'),
                    ContextualFeedbackSeverity::WARNING
                );
                $input->setTranslationMode('all');
            } catch (\Exception $exception) {
                $this->logger->error($exception->getMessage());
                $this->addFlashMessage(
                    $exception->getMessage(),
                    LocalizationUtility::translate('AiSuite.notification.generation.error', 'ai_suite'),
                    ContextualFeedbackSeverity::ERROR
                );
                return $this->redirect('translateXlf');
            }
        }
        try {
            $translateAi = !empty($this->request->getParsedBody()['libraries']['translateGenerationLibrary']) ? $this->request->getParsedBody()['libraries']['translateGenerationLibrary'] : '';
            $neededTranslations = XliffUtility::getTranslateValues($input);
            if (count($neededTranslations) === 0) {
                $this->addFlashMessage(
                    LocalizationUtility::translate('aiSuite.module.noTranslationsNeeded.message', 'ai_suite'),
                    LocalizationUtility::translate('aiSuite.module.noTranslationsNeeded.title', 'ai_suite'),
                    ContextualFeedbackSeverity::WARNING
                );
                return $this->redirect('translateXlf');
            }
            $answer = $this->requestService->sendDataRequest(
                'translate',
                [
                    'source_lang' => 'en',
                    'target_lang' => $input->getDestinationLanguage(),
                    'translation_content' => $neededTranslations,
                ],
                '',
                '',
                [
                    'translate' => $translateAi,
                ]
            );
            if ($answer->getType() === 'Error') {
                $this->addFlashMessage(
                    $answer->getResponseData()['message'],
                    LocalizationUtility::translate('aiSuite.module.errorFetchingTranslationResponse.title', 'ai_suite'),
                    ContextualFeedbackSeverity::ERROR
                );
                return $this->redirect('translateXlf');
            }
            $translations = $answer->getResponseData()['translations'];
            $input->setTranslations($translations);
            $originalValues = XliffUtility::readXliff($input->getExtensionKey(), $input->getFilename())->getFormatedData();
            foreach ($originalValues as $origKey => $origValue) {
                if (array_key_exists($origKey, $translations)) {
                    $originalValues[$origKey]['translated'] = $translations[$origKey];
                } else {
                    unset($originalValues[$origKey]);
                }
            }

            $this->moduleTemplate->assignMultiple([
                'allLanguagesList' => SiteUtility::getAvailableLanguages(),
                'input' => $input,
                'originalValues' => $originalValues
            ]);
            $this->addFlashMessage(
                LocalizationUtility::translate('aiSuite.module.fetchingDataSuccessful.message', 'ai_suite'),
                LocalizationUtility::translate('aiSuite.module.fetchingDataSuccessful.title', 'ai_suite'),
            );
            return $this->htmlResponse($this->moduleTemplate->render('Agencies/ValidateXlfResult'));
        } catch (UnknownPackageException $exception) {
            $this->logger->error($exception->getMessage());
            $this->addFlashMessage(
                $exception->getMessage(),
                LocalizationUtility::translate('aiSuite.module.errorExtensionNotFound.title', 'ai_suite'),
                ContextualFeedbackSeverity::ERROR
            );
            return $this->redirect('translateXlf');
        } catch (FileNotFoundException|Exception $exception) {
            $this->logger->error($exception->getMessage());
            $this->addFlashMessage(
                $exception->getMessage(),
                LocalizationUtility::translate('aiSuite.module.errorFileNotFound.title', 'ai_suite'),
                ContextualFeedbackSeverity::ERROR
            );
            return $this->redirect('translateXlf');
        } catch (EmptyXliffException $exception) {
            $this->logger->error($exception->getMessage());
            $this->addFlashMessage(
                $exception->getMessage(),
                LocalizationUtility::translate('aiSuite.module.sourceXliffFileEmpty.message', 'ai_suite'),
                ContextualFeedbackSeverity::ERROR
            );
            return $this->redirect('translateXlf');
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
            $this->addFlashMessage(
                $exception->getMessage(),
                LocalizationUtility::translate('AiSuite.notification.generation.error', 'ai_suite'),
                ContextualFeedbackSeverity::ERROR
            );
            return $this->redirect('translateXlf');
        }
    }

    /**
     * @throws UnknownPackageException
     */
    public function writeXlfAction(XlfInput $input): ResponseInterface
    {
        if (XliffUtility::writeXliff($input)) {
            $this->addFlashMessage(
                LocalizationUtility::translate('aiSuite.module.translationGenerationSuccessful.message', 'ai_suite'),
                LocalizationUtility::translate('aiSuite.module.translationGenerationSuccessful.title', 'ai_suite'),
            );
        } else {
            $this->addFlashMessage(
                LocalizationUtility::translate('aiSuite.module.errorWritingTranslationFile.message', 'ai_suite'),
                LocalizationUtility::translate('AiSuite.notification.generation.error', 'ai_suite'),
                ContextualFeedbackSeverity::ERROR
            );
        }
        return $this->redirect('overview');
    }
}
