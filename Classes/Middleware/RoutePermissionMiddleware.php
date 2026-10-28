<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Middleware;

use AutoDudes\AiSuite\Service\LocalizationService;
use AutoDudes\AiSuite\Service\RoutePermissionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Backend\Routing\Route;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\JsonResponse;

class RoutePermissionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RoutePermissionService $routePermissionService,
        private readonly LocalizationService $localizationService,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $route = $request->getAttribute('route');
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if (!$route instanceof Route
            || !$backendUser instanceof BackendUserAuthentication
            || !$this->routePermissionService->governs($route->getOption('target'))
            || $this->routePermissionService->isGranted($route->getOptions(), $backendUser)
        ) {
            return $handler->handle($request);
        }

        $message = $this->localizationService->translate('aiSuite.error.routeNotAllowed');
        if ($route->getOption('ajax')) {
            return new JsonResponse(['success' => false, 'error' => $message], 403);
        }

        return new HtmlResponse(htmlspecialchars($message), 403);
    }
}
