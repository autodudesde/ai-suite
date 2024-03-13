import HelperFunctions from "../helper/functions.js";

class Validation {
    constructor() {
        this.addEventListener();
    }
    addEventListener() {
        this.addEventListenerGeneratePageStructure();
        HelperFunctions.addFormSubmitEventListener();
    }

    addEventListenerGeneratePageStructure() {
        // generate array out of sortable items and submit form
        let pageStructureSubmitButton = document.querySelector('div[data-module-id="aiSuite"] form.page-structure-create span.submit-page-structure');
        if (HelperFunctions.isUsable(pageStructureSubmitButton)) {
            pageStructureSubmitButton.addEventListener('click', function (event) {
                event.preventDefault();
                let sortableItems = Array.from(document.querySelectorAll('div[data-module-id="aiSuite"] .sortable-wrap > .nested-sortable > .list-group-item'));
                let result = HelperFunctions.findItemsInSortable(sortableItems);
                document.querySelector('input[name="selectedPageTreeContent"]').value = JSON.stringify(result);
                HelperFunctions.showSpinner();
                document.querySelector('div[data-module-id="aiSuite"] form.page-structure-create').submit();
            });
        }
    }
}

export default new Validation();


