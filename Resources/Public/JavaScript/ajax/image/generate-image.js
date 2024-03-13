import Notification from "@typo3/backend/notification.js";
import Icons from "@typo3/backend/icons.js";
import Severity from "@typo3/backend/severity.js";
import MultiStepWizard from "@typo3/backend/multi-step-wizard.js";
import ImageManipulation from "@typo3/backend/image-manipulation.js";
import LinkPopup from "@typo3/backend/form-engine/field-control/link-popup.js";

import HelperFunctions from "../../helper/functions.js";

class GenerateImage {
    constructor() {
        let addImageGenerationWizard = this.addImageGenerationWizard;
        this.addEventListener(addImageGenerationWizard);
    }
    addEventListener(addImageGenerationWizard) {
        document.querySelectorAll('.typo3-TCEforms').forEach(function(element) {
            element.addEventListener("click", function(ev) {
                if(ev.target && ev.target.nodeName === "BUTTON" && ev.target.classList.contains('t3js-ai-suite-image-generation-add-btn')) {
                    ev.preventDefault();
                    let objectPrefix = ev.target.getAttribute('data-file-irre-object');
                    let fileContextConfig = ev.target.getAttribute('data-file-context-config');
                    let fileContextHmac = ev.target.getAttribute('data-file-context-hmac');
                    let table = ev.target.getAttribute('data-table');
                    let pageId = ev.target.getAttribute('data-page-id');
                    let position = ev.target.getAttribute('data-position');
                    let fieldname = ev.target.getAttribute('data-fieldname');
                    addImageGenerationWizard(objectPrefix, fileContextConfig, fileContextHmac, pageId, table, position, fieldname);
                }
            });
        });
    }
    addImageGenerationWizard(objectPrefix, fileContextConfig, fileContextHmac, pageId, table, position, fieldname) {
        /**
         * Step 1
         */
        MultiStepWizard.addSlide('ai-suite-generate-add-image-step-1', TYPO3.lang['aiSuite.module.modal.imageGenerationWizard'], '', Severity.info, TYPO3.lang['aiSuite.module.modal.imagePromptSettings'], function(slide, settings) {
            let modalContent = MultiStepWizard.setup.$carousel.closest('.t3js-modal');
            if(modalContent !== null) {
                modalContent.addClass('aisuite-modal');
                modalContent.removeClass('modal-size-default');
                modalContent.addClass('modal-size-large');
            }
            MultiStepWizard.blurCancelStep();
            MultiStepWizard.lockNextStep();
            MultiStepWizard.lockPrevStep();
            let modal = MultiStepWizard.setup.$carousel.closest('.modal');

            MultiStepWizard.set('objectPrefix', objectPrefix);
            MultiStepWizard.set('fileContextConfig', fileContextConfig);
            MultiStepWizard.set('fileContextHmac', fileContextHmac);
            let promptValue = settings['enteredPrompt'];
            if(promptValue === undefined) {
                promptValue = '';
            }
            let imageAiValue = settings['imageAiValue'];
            if(imageAiValue === undefined) {
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
                            fieldName: fieldname,
                            table: table,
                            position: position,
                            pageId: pageId
                        };
                        Notification.info(TYPO3.lang['AiSuite.notification.generation.start'], TYPO3.lang['AiSuite.notification.generation.start.suggestions'], 8);
                        let res = HelperFunctions.sendAjaxRequest('aisuite_image_generation_slide_two', postData);
                        if(res !== null) {
                            res
                                .then(async function (response) {
                                    if(response !== null) {
                                        if(response.error) {
                                            Notification.error(TYPO3.lang['AiSuite.notification.generation.requestError'], response.error);
                                        } else {
                                            Notification.success(TYPO3.lang['AiSuite.notification.generation.finish'], TYPO3.lang['AiSuite.notification.generation.finish.suggestions'], 8);
                                            MultiStepWizard.set('generatedImages', response.output);
                                            MultiStepWizard.set('table', table);
                                            MultiStepWizard.set('position', position);
                                            MultiStepWizard.set('fieldname', fieldname);
                                            MultiStepWizard.unlockNextStep().trigger('click');
                                        }
                                    }
                                })
                                .catch((error) => {
                                    Notification.error(TYPO3.lang['aiSuite.module.modal.imageGenerationFailed'] + error);
                                });
                        }
                    }
                });
            });
        });
        /**
         * Step 2
         */
        MultiStepWizard.addSlide('ai-suite-generate-add-image-step-2', TYPO3.lang['aiSuite.module.modal.imageSelection'], '', Severity.info, TYPO3.lang['aiSuite.module.modal.generatedImages'], function(slide, settings) {

            MultiStepWizard.blurCancelStep();
            MultiStepWizard.lockNextStep();
            MultiStepWizard.unlockPrevStep();
            let modal = MultiStepWizard.setup.$carousel.closest('.modal');

            slide.html(settings['generatedImages']);

            let aiSuiteBackToWizardSlideOneBtn = modal.find('.modal-body').find('button#aiSuiteBackToWizardSlideOneBtn');
            aiSuiteBackToWizardSlideOneBtn.on('click', function() {
                MultiStepWizard.set('generatedImages', '');
                MultiStepWizard.unlockPrevStep().trigger('click');
            });

            let aiSuiteSaveGeneratedImageButton = modal.find('.modal-body').find('button#aiSuiteSaveGeneratedImageBtn');
            aiSuiteSaveGeneratedImageButton.on('click', async function() {
                let selectedImageRadioBtn = modal.find('.modal-body').find('input[name="fileData[content][contentElementData]['+ settings['table'] +']['+ settings['position'] +']['+ settings['fieldname'] +'][newImageUrl]"]:checked');
                let selectedImageTitleRadioBtn = modal.find('.modal-body').find('input[name="fileData[content][contentElementData]['+ settings['table'] +']['+ settings['position'] +']['+ settings['fieldname'] +'][imageTitle]"]:checked');
                if(selectedImageRadioBtn.length > 0) {
                    let imageTitle = '';
                    if(selectedImageTitleRadioBtn !== null) {
                        imageTitle = selectedImageTitleRadioBtn.data('image-title');
                    }
                    let imageUrl = selectedImageRadioBtn.data('url');
                    let postData = {
                        imageUrl: imageUrl,
                        imageTitle: imageTitle
                    };
                    Icons.getIcon('spinner-circle', Icons.sizes.large, null, null).then(function(spinnerIcon) {
                        slide.html('<div class="text-center"><h4>'+TYPO3.lang['aiSuite.module.modal.imageSavingProcess']+'</h4><p>' + spinnerIcon + '</p></div>');
                    });
                    let resFile = await HelperFunctions.sendAjaxRequest('aisuite_image_generation_save', postData);
                    postData = {
                        ajax: {
                            0: settings['objectPrefix'],
                            1: resFile.sysFileUid,
                            context: JSON.stringify({config: settings['fileContextConfig'], hmac: settings['fileContextHmac']})
                        }
                    };
                    let res = await HelperFunctions.sendAjaxRequest('file_reference_create', postData, true);
                    if(res !== null) {
                        let dataKey = Object.keys(res.inlineData.map)[0];
                        document.querySelector('#'+res.inlineData.map[dataKey]+'_records').innerHTML += res.data;
                        let inputField = document.querySelector('input[name="'+dataKey+'"]');
                        if(inputField.value === '') {
                            inputField.value = res.compilerInput.uid;
                        } else {
                            inputField.value += ',' + res.compilerInput.uid;
                        }
                        let addedImages = inputField.value.split(',');
                        let parsedSettings = JSON.parse(settings['fileContextConfig']);
                        if(addedImages.length >= parseInt(parsedSettings.maxitems)) {
                            document.querySelectorAll('form[name="editform"] .form-control-wrap.t3js-inline-controls button').forEach(function(button) {
                                button.style.display = 'none';
                            });
                        }
                        document.querySelectorAll('form[name="editform"] div[data-form-field="'+dataKey+'"] .t3js-formengine-placeholder-formfield').forEach(function(placeholderField) {
                            placeholderField.style.display = 'none';
                        });
                        document.querySelectorAll('form[name="editform"] div[data-form-field="'+dataKey+'"] .t3js-formengine-placeholder-formfield input[type="text"]').forEach(function(inputField) {
                            inputField.addEventListener('keyup', function(event) {
                                document.querySelector('input[name="'+inputField.getAttribute('data-formengine-input-name')+'"]').value = event.target.value;
                            });
                        });
                        document.querySelectorAll('form[name="editform"] div[data-form-field="'+dataKey+'"] .t3js-form-field-link input[type="text"]').forEach(function(inputLinkField) {
                            inputLinkField.addEventListener('change', function(event) {
                                document.querySelector('input[name="'+inputLinkField.getAttribute('data-formengine-input-name')+'"]').value = event.target.value;
                                let inputLinkTooltip = this.closest('.t3js-form-field-link').querySelector('.t3js-form-field-link-explanation');
                                inputLinkTooltip.value = event.target.value;
                            });
                        });
                        res.scriptItems.forEach(function(scriptItem) {
                            if(scriptItem.payload.name === '@typo3/backend/form-engine/field-control/link-popup.js') {
                                let inputLinkId = scriptItem.payload.items[0].args[0];
                                new LinkPopup(inputLinkId);
                            }
                        });
                        ImageManipulation.initializeTrigger();
                        MultiStepWizard.unlockNextStep().trigger('click');
                    }
                } else {
                    Notification.warning(TYPO3.lang['aiSuite.module.modal.noImageSelectedTitle'], TYPO3.lang['aiSuite.module.modal.noImageSelectedMessage'], 8);
                }
            });
        });

        MultiStepWizard.addFinalProcessingSlide(function() {
            MultiStepWizard.dismiss();
        });
        MultiStepWizard.show();
    }
}
export default new GenerateImage();
