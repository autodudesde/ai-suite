import AjaxRequest from "@typo3/core/ajax/ajax-request.js";
import Notification from "@typo3/backend/notification.js";

class Ajax {
    sendStatusAjaxRequest(postData) {
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
            .catch(() => {
                // Status polling is a progress mechanism running alongside the main generation
                // request, which surfaces the real error itself — so a failed poll is non-fatal
                // and stays silent to avoid a redundant/spurious toast.
                return null;
            });
    }
    sendAjaxRequest(endpoint, postData, returnJson = false) {
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
                        Notification.error(TYPO3.lang['aiSuite.notification.generation.requestError'], responseBody.error);
                        return null;
                    } else {
                        return responseBody;
                    }
                }
            })
            .catch((error) => {
                Notification.error(TYPO3.lang['aiSuite.notification.generation.error'], error.statusText);
                return null;
            });
    }
    fetchLibraries(endpoint, data = {}) {
        return new AjaxRequest(TYPO3.settings.ajaxUrls[endpoint])
            .post(data)
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
                Notification.error(
                    TYPO3.lang['aiSuite.notification.generation.error'],
                    error?.statusText || error?.message || ''
                );
                return null;
            });
    }
    sendRteAjaxRequest(postData) {
        return new AjaxRequest(TYPO3.settings.ajaxUrls['aisuite_ckeditor_request'])
            .post(
                postData
            )
            .then(async function (response) {
                const resolved = await response.resolve();
                const responseBody = JSON.parse(resolved);
                if (responseBody.error) {
                    Notification.error(TYPO3.lang['aiSuite.notification.generation.requestError'], responseBody.error);
                    return null;
                } else {
                    return responseBody;
                }
            })
            .catch((error) => {
                Notification.error(
                    TYPO3.lang['aiSuite.notification.generation.error'],
                    error?.statusText || error?.message || ''
                );
                return null;
            });
    }
}

export default new Ajax();
