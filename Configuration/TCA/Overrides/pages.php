<?php

$GLOBALS['TCA']['pages']['columns']['description']['config'] = array_merge_recursive(
    $GLOBALS['TCA']['pages']['columns']['description']['config'],
    [
        'fieldControl' => [
            'tx_aisuite_custom_field' => [
                'renderType' => 'aiSeoMetaDescription'
            ]
        ]
    ]
);

$GLOBALS['TCA']['pages']['columns']['seo_title']['config'] = array_merge_recursive(
    $GLOBALS['TCA']['pages']['columns']['seo_title']['config'],
    [
        'fieldControl' => [
            'tx_aisuite_custom_field' => [
                'renderType' => 'aiSeoPageTitle'
            ]
        ]
    ]
);

$GLOBALS['TCA']['pages']['columns']['og_title']['config'] = array_merge_recursive(
    $GLOBALS['TCA']['pages']['columns']['og_title']['config'],
    [
        'fieldControl' => [
            'tx_aisuite_custom_field' => [
                'renderType' => 'aiSeoOpenGraphTitle'
            ]
        ]
    ]
);

$GLOBALS['TCA']['pages']['columns']['twitter_title']['config'] = array_merge_recursive(
    $GLOBALS['TCA']['pages']['columns']['twitter_title']['config'],
    [
        'fieldControl' => [
            'tx_aisuite_custom_field' => [
                'renderType' => 'aiSeoTwitterTitle'
            ]
        ]
    ]
);

$GLOBALS['TCA']['pages']['columns']['og_description']['config'] = array_merge_recursive(
    $GLOBALS['TCA']['pages']['columns']['og_description']['config'],
    [
        'fieldControl' => [
            'tx_aisuite_custom_field' => [
                'renderType' => 'aiSeoOpenGraphDescription'
            ]
        ]
    ]
);

$GLOBALS['TCA']['pages']['columns']['twitter_description']['config'] = array_merge_recursive(
    $GLOBALS['TCA']['pages']['columns']['twitter_description']['config'],
    [
        'fieldControl' => [
            'tx_aisuite_custom_field' => [
                'renderType' => 'aiSeoTwitterDescription'
            ]
        ]
    ]
);

$GLOBALS['TCA']['pages']['columns']['abstract']['config'] = array_merge_recursive(
    $GLOBALS['TCA']['pages']['columns']['abstract']['config'],
    [
        'fieldControl' => [
            'tx_aisuite_custom_field' => [
                'renderType' => 'aiSeoAbstract'
            ]
        ]
    ]
);
