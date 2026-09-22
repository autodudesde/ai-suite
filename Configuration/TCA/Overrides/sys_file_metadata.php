<?php

$GLOBALS['TCA']['sys_file_metadata']['columns']['alternative']['config']['fieldControl']['tx_aisuite_custom_field']['renderType'] = 'aiSysFileAlternative';

$GLOBALS['TCA']['sys_file_metadata']['columns']['title']['config']['fieldControl']['tx_aisuite_custom_field']['renderType'] = 'aiSysFileTitle';

$GLOBALS['TCA']['sys_file_metadata']['columns']['description']['config']['fieldControl']['tx_aisuite_custom_field']['renderType'] = 'aiSysFileDescription';

// On every field an AI feature can fill, not only on the title: the field notice is about the text
// in front of the editor, and the file notice about the image they are describing.
foreach (['title', 'alternative', 'description'] as $aiSuiteProvenanceField) {
    $GLOBALS['TCA']['sys_file_metadata']['columns'][$aiSuiteProvenanceField]['config']['fieldInformation']['tx_aisuite_provenance']['renderType'] = 'aiProvenanceInformation';
}
