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

use AutoDudes\AiSuite\Service\BackendUserService;
use AutoDudes\AiSuite\Service\GlossarService;
use AutoDudes\AiSuite\Service\LibraryService;
use AutoDudes\AiSuite\Service\PromptTemplateService;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\SiteService;
use AutoDudes\AiSuite\Service\TranslationService;
use AutoDudes\AiSuite\Service\UuidService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;

#[AsController]
class GlossarController extends AbstractAjaxController
{
    protected ResourceFactory $fileFactory;
    protected Filesystem $filesystem;
    protected GlossarService $glossarService;
    protected DataHandler $dataHandler;

    public function __construct(
        BackendUserService    $backendUserService,
        SendRequestService    $requestService,
        PromptTemplateService $promptTemplateService,
        LibraryService        $libraryService,
        UuidService           $uuidService,
        SiteService           $siteService,
        TranslationService    $translationService,
        LoggerInterface       $logger,
        EventDispatcher       $eventDispatcher,
        GlossarService        $glossarService,
        DataHandler           $dataHandler,
    ) {
        parent::__construct(
            $backendUserService,
            $requestService,
            $promptTemplateService,
            $libraryService,
            $uuidService,
            $siteService,
            $translationService,
            $logger,
            $eventDispatcher
        );
        $this->glossarService = $glossarService;
        $this->dataHandler = $dataHandler;
    }

    public function synchronizeAction(ServerRequestInterface $request): ResponseInterface
    {
        $success = false;
        $response = new Response();
        try {
            $success = $this->glossarService->syncDeeplGlossar((int)$request->getParsedBody()['pid']);
        } catch (\Throwable $exception) {
            $this->logger->error($exception->getMessage());
        }

        $response->getBody()->write(
            json_encode(
                [
                    'success' => $success
                ]
            )
        );
        return $response;
    }
}
