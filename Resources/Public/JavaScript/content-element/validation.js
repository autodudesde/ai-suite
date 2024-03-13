import Notification from "@typo3/backend/notification.js";
import Modal from "@typo3/backend/modal.js";
import Severity from "@typo3/backend/severity.js";
import Icons from "@typo3/backend/icons.js";
import MultiStepWizard from "@typo3/backend/multi-step-wizard.js";

import HelperFunctions from "../helper/functions.js";

class Validation {
    constructor() {
        let addImageGenerationWizard = this.addImageGenerationWizard;
        this.addEventListenerImageRegeneration(addImageGenerationWizard);
        this.addEventListenerFormSubmit();
    }
    addEventListenerImageRegeneration(addImageGenerationWizard) {
        // add regenerate suggestions for single image fields
        document.querySelectorAll('.create-content-update-image').forEach(function(regenerateButton) {
            regenerateButton.addEventListener('click', function(ev) {
                ev.preventDefault();
                let initialPrompt = this.getAttribute('data-initial-prompt');
                let initialImageAi = this.getAttribute('data-initial-image-ai');
                let pageId = this.getAttribute('data-page-id');
                let table = this.getAttribute('data-table');
                let position = this.getAttribute('data-position');
                let fieldname = this.getAttribute('data-fieldname');
                addImageGenerationWizard(initialPrompt, initialImageAi, pageId, table, position, fieldname, addImageGenerationWizard);
            });
        });
        document.querySelectorAll('div[data-module-id="aiSuite"] .t3js-module-body').forEach(function(element) {
            element.addEventListener("click", function(ev) {
                if(ev.target && ev.target.nodeName === "IMG" && ev.target.classList.contains('ce-image-selection')) {
                    let selectionGroup = ev.target.getAttribute('data-selection-group');
                    let selectionId = ev.target.getAttribute('data-selection-id');
                    if(document.getElementById(selectionId) === selectionId && document.getElementById(selectionId).checked) {
                        document.getElementById(selectionId).checked = false;
                    } else {
                        document.querySelectorAll('input[data-selection-group="'+selectionGroup+'"]').forEach(function(input) {
                            if(input.id !== selectionId) {
                                input.checked = false;
                            }
                        });
                    }
                }
            });
        });
    }

    addEventListenerFormSubmit() {
        document.querySelectorAll('form[name="content"]').forEach(function(form) {
            form.addEventListener('submit', function(ev) {
                ev.preventDefault();
                // check image fields and if at least one is selected of each checkbox
                let imageFieldsWithoutSelection = '';
                const checkboxInputs = document.querySelectorAll('input[type="checkbox"].image-selection');
                const groupsCheckedStatus = new Map();

                checkboxInputs.forEach(input => {
                    if (input.type === 'checkbox') {
                        let position = parseInt(input.getAttribute('data-position'));
                        let group = input.getAttribute('data-fieldname');
                        position++;
                        if(input.hasAttribute('data-itemlabel') && input.getAttribute('data-itemlabel') !== '') {
                            group = input.getAttribute('data-itemlabel') + ' ' + position + ': ' + input.getAttribute('data-fieldname');
                        }
                        if (!groupsCheckedStatus.has(group)) {
                            groupsCheckedStatus.set(group, false);
                        }
                        if (input.checked) {
                            groupsCheckedStatus.set(group, true);
                        }
                    }
                });
                groupsCheckedStatus.forEach(function(checked, key) {
                    if(!checked) {
                        imageFieldsWithoutSelection += key + ', ';
                    }
                })
                if(imageFieldsWithoutSelection !== '') {
                    Modal.confirm(
                        'Warning',
                        TYPO3.lang['aiSuite.module.modal.noSelectionForFields'] + imageFieldsWithoutSelection.slice(0, -2)  + '. ' + TYPO3.lang['aiSuite.module.modal.continue'],
                        Severity.warning, [
                        {
                            text: TYPO3.lang['aiSuite.module.modal.saveAnyway'],
                            active: true,
                            trigger: function() {
                                HelperFunctions.showSpinner();
                                form.submit();
                                Modal.dismiss();
                            }
                        }, {
                            text: TYPO3.lang['aiSuite.module.modal.abort'],
                            trigger: function() {
                                Modal.dismiss();
                            }
                        }
                    ]);
                } else {
                    HelperFunctions.showSpinner();
                    form.submit();
                }
            });
        });
    }

    addImageGenerationWizard(initialPrompt, initialImageAi, pageId, table, position, fieldname, addImageGenerationWizard) {
        /**
         * Step 1
         */
        MultiStepWizard.addSlide('ai-suite-generate-add-image-step-1', TYPO3.lang['aiSuite.module.modal.imageGenerationWizard'], '', Severity.info, 'Image prompt', function(slide, settings) {
            MultiStepWizard.blurCancelStep();
            MultiStepWizard.lockNextStep();
            MultiStepWizard.lockPrevStep();
            let modal = MultiStepWizard.setup.$carousel.closest('.modal');

            MultiStepWizard.set('enteredPrompt', initialPrompt);

            let promptValue = settings['enteredPrompt'];
            if(promptValue === undefined || promptValue === '') {
                MultiStepWizard.set('enteredPrompt', initialPrompt);
                promptValue = initialPrompt;
            }
            let imageAiValue = settings['imageAiValue'];
            if(imageAiValue === undefined) {
                MultiStepWizard.set('imageAiValue', initialImageAi);
                imageAiValue = '';
            }
            let postData = {
                imageAiValue: imageAiValue,
                promptValue: promptValue
            };
            HelperFunctions.sendAjaxRequest('aisuite_image_generation_slide_one', postData).then(function (res) {
                if(res.error) {
                    Notification.error(TYPO3.lang['AiSuite.notification.generation.requestError'], res.error);
                } else {
                    slide.html(res.output);
                    modal.find('#wizardSlideOne select[name="promptTemplates"]').on('change', function (event) {
                        modal.find('#wizardSlideOne textarea#imageGenerationPrompt').val(event.target.value);
                    });
                }
                let aiSuiteGenerateImageButton = modal.find('.modal-body').find('button#aiSuiteGenerateImageBtn');
                aiSuiteGenerateImageButton.on('click', function() {
                    let enteredPrompt = modal.find('.modal-body').find('textarea#imageGenerationPrompt').val();
                    let imageAiChecked = modal.find('.modal-body').find('input[name="libraries[imageGenerationLibrary]"]:checked');
                    MultiStepWizard.set('enteredPrompt', enteredPrompt);
                    MultiStepWizard.set('imageAiValue', imageAiChecked.val());
                    if(enteredPrompt.length < 5) {
                        Notification.warning(TYPO3.lang['aiSuite.module.modal.enteredPromptTitle'], TYPO3.lang['aiSuite.module.modal.enteredPromptMessage'], 8);
                    } else {
                        Icons.getIcon('spinner-circle', Icons.sizes.large, null, null).then(function(spinnerIcon) {
                            slide.html('<div class="text-center"><h4>'+TYPO3.lang['aiSuite.module.modal.imageGenerationInProcess']+'</h4><p>' + spinnerIcon + '</p></div>');
                        });
                        let postData = {
                            imagePrompt: enteredPrompt,
                            imageAi: imageAiChecked.val(),
                            pageId: pageId,
                            table: table,
                            fieldName: fieldname,
                            position: position
                        };
                        Notification.info(TYPO3.lang['AiSuite.notification.generation.start'], TYPO3.lang['AiSuite.notification.generation.start.suggestions'], 8);
                        HelperFunctions.sendAjaxRequest('aisuite_regenerate_images', postData).then(function (response) {
                            if(response.error) {
                                Notification.error(TYPO3.lang['AiSuite.notification.generation.requestError'], response.error);
                            } else {
                                let regenerateButton = null;
                                if(table === 'tt_content') {
                                    document.querySelector('form[name="content"] #fields-' + table + ' #generated-images-' + fieldname).innerHTML = response.output;
                                    regenerateButton = document.querySelector('form[name="content"] #fields-' + table + ' #generated-images-' + fieldname).querySelector('.create-content-update-image');
                                } else {
                                    document.querySelector('form[name="content"] #fields-' + table +'-' + position + ' #generated-images-' + fieldname).innerHTML = response.output;
                                    regenerateButton = document.querySelector('form[name="content"] #fields-' + table +'-' + position + ' #generated-images-' + fieldname).querySelector('.create-content-update-image');
                                }
                                if (regenerateButton !== null) {
                                    regenerateButton.addEventListener('click', function(ev) {
                                        ev.preventDefault();
                                        addImageGenerationWizard(enteredPrompt, initialImageAi, pageId, table, position, fieldname, addImageGenerationWizard);
                                    });
                                }
                                Notification.success(TYPO3.lang['AiSuite.notification.generation.finish'], TYPO3.lang['AiSuite.notification.generation.finish.suggestions'], 8);
                                MultiStepWizard.dismiss();
                            }
                        })
                        .catch((error) => {
                            Notification.error(TYPO3.lang['aiSuite.module.modal.imageGenerationFailed'] + error.statusText);
                        });
                    }
                });
            });
        });
        MultiStepWizard.show();
    }
}
export default new Validation();


