import General from "@autodudes/ai-suite/helper/general.js";

class Generation {

    addFormSubmitEventListener() {
        let showSpinnerFn = this.showSpinner;
        let formsWithSpinner = Array.from(document.querySelectorAll('div[data-module-id="aiSuite"] form.with-spinner'));
        let spinnerOverlay = document.querySelector('div[data-module-id="aiSuite"] .spinner-overlay');

        if (Array.isArray(formsWithSpinner) && General.isUsable(spinnerOverlay)) {
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

    cancelGeneration() {
        let cancelButton = document.querySelector('div[data-module-id="aiSuite"] .spinner-overlay .cancel .btn');
        if(General.isUsable(cancelButton)) {
            cancelButton.addEventListener('click', function () {
                history.go(0);
            });
        }
    }
}

export default new Generation();
