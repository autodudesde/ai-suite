<?php

/***
 *
 * This file is part of the "ai_suite" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *
 ***/

namespace AutoDudes\AiSuite\Controller;

use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Template\Components\Buttons\AiSuiteLinkButton;
use AutoDudes\AiSuite\Utility\BackendUserUtility;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Module\ModuleData;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder as BackendUriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Exception\NoSuchArgumentException;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

abstract class AbstractExtbaseBackendController extends ActionController
{
    protected ?ModuleData $moduleData;
    protected ModuleTemplate $moduleTemplate;

    protected ModuleTemplateFactory $moduleTemplateFactory;
    protected BackendUriBuilder $backendUriBuilder;
    protected IconFactory $iconFactory;
    protected PageRenderer $pageRenderer;
    protected SendRequestService $requestService;

    protected LoggerInterface $logger;

    public function __construct()
    {
        $this->moduleTemplateFactory = GeneralUtility::makeInstance(ModuleTemplateFactory::class);
        $this->backendUriBuilder = GeneralUtility::makeInstance(BackendUriBuilder::class);
        $this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $this->pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $this->requestService = GeneralUtility::makeInstance(SendRequestService::class);
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
    }

    public function initializeAction(): void
    {
        try {
            if ($this->request->hasArgument('content')) {
                $propertyMappingConfiguration = $this->arguments->getArgument('content')->getPropertyMappingConfiguration();
                $propertyMappingConfiguration->allowProperties(
                    'contentElementData',
                    'availableTcaColumns',
                    'selectedTcaColumns'
                );
                $this->setDefaultContentValues();
            }

            if (!isset($this->settings['dateFormat'])) {
                $this->settings['dateFormat'] = $GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'] ?: 'd-m-Y';
            }
            if (!isset($this->settings['timeFormat'])) {
                $this->settings['timeFormat'] = $GLOBALS['TYPO3_CONF_VARS']['SYS']['hhmm'];
            }
            $this->settings['dateTimeFormat'] = 'H:i d-m-Y';

            $this->moduleData = $this->request->getAttribute('moduleData');
            $this->moduleTemplate = $this->moduleTemplateFactory->create($this->request);
            $this->moduleTemplate->setTitle('AI Suite');
            $this->moduleTemplate->setFlashMessageQueue($this->getFlashMessageQueue());
            $this->moduleTemplate->setModuleId('aiSuite');
            $this->generateButtonBar();

            $this->pageRenderer->addInlineLanguageLabelFile('EXT:ai_suite/Resources/Private/Language/locallang.xlf');
            $this->pageRenderer->addCssFile('EXT:ai_suite/Resources/Public/Css/backend-basics-styles.css');
        } catch (NoSuchArgumentException|RouteNotFoundException $exception) {
            $this->logger->error($exception->getMessage());
            $this->addFlashMessage('Could not initialize module', 'Initialization error', ContextualFeedbackSeverity::ERROR);
            $this->redirect('dashboard');
        }
    }

    /**
     * @throws RouteNotFoundException
     */
    protected function generateButtonBar(): void
    {
        $moduleName = 'web_aisuite';
        $rootPageId = $this->request->getAttribute('site')->getRootPageId();
        $uriParameters = [
            'id' => $this->request->getQueryParams()['id'] ?? $rootPageId,
            'action' => 'dashboard',
            'controller' => 'AiSuite'
        ];

        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();

        $dashboardUrl = (string)$this->backendUriBuilder->buildUriFromRoute($moduleName, $uriParameters);
        $dashboardButton = $this->buildButton('actions-menu', 'tx_aisuite.module.actionmenu.dashboard', 'btn-md btn-primary rounded', $dashboardUrl);
        $buttonBar->addButton($dashboardButton);

        $uriParameters['action'] = 'overview';
        $uriParameters['controller'] = 'Pages';
        $pagesUrl = (string)$this->backendUriBuilder->buildUriFromRoute($moduleName, $uriParameters);
        $pagesButton = $this->buildButton('actions-file-text', 'tx_aisuite.module.actionmenu.pages', 'btn-md btn-secondary mx-2 rounded', $pagesUrl);
        $buttonBar->addButton($pagesButton);

        $uriParameters['controller'] = 'Agencies';
        $agenciesUrl = (string)$this->backendUriBuilder->buildUriFromRoute($moduleName, $uriParameters);
        $agenciesButton = $this->buildButton('content-store', 'tx_aisuite.module.actionmenu.agencies', 'btn-md btn-default rounded', $agenciesUrl);
        $buttonBar->addButton($agenciesButton);

        $uriParameters['controller'] = 'PromptTemplate';
        $promptUrl = (string)$this->backendUriBuilder->buildUriFromRoute($moduleName, $uriParameters);
        $promptButton = $this->buildButton('actions-file-text', 'tx_aisuite.module.actionmenu.promptTemplate', 'btn-md btn-default mx-2 rounded', $promptUrl);
        $buttonBar->addButton($promptButton);
        if(BackendUserUtility::checkPermissions('tx_aisuite_features:enable_massaction_generation')) {
            $uriParameters['controller'] = 'MassAction';
            $massActionUrl = (string)$this->backendUriBuilder->buildUriFromRoute($moduleName, $uriParameters);
            $massActionButton = $this->buildButton('actions-duplicate', 'tx_aisuite.module.actionmenu.massAction', 'btn-md btn-default rounded', $massActionUrl);
            $buttonBar->addButton($massActionButton);
        }
        if(BackendUserUtility::checkPermissions('tx_aisuite_features:enable_background_task_handling')) {
            $uriParameters['controller'] = 'BackgroundTask';
            $backgroundTaskUrl = (string)$this->backendUriBuilder->buildUriFromRoute($moduleName, $uriParameters);
            $backgroundTaskButton = $this->buildButton('overlay-scheduled', 'tx_aisuite.module.actionmenu.backgroundTask', 'btn-md btn-default mx-2 rounded', $backgroundTaskUrl);
            $buttonBar->addButton($backgroundTaskButton);
        }
    }

    private function setDefaultContentValues(): void
    {
        $content = $this->request->getArgument('content');
        $content['contentElementData'] = [];
        $content['availableTcaColumns'] = [];
        $content['selectedTcaColumns'] = [];

        $arguments = $this->request->getArguments();
        $arguments['content'] = $content;
        $newRequest = $this->request->withArguments($arguments);
        $this->request = $newRequest;
    }

    protected function buildButton(string $iconIdentifier, string $translationKey, string $classes, string $url): AiSuiteLinkButton
    {
        $button = GeneralUtility::makeInstance(AiSuiteLinkButton::class);
        return $button
            ->setIcon($this->iconFactory->getIcon($iconIdentifier, Icon::SIZE_SMALL))
            ->setTitle(LocalizationUtility::translate($translationKey, 'ai_suite'))
            ->setShowLabelText(true)
            ->setClasses($classes)
            ->setHref($url);
    }
}
