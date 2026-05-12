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

use AutoDudes\AiSuite\Events\BeforeAiSuiteAjaxTemplateRenderEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Fluid\View\StandaloneView;

class ViewFactoryService implements SingletonInterface
{
    public function __construct(
        protected readonly EventDispatcherInterface $eventDispatcher,
        protected readonly Typo3Version $typo3Version
    ) {}

    /**
     * Render the standard AI Suite AJAX template — adds inline styles and dispatches
     * BeforeAiSuiteAjaxTemplateRenderEvent.
     *
     * @param array<string, mixed> $params
     */
    public function renderTemplate(
        ServerRequestInterface $request,
        string $templateName,
        string $templateRootPath,
        array $params = [],
    ): string {
        $params['inlineStyles'] = file_get_contents(GeneralUtility::getFileAbsFileName('EXT:ai_suite/Resources/Public/Css/Ajax/wizard-general.css'));

        $event = new BeforeAiSuiteAjaxTemplateRenderEvent($request, $params);
        $this->eventDispatcher->dispatch($event);
        $params = $event->getParams();

        return $this->renderView(
            $templateName,
            [$templateRootPath],
            ['EXT:ai_suite/Resources/Private/Partials'],
            ['EXT:ai_suite/Resources/Private/Layouts'],
            $params,
            $request,
        );
    }

    /**
     * Render a Fluid template in a TYPO3-version-agnostic way.
     *
     * On TYPO3 v13/v14 ViewFactoryInterface is used; on v12 it falls back to StandaloneView.
     *
     * @param list<string>         $templateRootPaths
     * @param list<string>         $partialRootPaths
     * @param list<string>         $layoutRootPaths
     * @param array<string, mixed> $params
     */
    public function renderView(
        string $templateName,
        array $templateRootPaths,
        array $partialRootPaths = [],
        array $layoutRootPaths = [],
        array $params = [],
        ?ServerRequestInterface $request = null,
    ): string {
        if ($this->typo3Version->getMajorVersion() > 12) {
            /** @var ViewFactoryInterface $viewFactory */
            $viewFactory = GeneralUtility::makeInstance(ViewFactoryInterface::class);
            $viewFactoryData = new ViewFactoryData(
                templateRootPaths: $templateRootPaths,
                partialRootPaths: $partialRootPaths,
                layoutRootPaths: $layoutRootPaths,
                request: $request,
            );
            $view = $viewFactory->create($viewFactoryData);
            $view->assignMultiple($params);

            return $view->render($templateName);
        }
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setTemplateRootPaths($templateRootPaths);
        if ([] !== $partialRootPaths) {
            $view->setPartialRootPaths($partialRootPaths);
        }
        if ([] !== $layoutRootPaths) {
            $view->setLayoutRootPaths($layoutRootPaths);
        }
        $view->setTemplatePathAndFilename(
            rtrim($templateRootPaths[0], '/').'/'.$templateName.'.html'
        );
        $view->assignMultiple($params);

        return $view->render();
    }
}
