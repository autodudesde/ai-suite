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

namespace AutoDudes\AiSuite\Controller\Ajax;

use AutoDudes\AiSuite\Service\BackendUserService;
use AutoDudes\AiSuite\Service\SendRequestService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Core\Http\JsonResponse;

#[AsController]
class CreditsAjaxController
{
    public function __construct(
        protected readonly SendRequestService $requestService,
        protected readonly BackendUserService $backendUserService,
        protected readonly LoggerInterface $logger,
    ) {}

    public function refreshAction(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->backendUserService->checkPermissions('tx_aisuite_features:enable_toolbar_stats_item')) {
            return new JsonResponse(['status' => 'forbidden'], 403);
        }

        try {
            $answer = $this->requestService->sendDataRequest('getRequestsState');
        } catch (\Throwable $e) {
            $this->logger->warning('AI Suite: could not refresh credit state for the toolbar', ['exception' => $e]);

            return new JsonResponse(['status' => 'unavailable'], 502);
        }

        if ('RequestsState' !== $answer->getType()) {
            return new JsonResponse(['status' => 'unavailable'], 502);
        }

        return new JsonResponse(['status' => 'ok']);
    }
}
