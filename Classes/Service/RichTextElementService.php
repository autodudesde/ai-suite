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

use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\Locales;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\SingletonInterface;
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
class RichTextElementService implements SingletonInterface
{
    protected PageRenderer $pageRenderer;

    protected EventDispatcherInterface $eventDispatcher;

    protected Locales $locales;

    protected UriBuilder $uriBuilder;

    protected BackendUserService $backendUserService;

    protected array $rteConfiguration = [];

    public function __construct(
        PageRenderer $pageRenderer,
        EventDispatcherInterface $eventDispatcher,
        Locales $locales,
        UriBuilder $uriBuilder,
        BackendUserService $backendUserService
    ) {
        $this->pageRenderer = $pageRenderer;
        $this->eventDispatcher = $eventDispatcher;
        $this->locales = $locales;
        $this->uriBuilder = $uriBuilder;
        $this->backendUserService = $backendUserService;
    }

    public function fetchRteConfig(array $data): array
    {
        $parameterArray = $data['parameterArray'];
        $config = $parameterArray['fieldConf']['config'];

        $itemFormElementName = 'data[' . $data['tableName'] . ']['. $data['databaseRow']['uid'] . '][' . $data['fieldName'] . ']';
        $fieldId = $this->sanitizeFieldId($itemFormElementName);

        $this->rteConfiguration = $config['richtextConfiguration']['editor'] ?? [];
        $ckeditorConfiguration = $this->resolveCkEditorConfiguration($data);

        $ckeditorAttributes = GeneralUtility::implodeAttributes([
            'id' => $fieldId . 'ckeditor5',
            'options' => GeneralUtility::jsonEncodeForHtmlAttribute($ckeditorConfiguration, false),
        ], true);

        $textareaAttributes = GeneralUtility::implodeAttributes([
            'slot' => 'textarea',
            'id' => $fieldId,
            'name' => $itemFormElementName,
            'rows' => '18',
            'class' => 'form-control',
            'data-formengine-validation-rules' => $this->getValidationDataAsJsonString($config),
        ], true);

        $this->pageRenderer->loadJavaScriptModule('@typo3/rte-ckeditor/ckeditor5.js');

        $uiLanguage = $ckeditorConfiguration['language']['ui'];
        if ($this->translationExists($uiLanguage)) {
            $this->pageRenderer->loadJavaScriptModule('@typo3/ckeditor5/translations/' . $uiLanguage . '.js');
        }

        $contentLanguage = $ckeditorConfiguration['language']['content'];
        if ($this->translationExists($contentLanguage)) {
            $this->pageRenderer->loadJavaScriptModule('@typo3/ckeditor5/translations/' . $contentLanguage . '.js');
        }
        $this->pageRenderer->addCssFile('EXT:rte_ckeditor/Resources/Public/Css/editor.css');

        return [
            'ckeditorAttributes' => $ckeditorAttributes,
            'textareaAttributes' => $textareaAttributes
        ];
    }

    /**
     * Determine the contents language iso code
     */
    protected function getLanguageIsoCodeOfContent(array $data): string
    {
        $currentLanguageUid = ($data['databaseRow']['sys_language_uid'] ?? 0);
        if (is_array($currentLanguageUid)) {
            $currentLanguageUid = $currentLanguageUid[0];
        }
        $contentLanguageUid = (int)max($currentLanguageUid, 0);
        if ($contentLanguageUid) {
            // the language rows might not be fully initialized, so we fall back to en-US in this case
            $contentLanguage = $data['systemLanguageRows'][$currentLanguageUid]['iso'] ?? 'en-US';
        } else {
            $contentLanguage = $this->rteConfiguration['config']['defaultContentLanguage'] ?? 'en-US';
        }
        $languageCodeParts = explode('_', $contentLanguage);
        $contentLanguage = strtolower($languageCodeParts[0]) . (!empty($languageCodeParts[1]) ? '_' . strtoupper($languageCodeParts[1]) : '');
        // Find the configured language in the list of localization locales
        // If not found, default to 'en'
        if ($contentLanguage === 'default' || !$this->locales->isValidLanguageKey($contentLanguage)) {
            $contentLanguage = 'en';
        }
        return $contentLanguage;
    }

    protected function resolveCkEditorConfiguration(array $data): array
    {
        $configuration = $this->prepareConfigurationForEditor($data);

        foreach ($this->getExtraPlugins($data) as $extraPluginName => $extraPluginConfig) {
            $configName = $extraPluginConfig['configName'] ?? $extraPluginName;
            if (!empty($extraPluginConfig['config']) && is_array($extraPluginConfig['config'])) {
                if (empty($configuration[$configName])) {
                    $configuration[$configName] = $extraPluginConfig['config'];
                } elseif (is_array($configuration[$configName])) {
                    $configuration[$configName] = array_replace_recursive($extraPluginConfig['config'], $configuration[$configName]);
                }
            }
        }
        if (isset($data['parameterArray']['fieldConf']['config']['placeholder'])) {
            $configuration['placeholder'] = (string)$data['parameterArray']['fieldConf']['config']['placeholder'];
        }
        return $configuration;
    }

    /**
     * Get configuration of external/additional plugins
     */
    protected function getExtraPlugins(array $data): array
    {
        $externalPlugins = $this->rteConfiguration['externalPlugins'] ?? [];
        $externalPlugins = $this->eventDispatcher
            ->dispatch(new BeforeGetExternalPluginsEvent($externalPlugins, $data))
            ->getConfiguration();

        $urlParameters = [
            'P' => [
                'table'      => $data['tableName'],
                'uid'        => $data['databaseRow']['uid'],
                'fieldName'  => $data['fieldName'],
                'recordType' => $data['recordTypeValue'],
                'pid'        => $data['effectivePid'],
                'richtextConfigurationName' => $data['parameterArray']['fieldConf']['config']['richtextConfigurationName'],
            ],
        ];

        $pluginConfiguration = [];
        foreach ($externalPlugins as $pluginName => $configuration) {
            $pluginConfiguration[$pluginName] = [
                'configName' => $configuration['configName'] ?? $pluginName,
            ];
            unset($configuration['configName']);
            // CKEditor4 style config, unused in CKEditor5 and not forwarded to the resutling plugin config
            unset($configuration['resource']);

            if ($configuration['route'] ?? null) {
                $configuration['routeUrl'] = (string)$this->uriBuilder->buildUriFromRoute($configuration['route'], $urlParameters);
            }

            $pluginConfiguration[$pluginName]['config'] = $configuration;
        }

        $pluginConfiguration = $this->eventDispatcher
            ->dispatch(new AfterGetExternalPluginsEvent($pluginConfiguration, $data))
            ->getConfiguration();
        return $pluginConfiguration;
    }

    /**
     * Add configuration to replace LLL: references with the translated value
     */
    protected function replaceLanguageFileReferences(array $configuration): array
    {
        foreach ($configuration as $key => $value) {
            if (is_array($value)) {
                $configuration[$key] = $this->replaceLanguageFileReferences($value);
            } elseif (is_string($value)) {
                $configuration[$key] = $this->getLanguageService()->sL($value);
            }
        }
        return $configuration;
    }

    /**
     * Add configuration to replace absolute EXT: paths with relative ones
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
    protected function prepareConfigurationForEditor(array $data): array
    {
        // Ensure custom config is empty so nothing additional is loaded
        // Of course this can be overridden by the editor configuration below
        $configuration = [
            'customConfig' => '',
        ];

        if ($data['parameterArray']['fieldConf']['config']['readOnly'] ?? false) {
            $configuration['readOnly'] = true;
        }

        if (is_array($this->rteConfiguration['config'] ?? null)) {
            $configuration = array_replace_recursive($configuration, $this->rteConfiguration['config']);
        }

        $configuration = $this->eventDispatcher
            ->dispatch(new BeforePrepareConfigurationForEditorEvent($configuration, $data))
            ->getConfiguration();

        // Set the UI language of the editor if not hard-coded by the existing configuration
        if (empty($configuration['language']) ||
            (is_array($configuration['language']) && empty($configuration['language']['ui']))
        ) {
            $userLang = (string)($this->backendUserService->getBackendUser()->user['lang'] ?: 'en');
            $configuration['language']['ui'] = $userLang === 'default' ? 'en' : $userLang;
        } elseif (!is_array($configuration['language'])) {
            $configuration['language'] = [
                'ui' => $configuration['language'],
            ];
        }
        $configuration['language']['content'] = $this->getLanguageIsoCodeOfContent($data);

        // Replace all label references
        $configuration = $this->replaceLanguageFileReferences($configuration);
        // Replace all paths
        $configuration = $this->replaceAbsolutePathsToRelativeResourcesPath($configuration);

        // unless explicitly set, the debug mode is enabled in development context
        if (!isset($configuration['debug'])) {
            $configuration['debug'] = ($GLOBALS['TYPO3_CONF_VARS']['BE']['debug'] ?? false) && Environment::getContext()->isDevelopment();
        }

        // The removePlugins option needs to be assigned as an array in CKEditor5.
        // While we recommended passing the option already as an array, CKEditor4
        // needed a comma-separated string. The conversion was only handled if the
        // Integrator passed an array, which means if someone already provided a
        // comma-separated string the option was simply passed as is to the Editor.
        // To avoid javascript errors we are going to migrate it to array for now.
        // The possibility to pass the option as a string is deprecated and will be
        // removed with version 13.
        if (isset($configuration['removePlugins']) && !is_array($configuration['removePlugins'])) {
            trigger_error('Passing the CKEditor removePlugins option as string is deprecated, use an array instead. Support for passing the option as string will be removed in TYPO3 v13.0.', E_USER_DEPRECATED);
            $configuration['removePlugins'] = explode(',', $configuration['removePlugins']);
        }

        $configuration = $this->eventDispatcher
            ->dispatch(new AfterPrepareConfigurationForEditorEvent($configuration, $data))
            ->getConfiguration();

        return $configuration;
    }

    protected function sanitizeFieldId(string $itemFormElementName): string
    {
        $fieldId = (string)preg_replace('/[^a-zA-Z0-9_:-]/', '_', $itemFormElementName);
        return htmlspecialchars((string)preg_replace('/^[^a-zA-Z]/', 'x', $fieldId));
    }

    protected function translationExists(string $language): bool
    {
        $fileName = GeneralUtility::getFileAbsFileName('EXT:rte_ckeditor/Resources/Public/Contrib/translations/' . $language . '.js');
        return file_exists($fileName);
    }

    protected function getValidationDataAsJsonString(array $config): string
    {
        $validationRules = [];
        if (!empty($config['eval'])) {
            $evalList = GeneralUtility::trimExplode(',', $config['eval'], true);
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
        if (!empty($config['min'])) {
            $validationRules[] = ['type' => 'min'];
        }
        return json_encode($validationRules);
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
