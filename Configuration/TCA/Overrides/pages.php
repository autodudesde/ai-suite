<?php

$GLOBALS['TCA']['pages']['columns']['description']['config']['fieldControl']['tx_aisuite_custom_field']['renderType'] = 'aiSeoMetaDescription';

$GLOBALS['TCA']['pages']['columns']['seo_title']['config']['fieldControl']['tx_aisuite_custom_field']['renderType'] = 'aiSeoPageTitle';

$GLOBALS['TCA']['pages']['columns']['og_title']['config']['fieldControl']['tx_aisuite_custom_field']['renderType'] = 'aiSeoOpenGraphTitle';

$GLOBALS['TCA']['pages']['columns']['twitter_title']['config']['fieldControl']['tx_aisuite_custom_field']['renderType'] = 'aiSeoTwitterTitle';

$GLOBALS['TCA']['pages']['columns']['og_description']['config']['fieldControl']['tx_aisuite_custom_field']['renderType'] = 'aiSeoOpenGraphDescription';

$GLOBALS['TCA']['pages']['columns']['twitter_description']['config']['fieldControl']['tx_aisuite_custom_field']['renderType'] = 'aiSeoTwitterDescription';

$GLOBALS['TCA']['pages']['columns']['abstract']['config']['fieldControl']['tx_aisuite_custom_field']['renderType'] = 'aiSeoAbstract';

// The disclosure sits on every page field AI Suite can fill, next to the button that fills it: the
// notice is a statement about the text in front of the editor and ends when they rewrite that field.
foreach ([
    'abstract',
    'description',
    'seo_title',
    'og_title',
    'og_description',
    'twitter_title',
    'twitter_description',
] as $aiSuiteProvenanceField) {
    $GLOBALS['TCA']['pages']['columns'][$aiSuiteProvenanceField]['config']['fieldInformation']['tx_aisuite_provenance']['renderType'] = 'aiProvenanceInformation';
}
