<?php

declare(strict_types=1);

namespace AutoDudes\AiSuite\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Page\PageRenderer;

class ContextualRecordEditAssetsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!ApplicationType::fromRequest($request)->isBackend()) {
            return $handler->handle($request);
        }

        $routeIdentifier = (string) ($request->getAttribute('route')?->getOption('_identifier') ?? '');
        if ('record_edit_contextual' === $routeIdentifier) {
            $this->pageRenderer->addInlineLanguageLabelFile('EXT:ai_suite/Resources/Private/Language/locallang.xlf');
            $this->pageRenderer->addInlineLanguageLabelFile('EXT:ai_suite/Resources/Private/Language/locallang_module.xlf');
            $this->pageRenderer->addCssFile('EXT:ai_suite/Resources/Public/Css/backend-basics-styles.css');
        }

        return $handler->handle($request);
    }
}
