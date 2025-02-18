<?php

defined('TYPO3') || die('Access denied.');

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Backend\Controller\ContentElement\NewContentElementController::class] = [
    'className' => \AutoDudes\AiSuite\Controller\ContentElement\NewContentElementController::class,
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Backend\Controller\Page\LocalizationController::class] = [
    'className' => \AutoDudes\AiSuite\Controller\Page\LocalizationController::class,
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Backend\RecordList\DatabaseRecordList::class] = [
    'className' => \AutoDudes\AiSuite\Controller\RecordList\DatabaseRecordList::class,
];

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['ai_suite']
    = \AutoDudes\AiSuite\Hooks\TranslationHook::class;

if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('container')) {
    if (class_exists(\B13\Container\Hooks\Datahandler\CommandMapPostProcessingHook::class)) {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\B13\Container\Hooks\Datahandler\CommandMapPostProcessingHook::class] = [
            'className' => \AutoDudes\AiSuite\Hooks\CommandMapPostProcessingHook::class,
        ];
    }
}

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1676410677] = [
    'nodeName' => 'aiSeoMetaDescription',
    'priority' => 30,
    'class' => \AutoDudes\AiSuite\FormEngine\FieldControl\AiSeoMetaDescription::class
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1676410679] = [
    'nodeName' => 'aiSeoPageTitle',
    'priority' => 30,
    'class' => \AutoDudes\AiSuite\FormEngine\FieldControl\AiSeoPageTitle::class
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1676410680] = [
    'nodeName' => 'aiSeoOpenGraphTitle',
    'priority' => 30,
    'class' => \AutoDudes\AiSuite\FormEngine\FieldControl\AiSeoOpenGraphTitle::class
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1676410681] = [
    'nodeName' => 'aiSeoTwitterTitle',
    'priority' => 30,
    'class' => \AutoDudes\AiSuite\FormEngine\FieldControl\AiSeoTwitterTitle::class
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1676410682] = [
    'nodeName' => 'aiSeoOpenGraphDescription',
    'priority' => 30,
    'class' => \AutoDudes\AiSuite\FormEngine\FieldControl\AiSeoOpenGraphDescription::class
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1676410683] = [
    'nodeName' => 'aiSeoTwitterDescription',
    'priority' => 30,
    'class' => \AutoDudes\AiSuite\FormEngine\FieldControl\AiSeoTwitterDescription::class
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1676410687] = [
    'nodeName' => 'aiSysFileAlternative',
    'priority' => 30,
    'class' => \AutoDudes\AiSuite\FormEngine\FieldControl\FileList\AiSysFileAlternative::class
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1676410688] = [
    'nodeName' => 'aiSysFileTitle',
    'priority' => 30,
    'class' => \AutoDudes\AiSuite\FormEngine\FieldControl\FileList\AiSysFileTitle::class
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1676410689] = [
    'nodeName' => 'aiSysFileReferenceAlternative',
    'priority' => 30,
    'class' => \AutoDudes\AiSuite\FormEngine\FieldControl\SysFileReference\AiSysFileReferenceAlternative::class
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1676410690] = [
    'nodeName' => 'aiSysFileReferenceTitle',
    'priority' => 30,
    'class' => \AutoDudes\AiSuite\FormEngine\FieldControl\SysFileReference\AiSysFileReferenceTitle::class
];

if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('news')) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1676410684] = [
        'nodeName' => 'aiNewsMetaDescription',
        'priority' => 30,
        'class' => \AutoDudes\AiSuite\FormEngine\FieldControl\News\AiNewsMetaDescription::class
    ];

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1676410685] = [
        'nodeName' => 'aiNewsAlternativeTitle',
        'priority' => 30,
        'class' => \AutoDudes\AiSuite\FormEngine\FieldControl\News\AiNewsAlternativeTitle::class
    ];
}
