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

use AutoDudes\AiSuite\Enumeration\GenerationLibraryEnumeration;
use AutoDudes\AiSuite\Factory\SettingsFactory;
use AutoDudes\AiSuite\Service\AiSuiteContext;
use AutoDudes\AiSuite\Service\SendRequestService;
use AutoDudes\AiSuite\Service\SettingsService;
use AutoDudes\AiSuite\Service\TranslationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

#[AsController]
class SettingsController extends AbstractBackendController
{
    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        UriBuilder $uriBuilder,
        PageRenderer $pageRenderer,
        FlashMessageService $flashMessageService,
        SendRequestService $requestService,
        TranslationService $translationService,
        EventDispatcher $eventDispatcher,
        AiSuiteContext $aiSuiteContext,
        protected readonly ExtensionConfiguration $extensionConfiguration,
        protected readonly SettingsFactory $settingsFactory,
        protected readonly SettingsService $settingsService,
    ) {
        parent::__construct(
            $moduleTemplateFactory,
            $uriBuilder,
            $pageRenderer,
            $flashMessageService,
            $requestService,
            $translationService,
            $eventDispatcher,
            $aiSuiteContext,
        );
    }

    /**
     * @throws Exception
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws RouteNotFoundException
     */
    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $routeIdentifier = $request->getAttribute('route')?->getOption('_identifier') ?? '';
        if ('ai_suite_settings_save' === $routeIdentifier) {
            return $this->saveAction($request);
        }

        return $this->indexAction($request);
    }

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws RouteNotFoundException
     */
    public function indexAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->initialize($request);

        $definitions = $this->settingsFactory->parseExtConfTemplate();
        $extConf = $this->extensionConfiguration->get('ai_suite');
        $settings = $this->decorateAuditModelSetting($this->settingsService->buildSettingsForView($definitions, $extConf));

        $this->view->assignMultiple([
            'settings' => $settings,
            'categories' => $this->settingsService->buildCategoryTree($definitions, $settings),
            'currentAction' => 'settings',
        ]);

        return $this->view->renderResponse('Settings/Overview');
    }

    /**
     * @throws Exception
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws RouteNotFoundException
     */
    public function saveAction(ServerRequestInterface $request): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $submittedSettings = (array) ($body['settings'] ?? []);

        $definitions = $this->settingsFactory->parseExtConfTemplate();
        $currentConf = $this->extensionConfiguration->get('ai_suite');
        $newConf = [];

        foreach ($definitions as $key => $definition) {
            $formKey = str_replace('.', '_', $key);

            if ($this->settingsService->isMaskedField($key)) {
                $submittedValue = $submittedSettings[$formKey] ?? '';
                if (SettingsService::MASK_PLACEHOLDER === $submittedValue) {
                    $this->settingsService->setNestedValue($newConf, $key, $this->settingsService->getNestedValue($currentConf, $key));

                    continue;
                }
            }

            if ('boolean' === $definition['type']) {
                $this->settingsService->setNestedValue($newConf, $key, isset($submittedSettings[$formKey]) ? '1' : '0');
            } else {
                $this->settingsService->setNestedValue($newConf, $key, $submittedSettings[$formKey] ?? ($definition['default'] ?? ''));
            }
        }

        try {
            $this->extensionConfiguration->set('ai_suite', $newConf);

            $flashMessage = new FlashMessage(
                $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.settings.saveSuccess'),
                '',
                ContextualFeedbackSeverity::OK,
            );
        } catch (\Exception) {
            $flashMessage = new FlashMessage(
                $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.settings.saveError'),
                '',
                ContextualFeedbackSeverity::ERROR,
            );
        }

        $this->flashMessageService->getMessageQueueByIdentifier('ai_suite.template.flashMessages')->enqueue($flashMessage);

        return $this->indexAction($request);
    }

    /**
     * @param array<string, array<string, mixed>> $settings
     *
     * @return array<string, array<string, mixed>>
     */
    protected function decorateAuditModelSetting(array $settings): array
    {
        if (!isset($settings['auditDefaultTextModel'])) {
            return $settings;
        }

        try {
            $librariesAnswer = $this->requestService->sendLibrariesRequest(GenerationLibraryEnumeration::METADATA, 'createMetadata', ['text']);
            if ('Error' === $librariesAnswer->getType()) {
                return $settings;
            }
            $libraries = $this->aiSuiteContext->libraryService->prepareLibraries(array_values(array_filter(
                $librariesAnswer->getResponseData()['textGenerationLibraries'] ?? [],
                static fn (array $library): bool => !empty($library['model_identifier'])
                    && !\in_array($library['model_identifier'], ['Vision', 'MittwaldMinistral14BVision'], true)
            )));
        } catch (\Throwable) {
            return $settings;
        }
        if ([] === $libraries) {
            return $settings;
        }

        $options = ['' => $this->aiSuiteContext->localizationService->translate('module:aiSuite.module.settings.auditModel.perRun')];
        foreach ($libraries as $library) {
            $options[(string) $library['model_identifier']] = (string) ($library['name'] ?? $library['model_identifier']);
        }

        $settings['auditDefaultTextModel']['type'] = 'select';
        $settings['auditDefaultTextModel']['options'] = $options;
        $settings['auditDefaultTextModel']['currentValue'] = (string) ($settings['auditDefaultTextModel']['value'] ?? '');

        return $settings;
    }
}
