import Notification from '@typo3/backend/notification.js';
import MultiStepWizard from '@autodudes/ai-suite/helper/multi-step-wizard-patch.js';
import Provenance from '@autodudes/ai-suite/helper/provenance.js';

class Metadata {
    addSelectionEventListeners(modal, data, slide) {
        this.backToPreviousSlideButton(modal, data);
        this.saveGeneratedMetadataButton(modal, data);
    }

    backToPreviousSlideButton(modal, data) {
        let aiSuiteBackToPreviousSlideBtn = modal.find('.panel-body').find('button#aiSuiteBackToPreviousSlideBtn');
        aiSuiteBackToPreviousSlideBtn.on('click', function() {
            MultiStepWizard.unlockPrevStep().trigger('click');
        });
    }

    saveGeneratedMetadataButton(modal) {
        let self = this;
        let aiSuiteSaveMetadataBtn = modal.find('.panel-body').find('button#aiSuiteSaveMetadataBtn');
        aiSuiteSaveMetadataBtn.on('click', function() {
            let selectedSuggestion = modal.find('.metadata-suggestions input.metadata-selection:checked');
            if(selectedSuggestion.length === 0) {
                Notification.warning(TYPO3.lang['aiSuite.notification.generation.workflow.missingSelection'], TYPO3.lang['aiSuite.notification.generation.suggestions.missingSelectionInfo'], 5);
            } else {
                let data = MultiStepWizard.setup.settings['postData'];
                self.insertSelectedSuggestions(data['table'], data['id'], data['fieldName'], selectedSuggestion, data['textAiModel']);
                modal.find('input.use-for-selection:checked').each(function(index, item) {
                    self.insertSelectedSuggestions(data['table'], data['id'], item.value, selectedSuggestion, data['textAiModel']);
                });
                MultiStepWizard.dismiss();
            }
        });
    }

    insertSelectedSuggestions(table, uid, fieldName, selectedSuggestion, aiModel) {
        Provenance.recordAssisted(table, uid, fieldName, 'metadata', aiModel);

        if (document.querySelector('input[data-formengine-input-name="data[' + table + '][' + uid + '][' + fieldName + ']"]')) {
            document.querySelector('input[data-formengine-input-name="data[' + table + '][' + uid + '][' + fieldName + ']"]').value = selectedSuggestion.val();
            document.querySelector('input[name="data[' + table + '][' + uid + '][' + fieldName + ']"]').value = selectedSuggestion.val();
        } else {
            document.querySelector('textarea[data-formengine-input-name="data[' + table + '][' + uid + '][' + fieldName + ']"]').value = selectedSuggestion.val();
            document.querySelector('textarea[name="data[' + table + '][' + uid + '][' + fieldName + ']"]').value = selectedSuggestion.val();
        }
    }
}

export default new Metadata();
