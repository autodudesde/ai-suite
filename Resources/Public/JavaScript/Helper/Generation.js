define([
    "TYPO3/CMS/Backend/Notification",
    "TYPO3/CMS/AiSuite/Helper/General"
], function(Notification, General) {
    function addFormSubmitEventListener(promptInputName) {
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
                        showFormSpinner();
                        form.submit();
                    }
                });
            });
        }
    }

    function showFormSpinner() {
        document.querySelector('div[data-module-id="aiSuite"] .spinner-overlay').classList.add('active');
        setTimeout(() => {
            document.querySelector('div[data-module-id="aiSuite"] .spinner-overlay').classList.add('darken');
        }, 100);
    }

    function cancelGeneration() {
        let cancelButton = document.querySelector('div[data-module-id="aiSuite"] .spinner-overlay .cancel .btn');
        if(General.isUsable(cancelButton)) {
            cancelButton.addEventListener('click', function () {
                history.go(0);
            });
        }
    }
    return {
        addFormSubmitEventListener: addFormSubmitEventListener,
        showFormSpinner: showFormSpinner,
        cancelGeneration: cancelGeneration
    };
});
