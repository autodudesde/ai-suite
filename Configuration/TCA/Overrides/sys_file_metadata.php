<?php

$GLOBALS['TCA']['sys_file_metadata']['columns']['alternative']['config'] = array_merge_recursive(
    $GLOBALS['TCA']['sys_file_metadata']['columns']['alternative']['config'],
    [
        'fieldControl' => [
            'importControl' => [
                'renderType' => 'aiSysFileAlternative'
            ]
        ]
    ]
);
$GLOBALS['TCA']['sys_file_metadata']['columns']['title']['config'] = array_merge_recursive(
    $GLOBALS['TCA']['sys_file_metadata']['columns']['title']['config'],
    [
        'fieldControl' => [
            'importControl' => [
                'renderType' => 'aiSysFileTitle'
            ]
        ]
    ]
);
