<?php

$GLOBALS['TCA']['sys_file_reference']['columns']['alternative']['config']['fieldControl']['tx_aisuite_custom_field']['renderType'] = 'aiSysFileReferenceAlternative';

$GLOBALS['TCA']['sys_file_reference']['columns']['title']['config']['fieldControl']['tx_aisuite_custom_field']['renderType'] = 'aiSysFileReferenceTitle';

// The alternative text of a reference is a sentence an editor reads and corrects in the element's
// own form, so the disclosure belongs next to it. The register entry stays on the reference for
// exactly that reason; the module still lists the element, never the reference.
foreach (['title', 'alternative'] as $aiSuiteProvenanceField) {
    $GLOBALS['TCA']['sys_file_reference']['columns'][$aiSuiteProvenanceField]['config']['fieldInformation']['tx_aisuite_provenance']['renderType'] = 'aiProvenanceInformation';
}
