define([
    'TYPO3/CMS/Backend/Notification',
    "TYPO3/CMS/AiSuite/Helper/General",
    "TYPO3/CMS/AiSuite/Helper/Generation",
    "TYPO3/CMS/AiSuite/Helper/PromptTemplate",
    "TYPO3/CMS/AiSuite/Helper/Image/StatusHandling",
    "TYPO3/CMS/AiSuite/Helper/Image/GenerationHandling"
], function(Notification, General, Generation, PromptTemplate, StatusHandling, GenerationHandling) {
    'use strict';

    /**
     * Creation Constructor
     *
     * @constructor
     */
    function ContentCreation() {
        this.hideShowImageLibraries();
        this.hideShowTextLibraries();
        GenerationHandling.addAdditionalImageGenerationSettingsHandling();
        this.addFormSubmitEventListener();
        PromptTemplate.loadPromptTemplates('initialPrompt');
        this.calculateRequestAmount();
        this.handleModelChange();
        Generation.cancelGeneration();
        this.handleCheckboxChange('.request-field-checkbox[value="inline"]', '.image-generation-library');
        this.handleCheckboxChange('.request-field-checkbox[value="input"], .request-field-checkbox[value="text"]', '.text-generation-library');
    }

    /**
     * Show/hide image libraries based on checkbox state
     */
    ContentCreation.prototype.hideShowImageLibraries = function() {
        const self = this;
        document.querySelectorAll('.request-field-checkbox[value="inline"]').forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                self.handleCheckboxChange('.request-field-checkbox[value="inline"]', '.image-generation-library');
            });
        });
        let imageAiModel = document.querySelector('.image-generation-library input[name="libraries[imageGenerationLibrary]"]:checked');
        if(imageAiModel && imageAiModel.value === 'Midjourney') {
            document.querySelector('.image-settings-midjourney').style.display = 'block';
        }
    };

    /**
     * Show/hide text libraries based on checkbox state
     */
    ContentCreation.prototype.hideShowTextLibraries = function() {
        const self = this;
        document.querySelectorAll('.request-field-checkbox[value="input"], .request-field-checkbox[value="text"]').forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                self.handleCheckboxChange('.request-field-checkbox[value="input"], .request-field-checkbox[value="text"]', '.text-generation-library');
            });
        });
    };

    /**
     * Handle model change events to recalculate request amount
     */
    ContentCreation.prototype.handleModelChange = function() {
        const self = this;
        document.querySelectorAll('.library input[type="radio"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                self.calculateRequestAmount();
            });
        });
    };

    /**
     * Handle checkbox change events and update UI accordingly
     *
     * @param {string} selectors - CSS selectors for checkboxes
     * @param {string} librarySelector - CSS selector for library container
     */
    ContentCreation.prototype.handleCheckboxChange = function(selectors, librarySelector) {
        let checkboxes = document.querySelectorAll(selectors);

        let atLeastOneChecked = Array.from(checkboxes).some(function(checkbox) {
            return checkbox.checked;
        });

        if (atLeastOneChecked) {
            document.querySelector(librarySelector).style.display = 'block';
        } else {
            document.querySelector(librarySelector).style.display = 'none';
        }
        this.calculateRequestAmount();
    };

    /**
     * Add event listener for form submission
     */
    ContentCreation.prototype.addFormSubmitEventListener = function() {
        const self = this;
        let formsWithSpinner = Array.from(document.querySelectorAll('div[data-module-id="aiSuite"] form.with-spinner'));
        let spinnerOverlay = document.querySelector('div[data-module-id="aiSuite"] .spinner-overlay');

        if (Array.isArray(formsWithSpinner) && General.isUsable(spinnerOverlay)) {
            formsWithSpinner.forEach(function(form, index, arr) {
                form.addEventListener('submit', function(event) {
                    event.preventDefault();
                    let imageAiModel = document.querySelector('.image-generation-library input[name="libraries[imageGenerationLibrary]"]:checked').value;
                    try {
                        document.querySelector('input[name="additionalImageSettings"]').value = GenerationHandling.getAdditionalImageSettings(imageAiModel);
                        let checkboxes = document.querySelectorAll('.request-field-checkbox[value="input"], .request-field-checkbox[value="text"], .request-field-checkbox[value="inline"]');

                        let atLeastOneChecked = Array.from(checkboxes).some(function(checkbox) {
                            return checkbox.checked;
                        });
                        let enteredPrompt = document.querySelector('div[data-module-id="aiSuite"] textarea[name="initialPrompt"]').value;
                        if (enteredPrompt.length < 5) {
                            Notification.warning(TYPO3.lang['aiSuite.module.modal.enteredPromptTitle'], TYPO3.lang['aiSuite.module.modal.enteredPromptMessage'], 8);
                        }
                        if(atLeastOneChecked === false) {
                            Notification.warning(TYPO3.lang['aiSuite.module.notification.modal.noFieldsSelectedTitle'], TYPO3.lang['aiSuite.module.notification.modal.noFieldsSelectedMessage'], 8);
                        }
                        if(atLeastOneChecked && enteredPrompt.length > 4) {
                            Generation.showSpinner();
                            let submitBtn = form.querySelector('button[type="submit"]');
                            let data = {
                                uuid: submitBtn.getAttribute('data-uuid'),
                                pageId: submitBtn.getAttribute('data-page-id'),
                            };
                            StatusHandling.fetchStatusContentElement(data, self);
                            form.submit();
                        }
                    } catch (error) {
                        if (error.message === 'Invalid URL for --sref parameter') {
                            Notification.warning(
                                TYPO3.lang['AiSuite.notification.invalidUrlTitle'],
                                TYPO3.lang['AiSuite.notification.invalidUrlMessage'],
                                8
                            );
                        }
                    }
                });
            });
        }
    };

    /**
     * Calculate the number of API requests needed
     */
    ContentCreation.prototype.calculateRequestAmount = function() {
        let calculatedRequests = 0;
        document.querySelectorAll('.library').forEach(function(library) {
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
    };

    // Return a new instance
    return new ContentCreation();
});
