import AjaxRequest from "@typo3/core/ajax/ajax-request.js";
import Notification from "@typo3/backend/notification.js";

class Functions {

    addFormSubmitEventListener() {
        let showSpinnerFn = this.showSpinner;
        let formsWithSpinner = Array.from(document.querySelectorAll('div[data-module-id="aiSuite"] form.with-spinner'));
        let spinnerOverlay = document.querySelector('div[data-module-id="aiSuite"] .spinner-overlay');

        if (Array.isArray(formsWithSpinner) && this.isUsable(spinnerOverlay)) {
            formsWithSpinner.forEach(function (form, index, arr) {
                form.addEventListener('submit', function (event) {
                    showSpinnerFn();
                });
            });
        }
    }

    showSpinner() {
        document.querySelector('div[data-module-id="aiSuite"] .spinner-overlay').classList.add('active');
        setTimeout(() => {
            document.querySelector('div[data-module-id="aiSuite"] .spinner-overlay').classList.add('darken');
        }, 100);
    }

    /**
     *
     * @param {string} endpoint
     * @param {object} postData
     * @param {boolean} returnJson
     */
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
     * @param {object[]} list
     * @param findItemsInSortableFn
     * @returns {object[]}
     */
    findItemsInSortable(list, findItemsInSortableFn = this.findItemsInSortable) {
        let fiisFn = findItemsInSortableFn;
        let items = [];
        list.forEach(function (item, index, arr) {
            let title = item.querySelector('span.title').dataset.title;
            let children = Array.from(item.querySelectorAll(':scope > .list-group.nested-sortable > .list-group-item'));
            let newElement = {};
            if(children.length > 0) {
                newElement = {
                    title: title,
                    children: findItemsInSortableFn(children, fiisFn)
                };
            } else {
                newElement = {
                    title: title
                };
            }
            if(title !== undefined && title !== null && title !== '') {
                items.push(newElement);
            }
        });
        return items;
    }

    isUsable(element) {
        return element !== null && element !== undefined;
    }
}

export default new Functions();
