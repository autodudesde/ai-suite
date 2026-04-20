<?php

declare(strict_types=1);

/***
 *
 * This file is part of the "ai_suite" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *
 ***/

namespace AutoDudes\AiSuite\Service;

use AutoDudes\AiSuite\Events\BeforeAiSuiteAjaxTemplateRenderEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

class ViewFactoryService
{
    public function __construct(
        protected readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * @param array<string, mixed> $params
     */
    public function renderTemplate(
        ServerRequestInterface $request,
        string $templateName,
        string $templateRootPath,
        array $params = []
    ): string {
        $params['inlineStyles'] = file_get_contents(GeneralUtility::getFileAbsFileName('EXT:ai_suite/Resources/Public/Css/Ajax/wizard-general.css'));

        $event = new BeforeAiSuiteAjaxTemplateRenderEvent($request, $params);
        $this->eventDispatcher->dispatch($event);
        $params = $event->getParams();

        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setTemplateRootPaths([$templateRootPath]);
        $view->setPartialRootPaths(['EXT:ai_suite/Resources/Private/Partials/']);
        $view->setLayoutRootPaths(['EXT:ai_suite/Resources/Private/Layouts/']);
        $view->setTemplatePathAndFilename(rtrim($templateRootPath, '/') . '/' . $templateName . '.html');
        $view->assignMultiple($params);

        return $view->render();
    }
}
