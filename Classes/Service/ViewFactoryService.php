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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

class ViewFactoryService
{
    public function __construct(
        protected readonly ViewFactoryInterface $viewFactory,
        protected readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * @param array<string, mixed> $params
     */
    public function renderTemplate(
        ServerRequestInterface $request,
        string $templateName,
        string $templateRootPath,
        array $params = [],
    ): string {
        $viewFactoryData = new ViewFactoryData(
            templateRootPaths: [$templateRootPath],
            partialRootPaths: ['EXT:ai_suite/Resources/Private/Partials'],
            layoutRootPaths: ['EXT:ai_suite/Resources/Private/Layouts'],
            request: $request,
        );
        $view = $this->viewFactory->create($viewFactoryData);
        $params['inlineStyles'] = file_get_contents(GeneralUtility::getFileAbsFileName('EXT:ai_suite/Resources/Public/Css/Ajax/wizard-general.css'));

        $event = new BeforeAiSuiteAjaxTemplateRenderEvent($request, $params);
        $this->eventDispatcher->dispatch($event);
        $params = $event->getParams();

        $view->assignMultiple($params);

        return $view->render($templateName);
    }
}
