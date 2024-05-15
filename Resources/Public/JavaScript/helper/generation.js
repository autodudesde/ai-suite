import Notification from "@typo3/backend/notification.js";
import General from "@autodudes/ai-suite/helper/general.js";

class Generation {

    addFormSubmitEventListener(promptInputName) {
        let self = this;
        let formsWithSpinner = Array.from(document.querySelectorAll('div[data-module-id="aiSuite"] form.with-spinner'));
        let spinnerOverlay = document.querySelector('div[data-module-id="aiSuite"] .spinner-overlay');

        if (Array.isArray(formsWithSpinner) && General.isUsable(spinnerOverlay)) {
            formsWithSpinner.forEach(function (form, index, arr) {
                form.addEventListener('submit', function (event) {
                    event.preventDefault();
                    let enteredPrompt = document.querySelector('div[data-module-id="aiSuite"] textarea[name="'+promptInputName+'"]').value
                    if (enteredPrompt.length < 5) {
                        Notification.warning(TYPO3.lang['aiSuite.module.modal.enteredPromptTitle'], TYPO3.lang['aiSuite.module.modal.enteredPromptMessage'], 8);
                    }
                    if(enteredPrompt.length > 4) {
                        self.showSpinner();
                        form.submit();
                    }
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
