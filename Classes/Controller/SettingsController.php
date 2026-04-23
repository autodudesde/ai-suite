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

use AutoDudes\AiSuite\Factory\SettingsFactory;
use AutoDudes\AiSuite\Service\AiSuiteContext;
use AutoDudes\AiSuite\Service\SendRequestService;
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
    private const MASKED_FIELDS = [
        'aiSuiteApiKey',
        'openAiApiKey',
        'anthropicApiKey',
        'googleTranslateApiKey',
        'deeplApiKey',
        'midjourneyApiKey',
        'fluxApiKey',
        'basicAuth.pass',
    ];

    private const MASK_PLACEHOLDER = '************';

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
    private function indexAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->initialize($request);

        $definitions = $this->settingsFactory->parseExtConfTemplate();
        $extConf = $this->extensionConfiguration->get('ai_suite');
        $settings = $this->buildSettingsForView($definitions, $extConf);
        $categories = array_unique(array_column($definitions, 'category'));

        $this->view->assignMultiple([
            'settings' => $settings,
            'categories' => $categories,
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
    private function saveAction(ServerRequestInterface $request): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $submittedSettings = (array) ($body['settings'] ?? []);

        $definitions = $this->settingsFactory->parseExtConfTemplate();
        $currentConf = $this->extensionConfiguration->get('ai_suite');
        $newConf = [];

        foreach ($definitions as $key => $definition) {
            $formKey = str_replace('.', '_', $key);

            if (in_array($key, self::MASKED_FIELDS, true)) {
                $submittedValue = $submittedSettings[$formKey] ?? '';
                if (self::MASK_PLACEHOLDER === $submittedValue || '' === $submittedValue) {
                    $newConf[$key] = $this->getNestedValue($currentConf, $key);

                    continue;
                }
            }

            if ('boolean' === $definition['type']) {
                $newConf[$key] = isset($submittedSettings[$formKey]) ? '1' : '0';
            } else {
                $newConf[$key] = $submittedSettings[$formKey] ?? ($definition['default'] ?? '');
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
     * @param array<string, array<string, mixed>> $definitions
     * @param array<string, mixed>                $extConf
     *
     * @return array<string, array<string, mixed>>
     */
    private function buildSettingsForView(array $definitions, array $extConf): array
    {
        $settings = [];
        foreach ($definitions as $key => $definition) {
            $value = $this->getNestedValue($extConf, $key);
            $isMasked = in_array($key, self::MASKED_FIELDS, true);

            $setting = [
                'key' => $key,
                'formKey' => str_replace('.', '_', $key),
                'type' => $definition['type'],
                'category' => $definition['category'],
                'label' => $definition['label'],
                'value' => $isMasked && !empty($value) ? self::MASK_PLACEHOLDER : ($value ?? ($definition['default'] ?? '')),
                'masked' => $isMasked,
            ];

            if (isset($definition['options'])) {
                $setting['options'] = $definition['options'];
                $setting['currentValue'] = $value ?? ($definition['default'] ?? '');
            }

            $settings[$key] = $setting;
        }

        return $settings;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function getNestedValue(array $data, string $key): mixed
    {
        if (str_contains($key, '.')) {
            $parts = explode('.', $key);
            $current = $data;
            foreach ($parts as $part) {
                if (!is_array($current) || !array_key_exists($part, $current)) {
                    return '';
                }
                $current = $current[$part];
            }

            return $current;
        }

        return $data[$key] ?? '';
    }
}
