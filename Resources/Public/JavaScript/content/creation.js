import Notification from "@typo3/backend/notification.js";
import General from "@autodudes/ai-suite/helper/general.js";
import Generation from "@autodudes/ai-suite/helper/generation.js";
import PromptTemplate from "@autodudes/ai-suite/helper/prompt-template.js";
import StatusHandling from "@autodudes/ai-suite/helper/image/status-handling.js";

class Creation {

    constructor() {
        this.hideShowImageLibraries();
        this.hideShowTextLibraries();
        this.addAdditionalImageGenerationSettingsHandling();
        this.addFormSubmitEventListener();
        PromptTemplate.loadPromptTemplates('initialPrompt');
        this.calculateRequestAmount();
        this.handleModelChange();
        Generation.cancelGeneration();
        this.handleCheckboxChange('.request-field-checkbox[value="file"]', '.image-generation-library', this.calculateRequestAmount);
        this.handleCheckboxChange('.request-field-checkbox[value="input"], .request-field-checkbox[value="text"]', '.text-generation-library', this.calculateRequestAmount);
        this.intervalId = null;
    }

    hideShowImageLibraries() {
        let handleCheckboxChangeFn = this.handleCheckboxChange;
        let calculateRequestAmountFn = this.calculateRequestAmount;
        document.querySelectorAll('.request-field-checkbox[value="file"]').forEach(function (checkbox) {
            checkbox.addEventListener('change', () => {
                handleCheckboxChangeFn('.request-field-checkbox[value="file"]', '.image-generation-library', calculateRequestAmountFn);
            });
        });
        let imageAiModel = document.querySelector('.image-generation-library input[name="libraries[imageGenerationLibrary]"]:checked');
        if(imageAiModel && imageAiModel.value === 'Midjourney') {
            document.querySelector('.image-settings-midjourney').style.display = 'block';
        }
    }
    hideShowTextLibraries() {
        let handleCheckboxChangeFn = this.handleCheckboxChange;
        let calculateRequestAmountFn = this.calculateRequestAmount;
        document.querySelectorAll('.request-field-checkbox[value="input"], .request-field-checkbox[value="text"]').forEach(function (checkbox) {
            checkbox.addEventListener('change', () => {
                handleCheckboxChangeFn('.request-field-checkbox[value="input"], .request-field-checkbox[value="text"]', '.text-generation-library', calculateRequestAmountFn);
            });
        });
    }

    handleModelChange() {
        const self = this;
        document.querySelectorAll('.library input[type="radio"]').forEach(function (radio) {
            radio.addEventListener('change', function() {
                self.calculateRequestAmount();
            });
        });
    }

    /**
     *
     * @param selectors
     * @param librarySelector
     * @param calculateRequestAmountFn
     */
    handleCheckboxChange(selectors, librarySelector, calculateRequestAmountFn) {
        const fileCheckboxes = document.querySelectorAll(selectors);

        const atLeastOneChecked = Array.from(fileCheckboxes).some(function (checkbox) {
            return checkbox.checked;
        });

        if (atLeastOneChecked) {
            document.querySelector(librarySelector).style.display = 'block';
        } else {
            document.querySelector(librarySelector).style.display = 'none';
        }
        calculateRequestAmountFn();
    }
    addFormSubmitEventListener() {
        let self = this;
        let formsWithSpinner = Array.from(document.querySelectorAll('div[data-module-id="aiSuite"] form.with-spinner'));
        let spinnerOverlay = document.querySelector('div[data-module-id="aiSuite"] .spinner-overlay');

        if (Array.isArray(formsWithSpinner) && General.isUsable(spinnerOverlay)) {
            formsWithSpinner.forEach(function (form, index, arr) {
                form.addEventListener('submit', function (event) {
                    event.preventDefault();
                    document.querySelector('input[name="additionalImageSettings"]').value = self.getAdditionalImageSettings();
                    const fileCheckboxes = document.querySelectorAll('.request-field-checkbox[value="input"], .request-field-checkbox[value="text"], .request-field-checkbox[value="file"]');

                    const atLeastOneChecked = Array.from(fileCheckboxes).some(function (checkbox) {
                        return checkbox.checked;
                    });
                    let enteredPrompt = document.querySelector('div[data-module-id="aiSuite"] textarea[name="initialPrompt"]').value
                    if (enteredPrompt.length < 5) {
                        Notification.warning(TYPO3.lang['aiSuite.module.modal.enteredPromptTitle'], TYPO3.lang['aiSuite.module.modal.enteredPromptMessage'], 8);
                    }
                    if(atLeastOneChecked === false) {
                        Notification.warning(TYPO3.lang['aiSuite.module.notification.modal.noFieldsSelectedTitle'], TYPO3.lang['aiSuite.module.notification.modal.noFieldsSelectedMessage'], 8);
                    }
                    if(atLeastOneChecked && enteredPrompt.length > 4) {
                        Generation.showSpinner();
                        const submitBtn = form.querySelector('button[type="submit"]');
                        let data = {
                            uuid: submitBtn.getAttribute('data-uuid'),
                            pageId: submitBtn.getAttribute('data-page-id'),
                        };
                        StatusHandling.fetchStatusContentElement(data, self);
                        form.submit();
                    }
                });
            });
        }
    }
    calculateRequestAmount() {
        let calculatedRequests = 0;
        document.querySelectorAll('.library').forEach(function (library) {
            let amountField = library.querySelector('.request-amount span');
            if(library.style.display !== 'none' && amountField !== null) {
                let modelId = library.querySelector('input[type="radio"]:checked').id;
                let amount = parseInt(library.querySelector('label[for="' + modelId +'"] .request-amount span').textContent);
                calculatedRequests += amount;
            }
        });
        let marker = TYPO3.lang['aiSuite.module.multipleCredits'];
        if(calculatedRequests === 1) {
            marker = TYPO3.lang['aiSuite.module.oneCredit'];
        }
        document.querySelector('div[data-module-id="aiSuite"] .calculated-requests').textContent = '(' + calculatedRequests + ' ' + marker + ')';
    }

    addAdditionalImageGenerationSettingsHandling() {
        let imageGenerationLibraries = document.querySelectorAll('.image-generation-library input[name="libraries[imageGenerationLibrary]"]');
        let imageSettingsMidjourney = document.querySelector('.image-settings-midjourney');

        imageGenerationLibraries.forEach(function(element) {
            element.addEventListener('click', function(event) {
                if(event.target.value === 'Midjourney') {
                    imageSettingsMidjourney.style.display = 'block';
                } else {
                    imageSettingsMidjourney.style.display = 'none';
                }
            });
        });
    }
    getAdditionalImageSettings() {
        let imageAiModel = document.querySelector('.image-generation-library input[name="libraries[imageGenerationLibrary]"]:checked').value;
        let additionalSettings = '';
        if(imageAiModel === 'Midjourney') {
            let imageSettingsMidjourneySelect = document.querySelectorAll('.image-settings-midjourney select');
            let imageSettingsMidjourneyInputText = document.querySelectorAll('.image-settings-midjourney input[type="text"]');
            imageSettingsMidjourneySelect.forEach(function(settingsElement) {
                let prefix = settingsElement.getAttribute('data-prefix');
                let value = settingsElement.value;
                additionalSettings += ' ' + prefix + ' ' + value;
            });
            imageSettingsMidjourneyInputText.forEach(function(settingsElement) {
                let prefix = settingsElement.getAttribute('data-prefix');
                let value = settingsElement.value;
                if(prefix === '--no' && value.trim() !== '') {
                    value = value.replace(', ', ',');
                    value = value.replace('  ', ' ');
                    value = value.replace(' ', ',');
                }
                value = value.trim();
                if(value !== '') {
                    additionalSettings += ' ' + prefix + ' ' + value;
                }
            });
        }
        return additionalSettings;
    }
}
export default new Creation();


