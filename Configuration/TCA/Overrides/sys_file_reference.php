<?php

$GLOBALS['TCA']['sys_file_reference']['columns']['alternative']['config'] = array_merge_recursive(
    $GLOBALS['TCA']['sys_file_reference']['columns']['alternative']['config'],
    [
        'fieldControl' => [
            'tx_aisuite_custom_field' => [
                'renderType' => 'aiSysFileReferenceAlternative'
            ]
        ]
    ]
);

$GLOBALS['TCA']['sys_file_reference']['columns']['title']['config'] = array_merge_recursive(
    $GLOBALS['TCA']['sys_file_reference']['columns']['title']['config'],
    [
        'fieldControl' => [
            'tx_aisuite_custom_field' => [
                'renderType' => 'aiSysFileReferenceTitle'
            ]
        ]
    ]
);
