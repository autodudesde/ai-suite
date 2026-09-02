import AjaxRequest from "@typo3/core/ajax/ajax-request.js";
import Notification from "@typo3/backend/notification.js";

async function errorDetail(error) {
    if (error && typeof error.resolve === "function") {
        try {
            const body = await error.resolve();
            const parsed = typeof body === "string" ? JSON.parse(body) : body;
            if (parsed && parsed.error) {
                return parsed.error;
            }
        } catch (ignored) {
            // Not a JSON body, fall through to whatever the error object itself offers.
        }
    }

    return error?.statusText || error?.message || "";
}

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
            .catch(async (error) => {
                Notification.error(TYPO3.lang['aiSuite.notification.generation.error'], await errorDetail(error));
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
            .catch(async (error) => {
                Notification.error(
                    TYPO3.lang['aiSuite.notification.generation.error'],
                    await errorDetail(error)
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
            .catch(async (error) => {
                Notification.error(
                    TYPO3.lang['aiSuite.notification.generation.error'],
                    await errorDetail(error)
                );
                return null;
            });
    }
}

export default new Ajax();
