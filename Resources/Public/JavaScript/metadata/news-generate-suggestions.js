import Notification from "@typo3/backend/notification.js";
import Ajax from "@autodudes/ai-suite/helper/ajax.js";
import Metadata from "@autodudes/ai-suite/helper/metadata.js";

class NewsGenerateSuggestions {
    constructor() {
        this.addEventListener();
    }

    addEventListener() {
        let handleResponse = this.handleResponse;
        let executeRequest = Ajax.sendMetadataAjaxRequest;

        document.querySelectorAll('.ai-suite-news-suggestions-generation-btn').forEach(function(button) {
            button.addEventListener("click", function(ev) {
                ev.preventDefault();

                let newsId = parseInt(this.getAttribute('data-news-id'));
                let folderId = parseInt(this.getAttribute('data-folder-id'));
                let fieldName = this.getAttribute('data-field-name');
                let postData = {
                    newsId: newsId,
                    folderId: folderId
                };
                executeRequest(newsId, fieldName, postData, handleResponse, 'news_');
            });
        });
    }

    /**
     *
     * @param newsId
     * @param fieldName
     * @param responseBody
     */
    handleResponse(newsId, fieldName, responseBody) {
        let selection = Metadata.getSelectionOptions(responseBody.output);
        document.getElementById(fieldName+'_generation').closest('.formengine-field-item').append(selection);
        if(document.getElementById('suggestionBtnSet')) {
            document.getElementById('suggestionBtnSet').addEventListener('click', function(ev) {
                ev.preventDefault();
                let selectedSuggestion = document.querySelector('input[name="generatedSuggestions"]:checked');
                if(selectedSuggestion === null) {
                    Notification.info(TYPO3.lang['AiSuite.notification.generation.suggestions.missingSelection'], TYPO3.lang['AiSuite.notification.generation.suggestions.missingSelectionInfo'], 8);
                } else {
                    Metadata.insertSelectedSuggestions('tx_news_domain_model_news', newsId, fieldName, selectedSuggestion);
                    selection.remove();
                }
            });
        }
        Metadata.addRemoveButtonListener(selection);
    }
}

export default new NewsGenerateSuggestions();
