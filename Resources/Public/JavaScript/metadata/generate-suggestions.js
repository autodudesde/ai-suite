import Notification from "@typo3/backend/notification.js";
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

                let pageId = parseInt(this.getAttribute('data-page-id'));
                let fieldName = this.getAttribute('data-field-name');
                let postData = {
                    pageId: pageId
                };
                executeRequest(pageId, fieldName, postData, handleResponse, '', addSelectionToAdditionalFields);
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
                    let addToAdditionalFields = false;
                    if(addToAdditionalFieldsCheckbox !== null) {
                        addToAdditionalFields = true;
                    }
                    Metadata.insertSelectedSuggestions('pages', pageId, fieldName, selectedSuggestion, addToAdditionalFields, addSelectionToAdditionalFields);
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
    }
}

export default new GenerateSuggestions();
