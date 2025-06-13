<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace AutoDudes\AiSuite\Service;

use AutoDudes\AiSuite\Service\BackendUserService;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\Locales;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\RteCKEditor\Form\Element\Event\AfterGetExternalPluginsEvent;
use TYPO3\CMS\RteCKEditor\Form\Element\Event\AfterPrepareConfigurationForEditorEvent;
use TYPO3\CMS\RteCKEditor\Form\Element\Event\BeforeGetExternalPluginsEvent;
use TYPO3\CMS\RteCKEditor\Form\Element\Event\BeforePrepareConfigurationForEditorEvent;

/**
 * Render rich text editor in FormEngine
 * @internal This is a specific Backend FormEngine implementation and is not considered part of the Public TYPO3 API.
 */
class RichTextElementService
{
    protected EventDispatcherInterface $eventDispatcher;

    protected array $data;

    protected array $rteConfiguration = [];

    public function __construct(array $data = [], EventDispatcherInterface $eventDispatcher = null)
    {
        $this->data = $data;
        $this->eventDispatcher = $eventDispatcher ?? GeneralUtility::makeInstance(EventDispatcherInterface::class);
    }

    public function fetchRteConfig(int $counter): string
    {
        $parameterArray = $this->data['parameterArray'];
        $config = $parameterArray['fieldConf']['config'];

        $itemFormElementName = 'data[' . $this->data['tableName'] . ']['. $this->data['databaseRow']['uid'] . '][' . $this->data['fieldName'] . ']';
        $fieldId = $this->sanitizeFieldId($itemFormElementName) . '__' . $counter;

        $attributes = [
            'style' => 'display:none',
            'data-formengine-validation-rules' => $this->getValidationDataAsJsonString($config),
            'id' => $fieldId,
            'name' => htmlspecialchars($itemFormElementName),
        ];
        $ckeditorAttributes = GeneralUtility::implodeAttributes($attributes, true);

        $this->rteConfiguration = $config['richtextConfiguration']['editor'] ?? [];
        $jsInstruction = $this->loadCkEditorRequireJsModule($fieldId);

        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->addInlineSetting('FormEngine', 'formName', 'editform');
        $pageRenderer->getJavaScriptRenderer()->addJavaScriptModuleInstruction($jsInstruction);

        return $ckeditorAttributes;
    }

    /**
     * Determine the contents language iso code
     */
    protected function getLanguageIsoCodeOfContent(): string
    {
        $currentLanguageUid = ($this->data['databaseRow']['sys_language_uid'] ?? 0);
        if (is_array($currentLanguageUid)) {
            $currentLanguageUid = $currentLanguageUid[0];
        }
        $contentLanguageUid = (int)max($currentLanguageUid, 0);
        if ($contentLanguageUid) {
            // the language rows might not be fully inialized, so we fallback to en_US in this case
            $contentLanguage = $this->data['systemLanguageRows'][$currentLanguageUid]['iso'] ?? 'en_US';
        } else {
            $contentLanguage = $this->rteConfiguration['config']['defaultContentLanguage'] ?? 'en_US';
        }
        $languageCodeParts = explode('_', $contentLanguage);
        $contentLanguage = strtolower($languageCodeParts[0]) . (!empty($languageCodeParts[1]) ? '_' . strtoupper($languageCodeParts[1]) : '');
        // Find the configured language in the list of localization locales
        $locales = GeneralUtility::makeInstance(Locales::class);
        // If not found, default to 'en'
        if (!in_array($contentLanguage, $locales->getLocales(), true)) {
            $contentLanguage = 'en';
        }
        return $contentLanguage;
    }

    /**
     * Gets the JavaScript code for CKEditor module
     * Compiles the configuration, and then adds plugins
     *
     * @param string $fieldId
     * @return JavaScriptModuleInstruction
     */
    protected function loadCkEditorRequireJsModule(string $fieldId): JavaScriptModuleInstruction
    {
        $configuration = $this->prepareConfigurationForEditor();

        $externalPlugins = [];
        foreach ($this->getExtraPlugins() as $extraPluginName => $extraPluginConfig) {
            $configName = $extraPluginConfig['configName'] ?? $extraPluginName;
            if (!empty($extraPluginConfig['config']) && is_array($extraPluginConfig['config'])) {
                if (empty($configuration[$configName])) {
                    $configuration[$configName] = $extraPluginConfig['config'];
                } elseif (is_array($configuration[$configName])) {
                    $configuration[$configName] = array_replace_recursive($extraPluginConfig['config'], $configuration[$configName]);
                }
            }
            $configuration['extraPlugins'] = ($configuration['extraPlugins'] ?? '') . ',' . $extraPluginName;
            if (isset($this->data['parameterArray']['fieldConf']['config']['placeholder'])) {
                $configuration['editorplaceholder'] = (string)$this->data['parameterArray']['fieldConf']['config']['placeholder'];
            }

            $externalPlugins[] = [
                'name' => $extraPluginName,
                'resource' => $extraPluginConfig['resource'],
            ];
        }

        // Make a hash of the configuration and append it to CKEDITOR.timestamp
        // This will mitigate browser caching issue when plugins are updated
        $configurationHash = md5((string)json_encode($configuration));
        return JavaScriptModuleInstruction::forRequireJS('TYPO3/CMS/RteCkeditor/FormEngineInitializer', 'FormEngineInitializer')
            ->invoke('initializeCKEditor', [
                'fieldId' => $fieldId,
                'configuration' => $configuration,
                'configurationHash' => $configurationHash,
                'externalPlugins' => $externalPlugins,
            ]);
    }

    /**
     * Get configuration of external/additional plugins
     *
     * @return array
     */
    protected function getExtraPlugins(): array
    {
        $externalPlugins = $this->rteConfiguration['externalPlugins'] ?? [];
        $externalPlugins = $this->eventDispatcher
            ->dispatch(new BeforeGetExternalPluginsEvent($externalPlugins, $this->data))
            ->getConfiguration();

        $urlParameters = [
            'P' => [
                'table'      => $this->data['tableName'],
                'uid'        => $this->data['databaseRow']['uid'],
                'fieldName'  => $this->data['fieldName'],
                'recordType' => $this->data['recordTypeValue'],
                'pid'        => $this->data['effectivePid'],
                'richtextConfigurationName' => $this->data['parameterArray']['fieldConf']['config']['richtextConfigurationName'],
            ],
        ];

        $pluginConfiguration = [];
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        foreach ($externalPlugins as $pluginName => $configuration) {
            $pluginConfiguration[$pluginName] = [
                'configName' => $configuration['configName'] ?? $pluginName,
                'resource' => $this->resolveUrlPath($configuration['resource']),
            ];
            unset($configuration['configName']);
            unset($configuration['resource']);

            if ($configuration['route'] ?? null) {
                $configuration['routeUrl'] = (string)$uriBuilder->buildUriFromRoute($configuration['route'], $urlParameters);
            }

            $pluginConfiguration[$pluginName]['config'] = $configuration;
        }

        $pluginConfiguration = $this->eventDispatcher
            ->dispatch(new AfterGetExternalPluginsEvent($pluginConfiguration, $this->data))
            ->getConfiguration();
        return $pluginConfiguration;
    }

    /**
     * Add configuration to replace LLL: references with the translated value
     * @param array $configuration
     *
     * @return array
     */
    protected function replaceLanguageFileReferences(array $configuration): array
    {
        foreach ($configuration as $key => $value) {
            if (is_array($value)) {
                $configuration[$key] = $this->replaceLanguageFileReferences($value);
            } elseif (is_string($value) && stripos($value, 'LLL:') === 0) {
                $configuration[$key] = $this->getLanguageService()->sL($value);
            }
        }
        return $configuration;
    }

    /**
     * Add configuration to replace absolute EXT: paths with relative ones
     * @param array $configuration
     *
     * @return array
     */
    protected function replaceAbsolutePathsToRelativeResourcesPath(array $configuration): array
    {
        foreach ($configuration as $key => $value) {
            if (is_array($value)) {
                $configuration[$key] = $this->replaceAbsolutePathsToRelativeResourcesPath($value);
            } elseif (is_string($value) && PathUtility::isExtensionPath(strtoupper($value))) {
                $configuration[$key] = $this->resolveUrlPath($value);
            }
        }
        return $configuration;
    }

    /**
     * Resolves an EXT: syntax file to an absolute web URL
     *
     * @param string $value
     * @return string
     */
    protected function resolveUrlPath(string $value): string
    {
        return PathUtility::getPublicResourceWebPath($value);
    }

    /**
     * Compiles the configuration set from the outside
     * to have it easily injected into the CKEditor.
     *
     * @return array the configuration
     */
    protected function prepareConfigurationForEditor(): array
    {
        // Ensure custom config is empty so nothing additional is loaded
        // Of course this can be overridden by the editor configuration below
        $configuration = [
            'customConfig' => '',
        ];

        if ($this->data['parameterArray']['fieldConf']['config']['readOnly'] ?? false) {
            $configuration['readOnly'] = true;
        }

        if (is_array($this->rteConfiguration['config'] ?? null)) {
            $configuration = array_replace_recursive($configuration, $this->rteConfiguration['config']);
        }

        $configuration = $this->eventDispatcher
            ->dispatch(new BeforePrepareConfigurationForEditorEvent($configuration, $this->data))
            ->getConfiguration();

        // Set the UI language of the editor if not hard-coded by the existing configuration
        if (empty($configuration['language'])) {
            $userLang = (string)($GLOBALS['BE_USER']->user['lang'] ?: 'en');
            $configuration['language'] = $userLang === 'default' ? 'en' : $userLang;
        }
        $configuration['contentsLanguage'] = $this->getLanguageIsoCodeOfContent();

        // Replace all label references
        $configuration = $this->replaceLanguageFileReferences($configuration);
        // Replace all paths
        $configuration = $this->replaceAbsolutePathsToRelativeResourcesPath($configuration);

        // there are some places where we define an array, but it needs to be a list in order to work
        if (is_array($configuration['extraPlugins'] ?? null)) {
            $configuration['extraPlugins'] = implode(',', $configuration['extraPlugins']);
        }
        if (is_array($configuration['removePlugins'] ?? null)) {
            $configuration['removePlugins'] = implode(',', $configuration['removePlugins']);
        }
        if (is_array($configuration['removeButtons'] ?? null)) {
            $configuration['removeButtons'] = implode(',', $configuration['removeButtons']);
        }

        $configuration = $this->eventDispatcher
            ->dispatch(new AfterPrepareConfigurationForEditorEvent($configuration, $this->data))
            ->getConfiguration();

        return $configuration;
    }

    /**
     * @param string $itemFormElementName
     * @return string
     */
    protected function sanitizeFieldId(string $itemFormElementName): string
    {
        $fieldId = (string)preg_replace('/[^a-zA-Z0-9_:.-]/', '_', $itemFormElementName);
        return htmlspecialchars((string)preg_replace('/^[^a-zA-Z]/', 'x', $fieldId));
    }

    /**
     * Build JSON string for validations rules.
     *
     * @param array $config
     * @return string
     */
    protected function getValidationDataAsJsonString(array $config): string
    {
        $validationRules = [];
        if (!empty($config['eval'])) {
            $evalList = GeneralUtility::trimExplode(',', $config['eval'] ?? '', true);
            foreach ($evalList as $evalType) {
                $validationRules[] = [
                    'type' => $evalType,
                ];
            }
        }
        if (!empty($config['range'])) {
            $newValidationRule = [
                'type' => 'range',
            ];
            if (!empty($config['range']['lower'])) {
                $newValidationRule['lower'] = $config['range']['lower'];
            }
            if (!empty($config['range']['upper'])) {
                $newValidationRule['upper'] = $config['range']['upper'];
            }
            $validationRules[] = $newValidationRule;
        }
        if (!empty($config['maxitems']) || !empty($config['minitems'])) {
            $minItems = isset($config['minitems']) ? (int)$config['minitems'] : 0;
            $maxItems = isset($config['maxitems']) ? (int)$config['maxitems'] : 99999;
            $type = $config['type'] ?: 'range';
            $validationRules[] = [
                'type' => $type,
                'minItems' => $minItems,
                'maxItems' => $maxItems,
            ];
        }
        if (!empty($config['required'])) {
            $validationRules[] = ['type' => 'required'];
        }
        return json_encode($validationRules);
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
