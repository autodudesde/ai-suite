import Notification from '@typo3/backend/notification.js';
import MultiStepWizard from '@typo3/backend/multi-step-wizard.js';
import SaveHandling from '@autodudes/ai-suite/helper/image/save-handling.js';

class Metadata {
    addSelectionEventListeners(modal, data, slide) {
        this.backToPreviousSlideButton(modal, data);
        SaveHandling.selectionHandler(modal, 'label.ce-metadata-selection');
        this.saveGeneratedMetadataButton(modal, data);
    }

    backToPreviousSlideButton(modal, data) {
        let aiSuiteBackToPreviousSlideBtn = modal.find('.modal-body').find('button#aiSuiteBackToPreviousSlideBtn');
        aiSuiteBackToPreviousSlideBtn.on('click', function() {
            MultiStepWizard.unlockPrevStep().trigger('click');
        });
    }

    saveGeneratedMetadataButton(modal) {
        let self = this;
        let aiSuiteSaveMetadataBtn = modal.find('.modal-body').find('button#aiSuiteSaveMetadataBtn');
        aiSuiteSaveMetadataBtn.on('click', function() {
            let selectedSuggestion = modal.find('.metadata-suggestions input.metadata-selection:checked');
            if(selectedSuggestion.length === 0) {
                Notification.warning(TYPO3.lang['AiSuite.notification.generation.suggestions.missingSelection'], TYPO3.lang['AiSuite.notification.generation.suggestions.missingSelectionInfo'], 5);
            } else {
                let data = MultiStepWizard.setup.settings['postData'];
                self.insertSelectedSuggestions(data['table'], data['id'], data['fieldName'], selectedSuggestion);
                modal.find('input.use-for-selection:checked').each(function(index, item) {
                    self.insertSelectedSuggestions(data['table'], data['id'], item.value, selectedSuggestion);
                });
                MultiStepWizard.dismiss();
            }
        });
    }

    insertSelectedSuggestions(model, modelId, fieldName, selectedSuggestion) {
        if (document.querySelector('input[data-formengine-input-name="data[' + model + '][' + modelId + '][' + fieldName + ']"]')) {
            document.querySelector('input[data-formengine-input-name="data[' + model + '][' + modelId + '][' + fieldName + ']"]').value = selectedSuggestion.val();
            document.querySelector('input[name="data[' + model + '][' + modelId + '][' + fieldName + ']"]').value = selectedSuggestion.val();
        } else {
            document.querySelector('textarea[data-formengine-input-name="data[' + model + '][' + modelId + '][' + fieldName + ']"]').value = selectedSuggestion.val();
            document.querySelector('textarea[name="data[' + model + '][' + modelId + '][' + fieldName + ']"]').value = selectedSuggestion.val();
        }
    }
}

export default new Metadata();
