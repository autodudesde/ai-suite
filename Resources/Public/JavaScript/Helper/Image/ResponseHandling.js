define([
    "TYPO3/CMS/Backend/Notification",
    "TYPO3/CMS/Backend/MultiStepWizard",
    "TYPO3/CMS/AiSuite/Helper/General",
    "require"
], function(
    Notification,
    MultiStepWizard,
    General,
    require
) {
    function handleResponse(res, errorMessage) {
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

    function handleResponseContentElement(res, data, errorMessage, showGeneralImageSettingsModal, preselectionContent = '') {
        if(res !== null) {
            if(res.error) {
                Notification.error(TYPO3.lang['AiSuite.notification.generation.requestError'], res.error);
                if(data.table === 'tt_content') {
                    document.querySelector('form[name="content"] #fields-' + data.table + ' #generated-images-' + data.fieldName).innerHTML = preselectionContent;
                } else {
                    document.querySelector('form[name="content"] #fields-' + data.table +'-' + data.position + ' #generated-images-' + data.fieldName).innerHTML = preselectionContent;
                }
            } else {
                let regenerateButton;
                if(data.table === 'tt_content') {
                    document.querySelector('form[name="content"] #fields-' + data.table + ' #generated-images-' + data.fieldName).innerHTML = res.output;
                    regenerateButton = document.querySelector('form[name="content"] #fields-' + data.table + ' #generated-images-' + data.fieldName).querySelector('.create-content-update-image');
                } else {
                    document.querySelector('form[name="content"] #fields-' + data.table +'-' + data.position + ' #generated-images-' + data.fieldName).innerHTML = res.output;
                    regenerateButton = document.querySelector('form[name="content"] #fields-' + data.table +'-' + data.position + ' #generated-images-' + data.fieldName).querySelector('.create-content-update-image');
                }
                if (regenerateButton !== null) {
                    regenerateButton.addEventListener('click', function(ev) {
                        ev.preventDefault();
                        require(["TYPO3/CMS/AiSuite/Helper/Image/GenerationHandling"], function(GenerationHandling) {
                            GenerationHandling.showGeneralImageSettingsModal(data, 'ContentElement');
                        });
                    });
                }
            }
        } else {
            Notification.error(TYPO3.lang['AiSuite.notification.generation.error'], errorMessage);
            if(data.table === 'tt_content') {
                document.querySelector('form[name="content"] #fields-' + data.table + ' #generated-images-' + data.fieldName).innerHTML = preselectionContent;
            } else {
                document.querySelector('form[name="content"] #fields-' + data.table +'-' + data.position + ' #generated-images-' + data.fieldName).innerHTML = preselectionContent;
            }
        }
    }

    function handleStatusResponse(res, modal) {
        let statusElement = modal.find('.modal-body').find('.spinner-wrapper .status');
        if(
            General.isUsable(statusElement) &&
            General.isUsable(res) &&
            General.isUsable(res.error) === false
        ) {
            statusElement.html(res.output);
        }
    }
    function handleStatusContentElementResponse(res) {
        let statusElement = document.querySelector('.spinner-overlay .status');
        if(
            General.isUsable(statusElement) &&
            General.isUsable(res) &&
            General.isUsable(res.error) === false
        ) {
            statusElement.innerHTML = res.output;
        }
    }
    return {
        handleResponse: handleResponse,
        handleResponseContentElement: handleResponseContentElement,
        handleStatusResponse: handleStatusResponse,
        handleStatusContentElementResponse: handleStatusContentElementResponse
    };
});
