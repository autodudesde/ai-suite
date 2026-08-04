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

namespace AutoDudes\AiSuite\Service;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\WorkspaceAspect;
use TYPO3\CMS\Core\SingletonInterface;

class WorkspaceContextService implements SingletonInterface
{
    /** @var array<string, true> */
    private array $reportedDrifts = [];

    public function __construct(
        private readonly Context $context,
        private readonly LoggerInterface $logger,
    ) {}

    public function getWorkspaceId(): int
    {
        $aspectWorkspaceId = $this->getAspectWorkspaceId();
        $backendUser = $this->getAuthenticatedBackendUser();

        if (null === $backendUser) {
            return $aspectWorkspaceId;
        }

        $userWorkspaceId = (int) $backendUser->workspace;

        if ($userWorkspaceId !== $aspectWorkspaceId) {
            $this->reportDrift($aspectWorkspaceId, $userWorkspaceId, $backendUser);
        }

        return $userWorkspaceId;
    }

    public function applyWorkspace(BackendUserAuthentication $backendUser, int $workspaceId): bool
    {
        if (false === $backendUser->setTemporaryWorkspace($workspaceId)) {
            return false;
        }

        $this->context->setAspect('workspace', new WorkspaceAspect($workspaceId));

        return true;
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function withWorkspace(BackendUserAuthentication $backendUser, int $workspaceId, callable $callback): mixed
    {
        $previousUserWorkspaceId = (int) $backendUser->workspace;
        $previousAspectWorkspaceId = $this->getAspectWorkspaceId();

        $this->applyWorkspace($backendUser, $workspaceId);

        try {
            return $callback();
        } finally {
            $backendUser->setTemporaryWorkspace($previousUserWorkspaceId);
            $this->context->setAspect('workspace', new WorkspaceAspect($previousAspectWorkspaceId));
        }
    }

    private function getAspectWorkspaceId(): int
    {
        return (int) $this->context->getPropertyFromAspect('workspace', 'id');
    }

    private function getAuthenticatedBackendUser(): ?BackendUserAuthentication
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;

        if (!$backendUser instanceof BackendUserAuthentication) {
            return null;
        }

        return (int) ($backendUser->user['uid'] ?? 0) > 0 ? $backendUser : null;
    }

    private function reportDrift(int $aspectWorkspaceId, int $userWorkspaceId, BackendUserAuthentication $backendUser): void
    {
        $key = $aspectWorkspaceId.':'.$userWorkspaceId;

        if (isset($this->reportedDrifts[$key])) {
            return;
        }

        $this->reportedDrifts[$key] = true;

        $this->logger->warning('Workspace context drift: the workspace aspect does not match the backend user workspace', [
            'aspectWorkspace' => $aspectWorkspaceId,
            'backendUserWorkspace' => $userWorkspaceId,
            'beUserUid' => (int) ($backendUser->user['uid'] ?? 0),
        ]);
    }
}
