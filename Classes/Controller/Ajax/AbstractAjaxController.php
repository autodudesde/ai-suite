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

namespace AutoDudes\AiSuite\Controller\Ajax;

use AutoDudes\AiSuite\Service\SendRequestService;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

abstract class AbstractAjaxController
{
    protected SendRequestService $requestService;
    protected ViewFactoryInterface $viewFactory;
    protected LoggerInterface $logger;

    public function __construct()
    {
        $this->requestService = GeneralUtility::makeInstance(SendRequestService::class);
        $this->viewFactory = GeneralUtility::makeInstance(ViewFactoryInterface::class);
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
    }

    protected function getContentFromTemplate(
        ServerRequestInterface $request,
        string $templateName,
        string $templateRootPath,
        string $cssFilePath,
        array $params = []
    ) {
        $viewFactoryData = new ViewFactoryData(
            templateRootPaths: [$templateRootPath],
            partialRootPaths: ['EXT:ai_suite/Resources/Private/Partials'],
            layoutRootPaths: ['EXT:ai_suite/Resources/Private/Layouts'],
            request: $request,
        );
        $view = $this->viewFactory->create($viewFactoryData);
        $params['inlineStyles'] = !empty($cssFilePath) ? file_get_contents(GeneralUtility::getFileAbsFileName($cssFilePath)) : '';
        $view->assignMultiple($params);
        return $view->render($templateName);
    }

    protected function logError(string $errorMessage, Response $response, int $statusCode = 400): void
    {
        $this->logger->error($errorMessage);
        $response->withStatus($statusCode);
        $response->getBody()->write(json_encode(['success' => false, 'status' => $statusCode,'error' => $errorMessage]));
    }
}
