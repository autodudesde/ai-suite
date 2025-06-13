define([
    "TYPO3/CMS/AiSuite/Helper/Generation",
    "TYPO3/CMS/AiSuite/Helper/General",
], function(Generation, General) {
    function AgenciesCreation() {
        this.addFormSubmitEventListener();
    }

    AgenciesCreation.prototype.addFormSubmitEventListener = function() {
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
    return new AgenciesCreation();
});

