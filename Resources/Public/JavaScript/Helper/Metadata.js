define([
    "TYPO3/CMS/Backend/Notification",
    "TYPO3/CMS/Backend/MultiStepWizard",
    "TYPO3/CMS/AiSuite/Helper/Image/SaveHandling",
], function(
    Notification,
    MultiStepWizard,
    SaveHandling
) {

    function addSelectionEventListeners(modal, data, slide) {
        backToPreviousSlideButton(modal, data);
        SaveHandling.selectionHandler(modal, 'label.ce-metadata-selection');
        saveGeneratedMetadataButton(modal, data);
    }

    function backToPreviousSlideButton(modal, data) {
        let aiSuiteBackToPreviousSlideBtn = modal.find('.modal-body').find('button#aiSuiteBackToPreviousSlideBtn');
        aiSuiteBackToPreviousSlideBtn.on('click', function() {
            MultiStepWizard.unlockPrevStep().trigger('click');
        });
    }

    function saveGeneratedMetadataButton(modal) {
        let aiSuiteSaveMetadataBtn = modal.find('.modal-body').find('button#aiSuiteSaveMetadataBtn');
        aiSuiteSaveMetadataBtn.on('click', function() {
            let selectedSuggestion = modal.find('.metadata-suggestions input.metadata-selection:checked');
            if(selectedSuggestion.length === 0) {
                Notification.warning(TYPO3.lang['AiSuite.notification.generation.suggestions.missingSelection'], TYPO3.lang['AiSuite.notification.generation.suggestions.missingSelectionInfo'], 5);
            } else {
                let data = MultiStepWizard.setup.settings['postData'];
                insertSelectedSuggestions(data['table'], data['id'], data['fieldName'], selectedSuggestion);
                modal.find('input.use-for-selection:checked').each(function() {
                    insertSelectedSuggestions(data['table'], data['id'], $(this).val(), selectedSuggestion);
                });
                MultiStepWizard.dismiss();
            }
        });
    }
    /**
     * @param {string} model
     * @param {int} modelId
     * @param {string} fieldName
     * @param {object} selectedSuggestion
     */
    function insertSelectedSuggestions(model, modelId, fieldName, selectedSuggestion) {
        if (document.querySelector('input[data-formengine-input-name="data[' + model + '][' + modelId + '][' + fieldName + ']"]')) {
            document.querySelector('input[data-formengine-input-name="data[' + model + '][' + modelId + '][' + fieldName + ']"]').value = selectedSuggestion.val();
            document.querySelector('input[name="data[' + model + '][' + modelId + '][' + fieldName + ']"]').value = selectedSuggestion.val();
        } else {
            document.querySelector('textarea[data-formengine-input-name="data[' + model + '][' + modelId + '][' + fieldName + ']"]').value = selectedSuggestion.val();
            document.querySelector('textarea[name="data[' + model + '][' + modelId + '][' + fieldName + ']"]').value = selectedSuggestion.val();
        }
    }

    return {
        addSelectionEventListeners: addSelectionEventListeners,
        insertSelectedSuggestions: insertSelectedSuggestions,
    };
});

