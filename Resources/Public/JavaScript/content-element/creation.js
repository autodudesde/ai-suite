import HelperFunctions from "../helper/functions.js";
import Modal from "@typo3/backend/modal.js";
import Severity from "@typo3/backend/severity.js";

class Creation {

    constructor() {
        this.hideShowImageLibraries();
        this.hideShowTextLibraries();
        this.addFormSubmitEventListener();
        this.setPromptTemplate();
        HelperFunctions.cancelGeneration();
    }
    setPromptTemplate() {
        let promptTemplates = document.querySelector('div[data-module-id="aiSuite"] select[name="promptTemplates"]');
        if(promptTemplates !== null) {
            promptTemplates.addEventListener('change', function (event) {
                document.querySelector('div[data-module-id="aiSuite"] textarea[name="content[initialPrompt]"]').value = event.target.value;
            });
        }
    }
    hideShowImageLibraries() {
        let handleCheckboxChangeFn = this.handleCheckboxChange;
        // Get all checkboxes with the class "request-field-checkbox" and value "file"
        document.querySelectorAll('.request-field-checkbox[value="file"]').forEach(function (checkbox) {
            checkbox.addEventListener('change', () => {
                handleCheckboxChangeFn('.request-field-checkbox[value="file"]', '.image-generation-library');
            });
        });
    }
    hideShowTextLibraries() {
        let handleCheckboxChangeFn = this.handleCheckboxChange;
        // Get all checkboxes with the class "request-field-checkbox" and value "input" or "text"
        document.querySelectorAll('.request-field-checkbox[value="input"], .request-field-checkbox[value="text"]').forEach(function (checkbox) {
            checkbox.addEventListener('change', () => {
                handleCheckboxChangeFn('.request-field-checkbox[value="input"], .request-field-checkbox[value="text"]', '.text-generation-library');
            });
        });
    }

    /**
     *
     * @param selectors
     * @param librarySelector
     */
    handleCheckboxChange(selectors, librarySelector) {
        const fileCheckboxes = document.querySelectorAll(selectors);

        // Check if at least one "file" checkbox is checked
        const atLeastOneChecked = Array.from(fileCheckboxes).some(function (checkbox) {
            return checkbox.checked;
        });

        // Hide or show the field based on the condition
        if (atLeastOneChecked) {
            document.querySelector(librarySelector).style.display = 'block';
        } else {
            document.querySelector(librarySelector).style.display = 'none';
        }
    }
    addFormSubmitEventListener() {
        let showSpinnerFn = HelperFunctions.showSpinner;
        let formsWithSpinner = Array.from(document.querySelectorAll('div[data-module-id="aiSuite"] form.with-spinner'));
        let spinnerOverlay = document.querySelector('div[data-module-id="aiSuite"] .spinner-overlay');

        if (Array.isArray(formsWithSpinner) && HelperFunctions.isUsable(spinnerOverlay)) {
            formsWithSpinner.forEach(function (form, index, arr) {
                form.addEventListener('submit', function (event) {
                    event.preventDefault();
                    const fileCheckboxes = document.querySelectorAll('.request-field-checkbox[value="input"], .request-field-checkbox[value="text"], .request-field-checkbox[value="file"]');

                    // Check if at least one checkbox is checked
                    const atLeastOneChecked = Array.from(fileCheckboxes).some(function (checkbox) {
                        return checkbox.checked;
                    });
                    if(atLeastOneChecked) {
                        showSpinnerFn();
                        form.submit();
                    } else {
                        Modal.confirm(TYPO3.lang['aiSuite.module.notification.warning'], TYPO3.lang['aiSuite.module.notification.modal.noFieldsSelected'], Severity.warning, [
                            {
                                text: TYPO3.lang['aiSuite.module.notification.modal.close'],
                                trigger: function() {
                                    Modal.dismiss();
                                }
                            }
                        ]);
                    }
                });
            });
        }
    }
}
export default new Creation();


