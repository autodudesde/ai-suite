<?php

use AutoDudes\AiSuite\Controller\Decorator\ContentElement\NewContentElementController;
use AutoDudes\AiSuite\Controller\Decorator\Page\LocalizationController;
use AutoDudes\AiSuite\FormEngine\FieldControl\AiSeoAbstract;
use AutoDudes\AiSuite\FormEngine\FieldControl\AiSeoMetaDescription;
use AutoDudes\AiSuite\FormEngine\FieldControl\AiSeoOpenGraphDescription;
use AutoDudes\AiSuite\FormEngine\FieldControl\AiSeoOpenGraphTitle;
use AutoDudes\AiSuite\FormEngine\FieldControl\AiSeoPageTitle;
use AutoDudes\AiSuite\FormEngine\FieldControl\AiSeoTwitterDescription;
use AutoDudes\AiSuite\FormEngine\FieldControl\AiSeoTwitterTitle;
use AutoDudes\AiSuite\FormEngine\FieldControl\FileList\AiSysFileAlternative;
use AutoDudes\AiSuite\FormEngine\FieldControl\FileList\AiSysFileDescription;
use AutoDudes\AiSuite\FormEngine\FieldControl\FileList\AiSysFileTitle;
use AutoDudes\AiSuite\FormEngine\FieldControl\News\AiNewsAlternativeTitle;
use AutoDudes\AiSuite\FormEngine\FieldControl\News\AiNewsMetaDescription;
use AutoDudes\AiSuite\FormEngine\FieldControl\SysFileReference\AiSysFileReferenceAlternative;
use AutoDudes\AiSuite\FormEngine\FieldControl\SysFileReference\AiSysFileReferenceTitle;
use AutoDudes\AiSuite\Hooks\CommandMapPostProcessingHook;
use AutoDudes\AiSuite\Hooks\GlobalInstructionHook;
use AutoDudes\AiSuite\Hooks\TranslationHook;
use TYPO3\CMS\Backend\RecordList\DatabaseRecordList;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

defined('TYPO3') || exit('Access denied.');

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][TYPO3\CMS\Backend\Controller\ContentElement\NewContentElementController::class] = [
    'className' => NewContentElementController::class,
];

if (ExtensionManagementUtility::isLoaded('container')) {
    if (class_exists(B13\Container\Hooks\Datahandler\CommandMapPostProcessingHook::class)) {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][B13\Container\Hooks\Datahandler\CommandMapPostProcessingHook::class] = [
            'className' => CommandMapPostProcessingHook::class,
        ];
    }
}

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1676410677] = [
    'nodeName' => 'aiSeoMetaDescription',
    'priority' => 30,
    'class' => AiSeoMetaDescription::class,
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1676410679] = [
    'nodeName' => 'aiSeoPageTitle',
    'priority' => 30,
    'class' => AiSeoPageTitle::class,
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1676410680] = [
    'nodeName' => 'aiSeoOpenGraphTitle',
    'priority' => 30,
    'class' => AiSeoOpenGraphTitle::class,
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1676410681] = [
    'nodeName' => 'aiSeoTwitterTitle',
    'priority' => 30,
    'class' => AiSeoTwitterTitle::class,
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1676410682] = [
    'nodeName' => 'aiSeoOpenGraphDescription',
    'priority' => 30,
    'class' => AiSeoOpenGraphDescription::class,
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1676410683] = [
    'nodeName' => 'aiSeoTwitterDescription',
    'priority' => 30,
    'class' => AiSeoTwitterDescription::class,
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1676410686] = [
    'nodeName' => 'aiSeoAbstract',
    'priority' => 30,
    'class' => AiSeoAbstract::class,
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1676410687] = [
    'nodeName' => 'aiSysFileAlternative',
    'priority' => 30,
    'class' => AiSysFileAlternative::class,
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1676410688] = [
    'nodeName' => 'aiSysFileTitle',
    'priority' => 30,
    'class' => AiSysFileTitle::class,
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1676410689] = [
    'nodeName' => 'aiSysFileReferenceAlternative',
    'priority' => 30,
    'class' => AiSysFileReferenceAlternative::class,
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1676410690] = [
    'nodeName' => 'aiSysFileReferenceTitle',
    'priority' => 30,
    'class' => AiSysFileReferenceTitle::class,
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1676410691] = [
    'nodeName' => 'aiSysFileDescription',
    'priority' => 30,
    'class' => AiSysFileDescription::class,
];

if (ExtensionManagementUtility::isLoaded('news')) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1676410684] = [
        'nodeName' => 'aiNewsMetaDescription',
        'priority' => 30,
        'class' => AiNewsMetaDescription::class,
    ];

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1676410685] = [
        'nodeName' => 'aiNewsAlternativeTitle',
        'priority' => 30,
        'class' => AiNewsAlternativeTitle::class,
    ];
}

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['ai_suite']
    = GlobalInstructionHook::class;

try {
    $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('ai_suite');
    if (!array_key_exists('disableTranslationFunctionality', $extensionConfiguration)
        || false === (bool) $extensionConfiguration['disableTranslationFunctionality']) {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][TYPO3\CMS\Backend\Controller\Page\LocalizationController::class] = [
            'className' => LocalizationController::class,
        ];
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][DatabaseRecordList::class] = [
            'className' => AutoDudes\AiSuite\Controller\Decorator\RecordList\DatabaseRecordList::class,
        ];
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['ai_suite']
            = TranslationHook::class;
    }
} catch (Throwable $e) {
}
