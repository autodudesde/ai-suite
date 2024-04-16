import General from "@autodudes/ai-suite/helper/general.js";
import Generation from "@autodudes/ai-suite/helper/generation.js";
import Sortable from "@autodudes/ai-suite/helper/sortable.js";

class Validation {
    constructor() {
        this.addEventListener();
    }
    addEventListener() {
        this.addEventListenerGeneratePageStructure();
        Generation.addFormSubmitEventListener();
    }

    addEventListenerGeneratePageStructure() {
        // generate array out of sortable items and submit form
        let pageStructureSubmitButton = document.querySelector('div[data-module-id="aiSuite"] form.page-structure-create span.submit-page-structure');
        if (General.isUsable(pageStructureSubmitButton)) {
            pageStructureSubmitButton.addEventListener('click', function (event) {
                event.preventDefault();
                let sortableItems = Array.from(document.querySelectorAll('div[data-module-id="aiSuite"] .sortable-wrap > .nested-sortable > .list-group-item'));
                let result = Sortable.findItemsInSortable(sortableItems);
                document.querySelector('input[name="selectedPageTreeContent"]').value = JSON.stringify(result);
                Generation.showSpinner();
                document.querySelector('div[data-module-id="aiSuite"] form.page-structure-create').submit();
            });
        }
    }
}

export default new Validation();


