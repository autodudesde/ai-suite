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

        $this->eventDispatcher->dispatch(new AfterAiSuiteModuleInitalizeEvent($this->request, $this->pageRenderer));
    }

    /**
     * @throws RouteNotFoundException
     */
    protected function generateButtonBar(): void
    {
        $buttonBar = $this->view->getDocHeaderComponent()->getButtonBar();
        $buttonBar->addButton($this->buildButton('actions-menu', 'module:aiSuite.module.actionmenu.dashboard', 'btn-md btn-primary rounded', 'ai_suite_dashboard'));
        $buttonBar->addButton($this->buildButton('actions-file-text', 'module:aiSuite.module.actionmenu.pages', 'btn-md btn-default rounded', 'ai_suite_page'));
        $buttonBar->addButton($this->buildButton('content-store', 'module:aiSuite.module.actionmenu.agencies', 'btn-md btn-default rounded', 'ai_suite_agencies'));
        if ($this->aiSuiteContext->backendUserService->checkPermissions('tx_aisuite_features:enable_global_instructions_button')) {
            $buttonBar->addButton($this->buildButton('apps-pagetree-page-content-from-page-root', 'module:aiSuite.module.actionmenu.globalInstructions', 'btn-md btn-default rounded', 'ai_suite_global_instructions'));
        }
        if ($this->aiSuiteContext->backendUserService->checkPermissions('tx_aisuite_features:enable_prompt_template_button')) {
            $buttonBar->addButton($this->buildButton('actions-file-text', 'module:aiSuite.module.actionmenu.promptTemplate', 'btn-md btn-default rounded', 'ai_suite_prompt'));
        }
        if ($this->aiSuiteContext->backendUserService->checkPermissions('tx_aisuite_features:enable_massaction_generation')) {
            $buttonBar->addButton($this->buildButton('actions-duplicate', 'module:aiSuite.module.actionmenu.workflow', 'btn-md btn-default rounded', 'ai_suite_workflow'));
        }
        if ($this->aiSuiteContext->backendUserService->checkPermissions('tx_aisuite_features:enable_background_task_handling')) {
            $additonalParams = [];
            if (!empty($this->aiSuiteContext->sessionService->getBackgroundTaskFilter())) {
                $additonalParams['backgroundTaskFilter'] = $this->aiSuiteContext->sessionService->getBackgroundTaskFilter();
                $additonalParams['clickAndSave'] = $this->aiSuiteContext->sessionService->getClickAndSaveState();
            }
            $buttonBar->addButton($this->buildButton('overlay-scheduled', 'module:aiSuite.module.actionmenu.backgroundTask', 'btn-md btn-default rounded', 'ai_suite_backgroundtask', $additonalParams));
        }
        if ($this->aiSuiteContext->backendUserService->checkPermissions('tx_aisuite_features:enable_global_settings')) {
            $buttonBar->addButton($this->buildButton('actions-cog', 'module:aiSuite.module.actionmenu.globalSettings', 'btn-md btn-default rounded', 'ai_suite_settings'));
        }
        $this->eventDispatcher->dispatch(new AfterButtonBarGeneratedEvent($buttonBar, $this->request));
    }

    /**
     * @param array<string, mixed> $additionalParams
     *
     * @throws RouteNotFoundException
     */
    protected function buildButton(string $iconIdentifier, string $translationKey, string $classes, string $route, array $additionalParams = []): AiSuiteLinkButton
    {
        $rootPageId = $this->request->getAttribute('site')->getRootPageId();
        $uriParameters = [
            'id' => $this->request->getQueryParams()['id'] ?? $rootPageId,
        ];
        $uriParameters = array_merge_recursive($uriParameters, $additionalParams);
        $url = (string) $this->uriBuilder->buildUriFromRoute($route, $uriParameters);
        $button = GeneralUtility::makeInstance(AiSuiteLinkButton::class);

        return $button
            ->setIcon($this->aiSuiteContext->iconService->getIcon($iconIdentifier))
            ->setTitle($this->aiSuiteContext->localizationService->translate($translationKey))
            ->setShowLabelText(true)
            ->setClasses($classes)
            ->setHref($url)
        ;
    }
}
