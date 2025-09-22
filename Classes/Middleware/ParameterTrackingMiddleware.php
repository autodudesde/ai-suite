<?php

namespace AutoDudes\AiSuite\Middleware;

use AutoDudes\AiSuite\Service\SessionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\ApplicationType;

class ParameterTrackingMiddleware implements MiddlewareInterface
{
    private SessionService $sessionService;

    public function __construct(
        SessionService $sessionService
    ) {
        $this->sessionService = $sessionService;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!ApplicationType::fromRequest($request)->isBackend()) {
            return $handler->handle($request);
        }
        try {
            $route = (string)($request->getAttribute('route')?->getOption('_identifier') ?? '');
            if (!empty($route)) {
                $this->sessionService->trackRequestParameters($request, $route);
            }
        } catch (\Throwable $e) {
            // silent fail
        }
        return $handler->handle($request);
    }
}
