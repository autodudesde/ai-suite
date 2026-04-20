import Generation from "@autodudes/ai-suite/helper/generation.js";
import General from "@autodudes/ai-suite/helper/general.js";

class Creation {
    constructor() {
        this.addFormSubmitEventListener();
    }

    addFormSubmitEventListener() {
        let formsWithSpinner = Array.from(document.querySelectorAll('div[data-module-id="aiSuite"] form.with-spinner'));
        let spinnerOverlay = document.querySelector('div[data-module-id="aiSuite"] .spinner-overlay');

        if (Array.isArray(formsWithSpinner) && General.isUsable(spinnerOverlay)) {
            formsWithSpinner.forEach(function (form, index, arr) {
                form.addEventListener('submit', function (event) {
                    event.preventDefault();
                    Generation.showSpinner();
                    form.submit();
                });
            });
        }
    }
}
export default new Creation();


