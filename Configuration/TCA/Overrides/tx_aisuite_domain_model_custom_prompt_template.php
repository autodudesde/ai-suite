<?php

$exclusionTabList = [
    'ext-news',
    'container',
    'data',
    'lists',
    'menu',
    'special',
    'plugins',
    'social',
    'form',
];
$exclusionCTypeList = [
    'csv',
    'external_media',
    'menu_card_list',
    'menu_card_dir',
    'menu_thumbnail_list',
    'menu_thumbnail_dir',
    'social_links'
];

$cTypes = [];
$types = $GLOBALS['TCA']['tt_content']['columns']['CType']['config']['items'];
foreach ($GLOBALS['TCA']['tt_content']['columns']['CType']['config']['items'] as $val) {
    if (array_key_exists('label', $val) && array_key_exists('value', $val) && array_key_exists('group', $val)) {
        if(!in_array($val['group'], $exclusionTabList) && $val['value'] !== '--div--') {
            $cTypes[] = [$val['label'], $val['value']];
        }
    } else if (array_key_exists('0', $val) && array_key_exists('1', $val) && array_key_exists('3', $val)) {
        if(!in_array($val['3'], $exclusionTabList) && !in_array($val['1'], $exclusionCTypeList) && $val['1'] !== '--div--') {
            $cTypes[] = [$val['0'], $val['1']];
        }
    }
    if(array_key_exists('value', $val) && $val['value'] === 'CSV') {
        $test = 0;
    }
}

$GLOBALS['TCA']['tx_aisuite_domain_model_custom_prompt_template']['columns']['type']['config']['items'] = $cTypes;
