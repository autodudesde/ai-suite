<?php

namespace AutoDudes\AiSuite\Service;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\SingletonInterface;

class SessionService implements SingletonInterface
{
    private const SESSION_NAMESPACE = 'ai_suite';

    private const AI_SUITE_ROUTES = [
        'ajax_aisuite_massaction_pages_prepare' => 'ai_suite_massaction_pages_prepare',
        'ajax_aisuite_massaction_filereferences_prepare' => 'ai_suite_massaction_filereferences_prepare',
        'ajax_aisuite_massaction_filelist_files_update_view' => 'ai_suite_massaction_filelist_files_prepare'
    ];

    private const AI_SUITE_FILELIST_FOLDER_ID = 'ai_suite_filelist_folder_id';
    private const AI_SUITE_WEB_PAGE_ID = 'ai_suite_web_page_id';
    private const AI_SUITE_BACKGROUND_TASK_FILTER = 'ai_suite_background_task_filter';
    private const AI_SUITE_CLICK_AND_SAVE = 'ai_suite_click_and_save';

    protected BackendUserService $backendUserService;

    protected UriBuilder $uriBuilder;

    public function __construct(
        BackendUserService  $backendUserService,
        UriBuilder $uriBuilder,
    ) {
        $this->backendUserService = $backendUserService;
        $this->uriBuilder = $uriBuilder;
    }

    public function trackRequestParameters(ServerRequestInterface $request, string $route): void
    {
        $sessionData = $this->backendUserService->getBackendUser()->getSessionData(self::SESSION_NAMESPACE) ?? [];

        $queryParams = $request->getQueryParams();
        $this->storeQueryParams($sessionData, $queryParams);

        $postParams = $request->getParsedBody() ?? [];

        $this->storeContextForRoute($sessionData, $route, $postParams);

        $this->backendUserService->getBackendUser()->setAndSaveSessionData(self::SESSION_NAMESPACE, $sessionData);
    }

    protected function storeContextForRoute(array &$sessionData, string $route, array $postParams): void
    {
        $sessionData['ai_suite_context'] = $route === 'web_AiSuite' ? $sessionData['ai_suite_context'] : 'default';

        if (array_key_exists($route, self::AI_SUITE_ROUTES)) {
            $sessionData['ai_suite_context'] = self::AI_SUITE_ROUTES[$route];
            $sessionData['ai_suite_last_route'] = self::AI_SUITE_ROUTES[$route];
            if (!empty($postParams)) {
                $sessionData[self::AI_SUITE_ROUTES[$route]] = $postParams;
            }
        }
    }

    public function getParametersForRoute(string $route): array
    {
        $sessionData = $this->backendUserService->getBackendUser()->getSessionData(self::SESSION_NAMESPACE) ?? [];
        return $sessionData[$route] ?? [];
    }

    public function getCurrentContext(): string
    {
        $sessionData = $this->backendUserService->getBackendUser()->getSessionData(self::SESSION_NAMESPACE) ?? [];
        return $sessionData['ai_suite_context'] ?? 'default';
    }

    public function getLastRoute(): string {
        $sessionData = $this->backendUserService->getBackendUser()->getSessionData(self::SESSION_NAMESPACE) ?? [];
        return $sessionData['ai_suite_last_route'] ?? '';
    }

    public function storeQueryParams(array &$sessionData, array $queryParams): void
    {
        if (array_key_exists('id', $queryParams)) {
            if (strpos($queryParams['id'], ':') > 0) {
                $sessionData[self::AI_SUITE_FILELIST_FOLDER_ID] = $queryParams['id'];
            } else {
                $pageId = (int)$queryParams['id'];
                if ($pageId > 0) {
                    $sessionData[self::AI_SUITE_WEB_PAGE_ID] = $pageId;
                }
            }
        }
        if(array_key_exists('backgroundTaskFilter', $queryParams)) {
            $sessionData[self::AI_SUITE_BACKGROUND_TASK_FILTER] = $queryParams['backgroundTaskFilter'];
        }
        if(array_key_exists('clickAndSave', $queryParams)) {
            $sessionData[self::AI_SUITE_CLICK_AND_SAVE] = $queryParams['clickAndSave'] === '1';
        }
    }

    public function getFilelistFolderId(): string
    {
        $sessionData = $this->backendUserService->getBackendUser()->getSessionData(self::SESSION_NAMESPACE) ?? [];
        return $sessionData[self::AI_SUITE_FILELIST_FOLDER_ID] ?? '';
    }

    public function getWebPageId(): int
    {
        $sessionData = $this->backendUserService->getBackendUser()->getSessionData(self::SESSION_NAMESPACE) ?? [];
        return $sessionData[self::AI_SUITE_WEB_PAGE_ID] ?? 0;
    }

    public function getBackgroundTaskFilter(): string {
        $sessionData = $this->backendUserService->getBackendUser()->getSessionData(self::SESSION_NAMESPACE) ?? [];
        return $sessionData[self::AI_SUITE_BACKGROUND_TASK_FILTER] ?? '';
    }

    public function getClickAndSaveState(): bool {
        $sessionData = $this->backendUserService->getBackendUser()->getSessionData(self::SESSION_NAMESPACE) ?? [];
        return $sessionData[self::AI_SUITE_CLICK_AND_SAVE] ?? false;
    }

    /**
     * @throws PropagateResponseException
     * @throws RouteNotFoundException
     */
    public function handleRedirectBySessionRoute(): void
    {
        if ($this->getCurrentContext() !== 'default') {
            $lastRoute = $this->getLastRoute();
            if (!empty($lastRoute)) {
                $id = $this->getCurrentContext() === 'ai_suite_workflowmanager_files' ? $this->getFilelistFolderId() : $this->getWebPageId();
                $uri = $this->uriBuilder->buildUriFromRoute($lastRoute, ['id' => $id]);
                $response = new RedirectResponse((string)$uri);
                throw new PropagateResponseException($response, 303);
            }
        }
    }
}
