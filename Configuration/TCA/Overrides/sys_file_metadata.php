<?php

$GLOBALS['TCA']['sys_file_metadata']['columns']['alternative']['config'] = array_merge_recursive(
    $GLOBALS['TCA']['sys_file_metadata']['columns']['alternative']['config'],
    [
        'fieldControl' => [
            'tx_aisuite_custom_field' => [
                'renderType' => 'aiSysFileAlternative'
            ]
        ]
    ]
);
$GLOBALS['TCA']['sys_file_metadata']['columns']['title']['config'] = array_merge_recursive(
    $GLOBALS['TCA']['sys_file_metadata']['columns']['title']['config'],
    [
        'fieldControl' => [
            'tx_aisuite_custom_field' => [
                'renderType' => 'aiSysFileTitle'
            ]
        ]
    ]
);
