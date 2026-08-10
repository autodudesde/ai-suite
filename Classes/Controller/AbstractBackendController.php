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

namespace AutoDudes\AiSuite\Controller;

use AutoDudes\AiSuite\Events\AfterAiSuiteModuleInitalizeEvent;
use AutoDudes\AiSuite\Events\AfterButtonBarGeneratedEvent;
use AutoDudes\AiSuite\Service\AiSuiteContext;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\TranslationService;
use AutoDudes\AiSuite\Template\Components\Buttons\AiSuiteLinkButton;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

class AbstractBackendController
{
    protected ServerRequestInterface $request;
    protected ModuleTemplate $view;

    public function __construct(
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
        protected readonly UriBuilder $uriBuilder,
        protected readonly PageRenderer $pageRenderer,
        protected readonly FlashMessageService $flashMessageService,
        protected readonly SendRequestService $requestService,
        protected readonly TranslationService $translationService,
        protected readonly EventDispatcher $eventDispatcher,
        protected readonly AiSuiteContext $aiSuiteContext,
    ) {}

    /**
     * @throws RouteNotFoundException
     */
    public function initialize(ServerRequestInterface $request): void
    {
        $this->request = $request;
        $this->view = $this->moduleTemplateFactory->create($request);
        $this->view->setTitle('AI Suite');
        $this->view->setFlashMessageQueue($this->flashMessageService->getMessageQueueByIdentifier('ai_suite.template.flashMessages'));
        $this->view->setModuleId('aiSuite');
        $this->generateButtonBar();

        $this->pageRenderer->addInlineLanguageLabelFile('EXT:ai_suite/Resources/Private/Language/locallang.xlf');
        $this->pageRenderer->addInlineLanguageLabelFile('EXT:ai_suite/Resources/Private/Language/locallang_module.xlf');
        $this->pageRenderer->addCssFile('EXT:ai_suite/Resources/Public/Css/backend-basics-styles.css');
        $this->pageRenderer->loadJavaScriptModule('@autodudes/ai-suite/backend/nav-overflow.js');

        $this->eventDispatcher->dispatch(new AfterAiSuiteModuleInitalizeEvent($this->request, $this->pageRenderer));
    }

    /**
     * @throws RouteNotFoundException
     */
    protected function generateButtonBar(): void
    {
        $buttonBar = $this->view->getDocHeaderComponent()->getButtonBar();
        foreach ($this->aiSuiteContext->moduleNavigationService->getPermittedEntries() as $entry) {
            $buttonBar->addButton($this->buildButton(
                $entry['icon'],
                $entry['labelKey'],
                'btn-md rounded',
                $entry['route'],
                $this->buttonParams($entry['route']),
            ));
        }
        $this->eventDispatcher->dispatch(new AfterButtonBarGeneratedEvent($buttonBar, $this->request));
    }

    /**
     * @return array<string, mixed>
     */
    protected function buttonParams(string $route): array
    {
        if ('web_aisuite.backgroundtask' !== $route) {
            return [];
        }
        $filter = $this->aiSuiteContext->sessionService->getBackgroundTaskFilter();
        if (empty($filter)) {
            return [];
        }

        return [
            'backgroundTaskFilter' => $filter,
            'clickAndSave' => $this->aiSuiteContext->sessionService->getClickAndSaveState(),
        ];
    }

    /**
     * @param array<string, mixed> $additionalParams
     *
     * @throws RouteNotFoundException
     */
    protected function buildButton(string $iconIdentifier, string $translationKey, string $classes, string $route, array $additionalParams = []): AiSuiteLinkButton
    {
        $rootPageId = $this->request->getAttribute('site')->getRootPageId();
        $currentId = $this->request->getQueryParams()['id'] ?? null;
        if (MathUtility::canBeInterpretedAsInteger($currentId)) {
            $pageId = (int) $currentId;
        } else {
            $webPageId = $this->aiSuiteContext->sessionService->getWebPageId();
            $pageId = $webPageId > 0 ? $webPageId : $rootPageId;
        }
        $uriParameters = [
            'id' => $pageId,
        ];
        $uriParameters = array_merge_recursive($uriParameters, $additionalParams);
        $url = (string) $this->uriBuilder->buildUriFromRoute($route, $uriParameters);
        $button = GeneralUtility::makeInstance(AiSuiteLinkButton::class);

        $classes = trim($classes.' '.($this->isActiveRoute($route) ? 'btn-primary' : 'btn-default'));

        return $button
            ->setIcon($this->aiSuiteContext->iconService->getIcon($iconIdentifier))
            ->setTitle($this->aiSuiteContext->localizationService->translate($translationKey))
            ->setShowLabelText(true)
            ->setClasses($classes)
            ->setHref($url)
        ;
    }

    protected function isActiveRoute(string $route): bool
    {
        $currentRoute = (string) ($this->request->getAttribute('route')?->getOption('_identifier') ?? '');

        $standalone = 'web_aisuite' === $route
            ? 'ai_suite_dashboard'
            : 'ai_suite_'.substr($route, strlen('web_aisuite.'));

        return $currentRoute === $route
            || $currentRoute === $standalone
            || str_starts_with($currentRoute, $standalone.'_');
    }
}
