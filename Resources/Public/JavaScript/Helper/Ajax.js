define([
    "TYPO3/CMS/Backend/Notification",
    "TYPO3/CMS/Core/Ajax/AjaxRequest",
], function(
    Notification,
    AjaxRequest
) {
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
    function fetchLibraries(endpoint) {
        return new AjaxRequest(TYPO3.settings.ajaxUrls[endpoint])
            .post({})
            .then(async function (response) {

                const resolved = await response.resolve();
                const responseBody = JSON.parse(resolved);
                if (responseBody.error) {
                    return null;
                } else {
                    return responseBody;
                }
            })
            .catch((error) => {
                console.error(error);
                return null;
            });
    }
    return {
        sendStatusAjaxRequest: sendStatusAjaxRequest,
        sendAjaxRequest: sendAjaxRequest,
        fetchLibraries: fetchLibraries
    };
});
