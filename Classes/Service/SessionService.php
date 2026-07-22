<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Service;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Site\SiteFinder;

class SessionService implements SingletonInterface
{
    private const SESSION_NAMESPACE = 'ai_suite';

    private const AI_SUITE_ROUTES = [
        'ajax_aisuite_workflow_pages_prepare' => 'ai_suite_workflow_pages_prepare',
        'ajax_aisuite_workflow_filereferences_prepare' => 'ai_suite_workflow_filereferences_prepare',
        'ajax_aisuite_workflow_filelist_files_update_view' => 'ai_suite_workflow_filelist_files_prepare',
        'ajax_aisuite_workflow_pages_translation_prepare' => 'ai_suite_workflow_pages_translation_prepare',
        'ajax_aisuite_workflow_filelist_files_translate_update_view' => 'ai_suite_workflow_filelist_files_translate_prepare',
        'ajax_aisuite_glossary_fetch_page_translation' => 'ai_suite_workflow_pages_translation_prepare',
        'ai_suite_workflow_pages_prepare' => 'ai_suite_workflow_pages_prepare',
        'ai_suite_workflow_filereferences_prepare' => 'ai_suite_workflow_filereferences_prepare',
        'ai_suite_workflow_filelist_files_prepare' => 'ai_suite_workflow_filelist_files_prepare',
        'ai_suite_workflow_pages_translation_prepare' => 'ai_suite_workflow_pages_translation_prepare',
        'ai_suite_workflow_filelist_files_translate_prepare' => 'ai_suite_workflow_filelist_files_translate_prepare',
        'ai_suite_global_instructions' => 'ai_suite_global_instructions',
        'ai_suite_prompt_manage_customprompttemplates' => 'ai_suite_prompt_manage_customprompttemplates',
    ];

    private const CONTEXT_PRESERVE_ROUTES = [
        'web_aisuite',
        'files_aisuite',
        'ai_suite_global_instructions',
        'ai_suite_prompt_manage_customprompttemplates',
    ];

    private const AI_SUITE_FILELIST_FOLDER_ID = 'ai_suite_filelist_folder_id';
    private const AI_SUITE_WEB_PAGE_ID = 'ai_suite_web_page_id';
    private const AI_SUITE_BACKGROUND_TASK_FILTER = 'ai_suite_background_task_filter';
    private const AI_SUITE_CLICK_AND_SAVE = 'ai_suite_click_and_save';

    public function __construct(
        protected readonly BackendUserService $backendUserService,
        protected readonly UriBuilder $uriBuilder,
        protected readonly SiteFinder $siteFinder,
        protected readonly ResourceFactory $resourceFactory,
        protected readonly LoggerInterface $logger,
    ) {}

    public function trackRequestParameters(ServerRequestInterface $request, string $route): void
    {
        $queryParams = $request->getQueryParams();
        if (isset($queryParams['token']) && '--AnonymizedToken--' === $queryParams['token']) {
            return;
        }

        $sessionData = $this->backendUserService->getBackendUser()?->getSessionData(self::SESSION_NAMESPACE) ?? [];

        $this->storeQueryParams($sessionData, $queryParams);

        /** @var array<string, mixed> $postParams */
        $postParams = $request->getParsedBody() ?? [];

        $this->storeContextForRoute($sessionData, $route, $postParams);

        $this->backendUserService->getBackendUser()?->setAndSaveSessionData(self::SESSION_NAMESPACE, $sessionData);
    }

    /**
     * @return array<string, mixed>
     */
    public function getParametersForRoute(string $route): array
    {
        $sessionData = $this->backendUserService->getBackendUser()?->getSessionData(self::SESSION_NAMESPACE) ?? [];

        return $sessionData[$route] ?? [];
    }

    public function getCurrentContext(): string
    {
        $sessionData = $this->backendUserService->getBackendUser()?->getSessionData(self::SESSION_NAMESPACE) ?? [];

        return $sessionData['ai_suite_context'] ?? 'default';
    }

    public function getLastRoute(): string
    {
        $sessionData = $this->backendUserService->getBackendUser()?->getSessionData(self::SESSION_NAMESPACE) ?? [];

        return $sessionData['ai_suite_last_route'] ?? '';
    }

    /**
     * @param array<string, mixed> $queryParams
     * @param array<string, mixed> $sessionData
     */
    public function storeQueryParams(array &$sessionData, array $queryParams): void
    {
        if (array_key_exists('id', $queryParams)) {
            if (str_contains($queryParams['id'], ':')) {
                $folderIdentifier = $this->normalizeToFolderIdentifier((string) $queryParams['id']);
                if (null !== $folderIdentifier) {
                    $sessionData[self::AI_SUITE_FILELIST_FOLDER_ID] = $folderIdentifier;
                }
            } else {
                $pageId = (int) $queryParams['id'];
                if ($this->isValidPageId($pageId)) {
                    $sessionData[self::AI_SUITE_WEB_PAGE_ID] = $pageId;
                }
            }
        }
        if (array_key_exists('backgroundTaskFilter', $queryParams)) {
            $sessionData[self::AI_SUITE_BACKGROUND_TASK_FILTER] = $queryParams['backgroundTaskFilter'];
        }
        if (array_key_exists('clickAndSave', $queryParams)) {
            $sessionData[self::AI_SUITE_CLICK_AND_SAVE] = '1' === $queryParams['clickAndSave'];
        }
    }

    public function getFilelistFolderId(): string
    {
        $sessionData = $this->backendUserService->getBackendUser()?->getSessionData(self::SESSION_NAMESPACE) ?? [];

        return $sessionData[self::AI_SUITE_FILELIST_FOLDER_ID] ?? '';
    }

    public function getFilelistFolder(): ?Folder
    {
        $directoryId = $this->getFilelistFolderId();
        if ('' === $directoryId) {
            return null;
        }

        try {
            return $this->resourceFactory->getFolderObjectFromCombinedIdentifier($directoryId);
        } catch (\Exception $e) {
            $this->logger->warning('Stored filelist folder identifier could not be resolved, resetting session value', [
                'directory' => $directoryId,
                'error' => $e->getMessage(),
            ]);
            $this->resetFilelistFolderId();

            return null;
        }
    }

    public function getWebPageId(): int
    {
        $sessionData = $this->backendUserService->getBackendUser()?->getSessionData(self::SESSION_NAMESPACE) ?? [];

        return $sessionData[self::AI_SUITE_WEB_PAGE_ID] ?? 0;
    }

    public function getBackgroundTaskFilter(): string
    {
        $sessionData = $this->backendUserService->getBackendUser()?->getSessionData(self::SESSION_NAMESPACE) ?? [];

        return $sessionData[self::AI_SUITE_BACKGROUND_TASK_FILTER] ?? '';
    }

    public function getClickAndSaveState(): bool
    {
        $sessionData = $this->backendUserService->getBackendUser()?->getSessionData(self::SESSION_NAMESPACE) ?? [];

        return $sessionData[self::AI_SUITE_CLICK_AND_SAVE] ?? false;
    }

    /**
     * @param array<int, string> $allowedRoutes
     *
     * @throws PropagateResponseException
     * @throws RouteNotFoundException
     */
    public function handleRedirectBySessionRoute(array $allowedRoutes = []): void
    {
        if ('default' !== $this->getCurrentContext()) {
            $lastRoute = $this->getLastRoute();
            if (!empty($lastRoute) && (empty($allowedRoutes) || in_array($lastRoute, $allowedRoutes, true))) {
                $currentContext = $this->getCurrentContext();
                if ('ai_suite_workflow_filelist_files_prepare' === $currentContext || 'ai_suite_workflow_filelist_files_translate_prepare' === $currentContext) {
                    $id = $this->getFilelistFolderId();
                } elseif ('ai_suite_global_instructions' === $currentContext || 'ai_suite_prompt_manage_customprompttemplates' === $currentContext) {
                    $id = $this->getWebPageId();
                } else {
                    $id = $this->getWebPageId();
                }
                $uri = $this->uriBuilder->buildUriFromRoute($lastRoute, ['id' => $id]);
                $response = new RedirectResponse((string) $uri);

                throw new PropagateResponseException($response, 303);
            }
        }
    }

    /**
     * @param array<string, mixed> $postParams
     * @param array<string, mixed> $sessionData
     */
    protected function storeContextForRoute(array &$sessionData, string $route, array $postParams): void
    {
        $sessionData['ai_suite_context'] = in_array($route, self::CONTEXT_PRESERVE_ROUTES, true) ? $sessionData['ai_suite_context'] : 'default';

        if (array_key_exists($route, self::AI_SUITE_ROUTES)) {
            $sessionData['ai_suite_context'] = self::AI_SUITE_ROUTES[$route];
            $sessionData['ai_suite_last_route'] = self::AI_SUITE_ROUTES[$route];
            if (!empty($postParams)) {
                $sessionData[self::AI_SUITE_ROUTES[$route]] = array_merge(
                    $sessionData[self::AI_SUITE_ROUTES[$route]] ?? [],
                    $postParams
                );
            }
        }
    }

    private function resetFilelistFolderId(): void
    {
        $backendUser = $this->backendUserService->getBackendUser();
        if (null === $backendUser) {
            return;
        }
        $sessionData = $backendUser->getSessionData(self::SESSION_NAMESPACE) ?? [];
        unset($sessionData[self::AI_SUITE_FILELIST_FOLDER_ID]);
        $backendUser->setAndSaveSessionData(self::SESSION_NAMESPACE, $sessionData);
    }

    private function normalizeToFolderIdentifier(string $identifier): ?string
    {
        try {
            $object = $this->resourceFactory->getObjectFromCombinedIdentifier($identifier);
            if ($object instanceof Folder) {
                return $object->getCombinedIdentifier();
            }
            if ($object instanceof File) {
                return $object->getParentFolder()->getCombinedIdentifier();
            }
        } catch (\Exception $e) {
            $this->logger->notice('Could not normalize filelist identifier to a folder', [
                'identifier' => $identifier,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function isValidPageId(int $pageId): bool
    {
        try {
            if ($pageId <= 0) {
                return false;
            }
            $site = $this->siteFinder->getSiteByPageId($pageId);

            return true;
        } catch (\Exception $e) {
            $this->logger->notice('Page id is not part of any site', [
                'pageId' => $pageId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
