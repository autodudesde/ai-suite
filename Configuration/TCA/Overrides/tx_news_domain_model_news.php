<?php

if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('news')) {
    $GLOBALS['TCA']['tx_news_domain_model_news']['columns']['description']['config'] = array_merge_recursive(
        $GLOBALS['TCA']['tx_news_domain_model_news']['columns']['description']['config'],
        [
            'fieldControl' => [
                'tx_aisuite_custom_field' => [
                    'renderType' => 'aiNewsMetaDescription'
                ]
            ]
        ]
    );

    $GLOBALS['TCA']['tx_news_domain_model_news']['columns']['alternative_title']['config'] = array_merge_recursive(
        $GLOBALS['TCA']['tx_news_domain_model_news']['columns']['alternative_title']['config'],
        [
            'fieldControl' => [
                'tx_aisuite_custom_field' => [
                    'renderType' => 'aiNewsAlternativeTitle'
                ]
            ]
        ]
    );
}
