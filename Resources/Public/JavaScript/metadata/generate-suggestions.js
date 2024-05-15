import Notification from "@typo3/backend/notification.js";
import General from "@autodudes/ai-suite/helper/general.js";
import Ajax from "@autodudes/ai-suite/helper/ajax.js";
import Metadata from "@autodudes/ai-suite/helper/metadata.js";

class GenerateSuggestions {
    constructor() {
        this.addEventListener();
    }

    addEventListener() {
        let handleResponse = this.handleResponse;
        let executeRequest = Ajax.sendMetadataAjaxRequest;
        let addSelectionToAdditionalFields = this.addSelectionToAdditionalFields;

        document.querySelectorAll('.ai-suite-suggestions-generation-btn').forEach(function(button) {
            button.addEventListener("click", function(ev) {
                ev.preventDefault();
                let fieldName = this.getAttribute('data-field-name');
                if(
                    General.isUsable(this.getAttribute('data-sys-file-metadata-id')) &&
                    General.isUsable(this.getAttribute('data-sys-file-id'))
                ) {
                    let sysFileMetadataId = parseInt(this.getAttribute('data-sys-file-metadata-id'));
                    let sysFileId = parseInt(this.getAttribute('data-sys-file-id'));
                    let postData = {
                        sysFileMetadataId: sysFileMetadataId,
                        sysFileId: sysFileId
                    };
                    executeRequest(sysFileMetadataId, fieldName, postData, handleResponse, '', addSelectionToAdditionalFields);
                } else {
                    let pageId = parseInt(this.getAttribute('data-page-id'));
                    let postData = {
                        pageId: pageId
                    };
                    executeRequest(pageId, fieldName, postData, handleResponse, '', addSelectionToAdditionalFields);
                }
            });
        });
    }

    /**
     *
     * @param pageId
     * @param fieldName
     * @param responseBody
     * @param addSelectionToAdditionalFields
     */
    handleResponse(pageId, fieldName, responseBody, addSelectionToAdditionalFields) {
        let selection = Metadata.getSelectionOptions(responseBody.output);
        document.getElementById(fieldName+'_generation').closest('.formengine-field-item').append(selection);
        if(document.getElementById('suggestionBtnSet')) {
            document.getElementById('suggestionBtnSet').addEventListener('click', function(ev) {
                ev.preventDefault();
                let selectedSuggestion = document.querySelector('input[name="generatedSuggestions"]:checked');
                if(selectedSuggestion === null) {
                    Notification.info(TYPO3.lang['AiSuite.notification.generation.suggestions.missingSelection'], TYPO3.lang['AiSuite.notification.generation.suggestions.missingSelectionInfo'], 8);
                } else {
                    let addToAdditionalFieldsCheckbox = document.querySelector('input[name="addToAdditionalFields"]:checked');
                    let addToAdditionalSysFileFieldsCheckbox = document.querySelector('input[name="addToAdditionalSysFileFields"]:checked');
                    let addToAdditionalFields = false;
                    if(General.isUsable(addToAdditionalFieldsCheckbox) || General.isUsable(addToAdditionalSysFileFieldsCheckbox)) {
                        addToAdditionalFields = true;
                    }
                    let type = 'pages';
                    if(fieldName === 'title' || fieldName === 'alternative') {
                        type = 'sys_file_metadata';
                    }
                    Metadata.insertSelectedSuggestions(type, pageId, fieldName, selectedSuggestion, addToAdditionalFields, addSelectionToAdditionalFields);
                    selection.remove();
                }
            });
        }
        Metadata.addRemoveButtonListener(selection);
    }

    addSelectionToAdditionalFields(pageId, fieldName, selectedSuggestionValue) {
        if(fieldName === 'seo_title') {
            Notification.info(TYPO3.lang['AiSuite.notification.generation.copy'], TYPO3.lang['AiSuite.notification.generation.suggestions.ogTwitterTitlesUpdated'], 8);
            document.querySelector('input[data-formengine-input-name="data[pages]['+pageId+'][og_title]"]').value = selectedSuggestionValue;
            document.querySelector('input[name="data[pages]['+pageId+'][og_title]"]').value = selectedSuggestionValue;
            document.querySelector('input[data-formengine-input-name="data[pages]['+pageId+'][twitter_title]"]').value = selectedSuggestionValue;
            document.querySelector('input[name="data[pages]['+pageId+'][twitter_title]"]').value = selectedSuggestionValue;
        }
        if(fieldName === 'description') {
            Notification.info(TYPO3.lang['AiSuite.notification.generation.copy'], TYPO3.lang['AiSuite.notification.generation.suggestions.ogTwitterDescriptionsUpdated'], 8);
            document.querySelector('textarea[data-formengine-input-name="data[pages]['+pageId+'][og_description]"]').value = selectedSuggestionValue;
            document.querySelector('textarea[name="data[pages]['+pageId+'][og_description]"]').value = selectedSuggestionValue;
            document.querySelector('textarea[data-formengine-input-name="data[pages]['+pageId+'][twitter_description]"]').value = selectedSuggestionValue;
            document.querySelector('textarea[name="data[pages]['+pageId+'][twitter_description]"]').value = selectedSuggestionValue;
        }
        if(fieldName === 'title' || fieldName === 'alternative') {
            Notification.info(TYPO3.lang['AiSuite.notification.generation.copy'], TYPO3.lang['AiSuite.notification.generation.suggestions.sysFileMetadataUpdated'], 8);
            document.querySelector('input[data-formengine-input-name="data[sys_file_metadata]['+pageId+'][title]"]').value = selectedSuggestionValue;
            document.querySelector('input[name="data[sys_file_metadata]['+pageId+'][title]"]').value = selectedSuggestionValue;
            document.querySelector('input[data-formengine-input-name="data[sys_file_metadata]['+pageId+'][alternative]"]').value = selectedSuggestionValue;
            document.querySelector('input[name="data[sys_file_metadata]['+pageId+'][alternative]"]').value = selectedSuggestionValue;
        }
    }
}

export default new GenerateSuggestions();
