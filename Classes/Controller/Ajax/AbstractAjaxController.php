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
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

abstract class AbstractAjaxController
{
    protected SendRequestService $requestService;
    protected LoggerInterface $logger;

    public function __construct()
    {
        $this->requestService = GeneralUtility::makeInstance(SendRequestService::class);
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
    }

    protected function getContentFromTemplate(
        ServerRequestInterface $request,
        string $templateName,
        string $templateRootPath,
        string $controllerName,
        array $params = []
    ) {
        $partialRootPaths = ['EXT:ai_suite/Resources/Private/Partials/'];
        $templateRootPaths = [$templateRootPath];
        $standaloneView = GeneralUtility::makeInstance(StandaloneView::class);
        $standaloneView->setTemplateRootPaths($templateRootPaths);
        $standaloneView->setPartialRootPaths($partialRootPaths);
        $standaloneView->getRenderingContext()->setControllerName($controllerName);
        $standaloneView->setTemplate($templateName);
        $standaloneView->assignMultiple($params);

        $moduleTemplate = GeneralUtility::makeInstance(ModuleTemplateFactory::class)->create($request);
        $moduleTemplate->getDocHeaderComponent()->disable();
        $moduleTemplate->setContent($standaloneView->render());
        return $moduleTemplate->renderContent();
    }

    protected function logError(string $errorMessage, Response $response, int $statusCode = 400): void
    {
        $this->logger->error($errorMessage);
        $response->withStatus($statusCode);
        $response->getBody()->write(json_encode(['success' => false, 'status' => $statusCode,'error' => $errorMessage]));
    }
}
