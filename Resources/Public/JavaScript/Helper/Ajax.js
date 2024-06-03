define([
    "TYPO3/CMS/Backend/Notification",
    "TYPO3/CMS/Core/Ajax/AjaxRequest",
], function(
    Notification,
    AjaxRequest
) {
    /**
     * @param {object} postData
     */
    function sendStatusAjaxRequest(postData) {
        return new AjaxRequest(TYPO3.settings.ajaxUrls['aisuite_generation_status'])
            .post(
                postData
            )
            .then(async function (response) {
                const resolved = await response.resolve();
                const responseBody = JSON.parse(resolved);
                if(responseBody.error) {
                    return null;
                } else {
                    return responseBody;
                }
            })
            .catch((error) => {
                return null;
            });
    }
    /**
     *
     * @param {string} endpoint
     * @param {object} postData
     * @param {boolean} returnJson
     */
    function sendAjaxRequest(endpoint, postData, returnJson = false) {
        return new AjaxRequest(TYPO3.settings.ajaxUrls[endpoint])
            .post(
                postData
            )
            .then(async function (response) {
                const resolved = await response.resolve();
                if(returnJson) {
                    return resolved;
                } else {
                    const responseBody = JSON.parse(resolved);
                    if(responseBody.error) {
                        Notification.error(TYPO3.lang['AiSuite.notification.generation.requestError'], responseBody.error);
                        return null;
                    } else {
                        return responseBody;
                    }
                }
            })
            .catch((error) => {
                Notification.error(TYPO3.lang['AiSuite.notification.generation.error'], error.statusText);
                return null;
            });
    }

    /**
     *
     * @param {int} modelId
     * @param {string} fieldName
     * @param {object} postData
     * @param {function} handleResponse
     * @param {string} ajaxUrlPrefix
     * @param {function} addSelectionToAdditionalFields
     */
    function sendMetadataAjaxRequest(modelId, fieldName, postData, handleResponse, ajaxUrlPrefix = '', addSelectionToAdditionalFields = null) {
        Notification.info(TYPO3.lang['AiSuite.notification.generation.start'], TYPO3.lang['AiSuite.notification.generation.start.suggestions'], 16);
        new AjaxRequest(TYPO3.settings.ajaxUrls[ajaxUrlPrefix + fieldName+'_generation'])
            .post(
                postData
            )
            .then(async function (response) {
                const resolved = await response.resolve();
                const responseBody = JSON.parse(resolved);
                if(responseBody.error) {
                    Notification.error(TYPO3.lang['AiSuite.notification.generation.requestError'], responseBody.error);
                } else {
                    if(addSelectionToAdditionalFields === null) {
                        handleResponse(modelId, fieldName, responseBody)
                    } else {
                        handleResponse(modelId, fieldName, responseBody)
                    }
                    Notification.success(TYPO3.lang['AiSuite.notification.generation.finish'], TYPO3.lang['AiSuite.notification.generation.finish.suggestions'], 8);
                }
            })
            .catch((error) => {
                Notification.error(TYPO3.lang['AiSuite.notification.generation.error'], error);
            });
    }
    return {
        sendStatusAjaxRequest: sendStatusAjaxRequest,
        sendAjaxRequest: sendAjaxRequest,
        sendMetadataAjaxRequest: sendMetadataAjaxRequest
    };
});
