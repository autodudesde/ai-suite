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

use AutoDudes\AiSuite\Service\BackgroundTaskService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Core\Http\JsonResponse;

#[AsController]
class CliOverviewAjaxController
{
    public function __construct(
        protected readonly BackgroundTaskService $backgroundTaskService,
    ) {}

    public function rerunAction(ServerRequestInterface $request): ResponseInterface
    {
        $parsedBody = (array) $request->getParsedBody();
        $type = $parsedBody['type'] ?? null;

        $config = ['status' => 'failed'];
        if (null !== $type && 'all' !== $type) {
            $config['type'] = $type;
        }

        $result = $this->backgroundTaskService->retryFailedTasks($config);

        return new JsonResponse($result);
    }

    public function updateStatusAction(ServerRequestInterface $request): ResponseInterface
    {
        $result = $this->backgroundTaskService->updateAllTaskStatuses();

        return new JsonResponse($result);
    }
}
