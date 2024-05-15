<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Controller\Ajax;

use AutoDudes\AiSuite\Exception\AiSuiteServerException;
use AutoDudes\AiSuite\Exception\FetchedContentFailedException;
use AutoDudes\AiSuite\Exception\NewsContentNotAvailableException;
use AutoDudes\AiSuite\Service\MetadataService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Routing\UnableToLinkToPageException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class MetadataController
{
    protected MetadataService $metadataService;

    protected LoggerInterface $logger;

    public function __construct(
        MetadataService $metadataService,
        LoggerInterface $logger
    ) {
        $this->metadataService = $metadataService;
        $this->logger = $logger;
    }

    public function generateMetaDescriptionAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->generateSuggestions($request, 'MetaDescription');
    }

    public function generateNewsMetaDescriptionAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->generateSuggestions($request, 'NewsMetaDescription');
    }

    public function generateKeywordsAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->generateSuggestions($request, 'Keywords');
    }

    public function generateNewsKeywordsAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->generateSuggestions($request, 'NewsKeywords');
    }

    public function generatePageTitleAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->generateSuggestions($request, 'PageTitle');
    }

    public function generateTwitterTitleAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->generateSuggestions($request, 'TwitterTitle');
    }

    public function generateOgTitleAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->generateSuggestions($request, 'OgTitle');
    }

    public function generateNewsAlternativeTitleAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->generateSuggestions($request, 'NewsAlternativeTitle');
    }

    public function generateOgDescriptionAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->generateSuggestions($request, 'OgDescription');
    }

    public function generateTwitterDescriptionAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->generateSuggestions($request, 'TwitterDescription');
    }

    public function generateAlternativeAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->generateSuggestions($request, 'Alternative');
    }

    public function generateTitleAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->generateSuggestions($request, 'Title');
    }

    private function generateSuggestions(ServerRequestInterface $request, string $type): Response
    {
        $response = new Response();
        try {
            $response->getBody()->write(
                json_encode(
                    [
                        'success' => true,
                        'output' => $this->metadataService->getContentForSuggestions($request, $type)
                    ]
                )
            );
            return $response;
        } catch(UnableToLinkToPageException|FetchedContentFailedException|SiteNotFoundException $e) {
            $this->logError($e->getMessage(), $response, 404);
        } catch (AiSuiteServerException $e) {
            $this->logError($e->getMessage(), $response);
        } catch (NewsContentNotAvailableException $e) {
            $this->logError(LocalizationUtility::translate('LLL:EXT:ai_suite/Resources/Private/Language/locallang.xlf:AiSuite.NewsContentNotFound'), $response, 404);
        }
        return $response;
    }

    private function logError(string $errorMessage, Response $response, int $statusCode = 400): void
    {
        $this->logger->error($errorMessage);
        $response->withStatus($statusCode);
        $response->getBody()->write(json_encode(['success' => false, 'status' => $statusCode,'error' => $errorMessage]));
    }
}
