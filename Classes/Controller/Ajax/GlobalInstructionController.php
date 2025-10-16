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
use AutoDudes\AiSuite\Service\GlobalInstructionService;
use AutoDudes\AiSuite\Service\LibraryService;
use AutoDudes\AiSuite\Service\PromptTemplateService;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\SiteService;
use AutoDudes\AiSuite\Service\TranslationService;
use AutoDudes\AiSuite\Service\UuidService;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;

#[AsController]
class GlobalInstructionController extends AbstractAjaxController
{
    public function __construct(
        BackendUserService    $backendUserService,
        SendRequestService    $requestService,
        PromptTemplateService $promptTemplateService,
        GlobalInstructionService $globalInstructionService,
        LibraryService        $libraryService,
        UuidService           $uuidService,
        SiteService           $siteService,
        TranslationService    $translationService,
        ViewFactoryInterface  $viewFactory,
        LoggerInterface       $logger,
        EventDispatcher       $eventDispatcher,
    ) {
        parent::__construct(
            $backendUserService,
            $requestService,
            $promptTemplateService,
            $globalInstructionService,
            $libraryService,
            $uuidService,
            $siteService,
            $translationService,
            $viewFactory,
            $logger,
            $eventDispatcher
        );
    }

    public function previewAction(ServerRequestInterface $request): ResponseInterface
    {
        $success = false;
        $output = '';
        $response = new Response();

        try {
            $parsedBody = $request->getParsedBody();
            $params = [
                'globalInstructions' => ''
            ];
            if ($parsedBody['context'] === 'files' && !empty($parsedBody['targetFolder'])) {
                $params['globalInstructions'] = $this->globalInstructionService->buildGlobalInstruction($parsedBody['context'], $parsedBody['scope'], null, $parsedBody['targetFolder']);
            }
            if ($parsedBody['context'] === 'pages' && !empty($parsedBody['pageId'])) {
                $params['globalInstructions'] = $this->globalInstructionService->buildGlobalInstruction($parsedBody['context'], $parsedBody['scope'], $parsedBody['pageId']);
            }
            $output = $this->getContentFromTemplate(
                $request,
                'Preview',
                'EXT:ai_suite/Resources/Private/Templates/Ajax/GlobalInstruction/',
                $params
            );
            $success = true;
        } catch (\Throwable $exception) {
            $this->logger->error($exception->getMessage());
        }
        $response->getBody()->write(
            json_encode(
                [
                    'success' => $success,
                    'output' => $output
                ]
            )
        );
        return $response;
    }
}
