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

use AutoDudes\AiSuite\Domain\Model\Dto\ProvenanceContext;
use AutoDudes\AiSuite\Service\BackendUserService;
use AutoDudes\AiSuite\Service\ProvenanceService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Core\Http\JsonResponse;

#[AsController]
class ProvenanceAssistedController
{
    public function __construct(
        protected readonly ProvenanceService $provenanceService,
        protected readonly BackendUserService $backendUserService,
    ) {}

    public function recordAction(ServerRequestInterface $request): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $table = (string) ($body['table'] ?? '');
        $uid = (int) ($body['uid'] ?? 0);
        $field = (string) ($body['field'] ?? '');
        $model = (string) ($body['model'] ?? '');
        $feature = (string) ($body['feature'] ?? '');

        if ('' === $table || $uid <= 0) {
            return new JsonResponse(['success' => false]);
        }

        if (!$this->backendUserService->canEditRecord($table, $uid)) {
            return new JsonResponse(['success' => false], 403);
        }

        $this->provenanceService->record(
            ProvenanceContext::assisted($this->featureOf($feature), $model),
            $table,
            $uid,
            '' !== $field ? [$field] : [],
        );

        return new JsonResponse(['success' => true]);
    }

    protected function featureOf(string $feature): string
    {
        return in_array($feature, ProvenanceContext::FEATURES, true)
            ? $feature
            : ProvenanceContext::FEATURE_CONTENT;
    }
}
