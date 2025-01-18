import Notification from "@typo3/backend/notification.js";
import MultiStepWizard from "@typo3/backend/multi-step-wizard.js";
import GenerationHandling from "@autodudes/ai-suite/helper/image/generation-handling.js";
import General from "@autodudes/ai-suite/helper/general.js";
import Ajax from "@autodudes/ai-suite/helper/ajax.js";

class ResponseHandling {
    handleResponse(res, errorMessage) {
        if(res !== null) {
            if(res.error) {
                Notification.error(TYPO3.lang['AiSuite.notification.generation.requestError'], res.error);
            } else {
                MultiStepWizard.set('generatedData', res.output);
            }
        } else {
            Notification.error(TYPO3.lang['AiSuite.notification.generation.error'], errorMessage);
        }
    }

    handleResponseContentElement(res, data, errorMessage, preselectionContent = '') {
        if(res !== null) {
            if(res.error) {
                Notification.error(TYPO3.lang['AiSuite.notification.generation.requestError'], res.error);
                if(data.table === 'tt_content') {
                    document.querySelector('form[name="requestContent"] #fields-' + data.table + ' #generated-images-' + data.fieldName).innerHTML = preselectionContent;
                } else {
                    document.querySelector('form[name="requestContent"] #fields-' + data.table +'-' + data.position + ' #generated-images-' + data.fieldName).innerHTML = preselectionContent;
                }
            } else {
                let regenerateButton;
                if(data.table === 'tt_content') {
                    document.querySelector('form[name="requestContent"] #fields-' + data.table + ' #generated-images-' + data.fieldName).innerHTML = res.output;
                    regenerateButton = document.querySelector('form[name="requestContent"] #fields-' + data.table + ' #generated-images-' + data.fieldName).querySelector('.create-content-update-image');
                } else {
                    document.querySelector('form[name="content"] #fields-' + data.table +'-' + data.position + ' #generated-images-' + data.fieldName).innerHTML = res.output;
                    regenerateButton = document.querySelector('form[name="requestContent"] #fields-' + data.table +'-' + data.position + ' #generated-images-' + data.fieldName).querySelector('.create-content-update-image');
                }
                if (regenerateButton !== null) {
                    regenerateButton.addEventListener('click', function(ev) {
                        ev.preventDefault();
                        GenerationHandling.showGeneralImageSettingsModal(data, 'ContentElement');
                    });
                }
            }
        } else {
            Notification.error(TYPO3.lang['AiSuite.notification.generation.error'], errorMessage);
            if(data.table === 'tt_content') {
                document.querySelector('form[name="requestContent"] #fields-' + data.table + ' #generated-images-' + data.fieldName).innerHTML = preselectionContent;
            } else {
                document.querySelector('form[name="requestContent"] #fields-' + data.table +'-' + data.position + ' #generated-images-' + data.fieldName).innerHTML = preselectionContent;
            }
        }
        MultiStepWizard.dismiss();
    }

    handleStatusResponse(res, modal) {
        let statusElement = modal.find('.modal-body').find('.spinner-wrapper .status');
        if(
            General.isUsable(statusElement) &&
            General.isUsable(res) &&
            General.isUsable(res.error) === false
        ) {
            statusElement.html(res.output);
        }
    }
    handleStatusContentElementResponse(res) {
        let statusElement = document.querySelector('.spinner-overlay .status');
        if(
            General.isUsable(statusElement) &&
            General.isUsable(res) &&
            General.isUsable(res.error) === false
        ) {
            statusElement.innerHTML = res.output;
        }
    }
}

export default new ResponseHandling();
