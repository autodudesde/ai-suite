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

use AutoDudes\AiSuite\Controller\Trait\AjaxResponseTrait;
use AutoDudes\AiSuite\Service\AiSuiteContext;
use AutoDudes\AiSuite\Service\GlossarService;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\TranslationService;
use AutoDudes\AiSuite\Service\UuidService;
use AutoDudes\AiSuite\Service\ViewFactoryService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;

#[AsController]
class GlossarController extends AbstractBackendController
{
    use AjaxResponseTrait;

    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        UriBuilder $uriBuilder,
        PageRenderer $pageRenderer,
        FlashMessageService $flashMessageService,
        SendRequestService $requestService,
        TranslationService $translationService,
        EventDispatcher $eventDispatcher,
        AiSuiteContext $aiSuiteContext,
        protected readonly GlossarService $glossarService,
        protected readonly DataHandler $dataHandler,
        protected readonly ViewFactoryService $viewFactoryService,
        protected readonly UuidService $uuidService,
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

    public function synchronizeAction(ServerRequestInterface $request): ResponseInterface
    {
        $success = false;
        $response = new Response();

        try {
            $success = $this->glossarService->syncDeeplGlossar((int) ((array) $request->getParsedBody())['pid']);
        } catch (\Throwable $exception) {
            $this->logger->error($exception->getMessage());
        }

        $response->getBody()->write(
            (string) json_encode(
                [
                    'success' => $success,
                ]
            )
        );

        return $response;
    }

    public function fetchGlossariesForPageTranslationAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        $availableGlossaries = [];

        try {
            $parsedBody = (array) $request->getParsedBody();
            $sourceLanguage = $parsedBody['sourceLanguageId'] ?? [];
            $targetLanguage = $parsedBody['targetLanguageId'] ?? [];
            $sourceLanguageParts = explode('__', $sourceLanguage);
            $targetLanguageParts = explode('__', $targetLanguage);
            $sourceLanguageId = isset($sourceLanguageParts[1]) ? (int) $sourceLanguageParts[1] : 0;
            $targetLanguageId = isset($targetLanguageParts[1]) ? (int) $targetLanguageParts[1] : 0;
            $sourceLanguageIso = $sourceLanguageParts[0] ?? '';
            $targetLanguageIso = $targetLanguageParts[0] ?? '';
            $textAiModel = $parsedBody['textAiModel'] ?? '';

            if (!empty($textAiModel) && 'GoogleTranslate' !== $textAiModel && $sourceLanguageId !== $targetLanguageId) {
                $availableGlossaries = $this->glossarService->getAvailableGlossariesForFileTranslation(
                    $sourceLanguageId,
                    $targetLanguageId,
                    $sourceLanguageIso,
                    $targetLanguageIso,
                    $textAiModel
                );
            }
        } catch (\Throwable $exception) {
            $this->logger->error($exception->getMessage());
        }

        $response->getBody()->write(
            (string) json_encode(
                [
                    'glossaries' => $availableGlossaries,
                ]
            )
        );

        return $response;
    }

    public function fetchGlossariesForFileTranslationAction(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        $availableGlossaries = [];

        try {
            $parsedBody = (array) $request->getParsedBody();
            $sourceLanguage = $parsedBody['sourceLanguageId'] ?? [];
            $targetLanguage = $parsedBody['targetLanguageId'] ?? [];
            $sourceLanguageParts = explode('__', $sourceLanguage);
            $targetLanguageParts = explode('__', $targetLanguage);
            $sourceLanguageId = isset($sourceLanguageParts[1]) ? (int) $sourceLanguageParts[1] : 0;
            $targetLanguageId = isset($targetLanguageParts[1]) ? (int) $targetLanguageParts[1] : 0;
            $sourceLanguageIso = $sourceLanguageParts[0] ?? '';
            $targetLanguageIso = $targetLanguageParts[0] ?? '';
            $textAiModel = $parsedBody['textAiModel'] ?? '';

            if (!empty($textAiModel) && 'GoogleTranslate' !== $textAiModel && $sourceLanguageId !== $targetLanguageId) {
                $availableGlossaries = $this->glossarService->getAvailableGlossariesForFileTranslation(
                    $sourceLanguageId,
                    $targetLanguageId,
                    $sourceLanguageIso,
                    $targetLanguageIso,
                    $textAiModel
                );
            }
        } catch (\Throwable $exception) {
            $this->logger->error($exception->getMessage());
        }

        $response->getBody()->write(
            (string) json_encode(
                [
                    'glossaries' => $availableGlossaries,
                ]
            )
        );

        return $response;
    }
}
