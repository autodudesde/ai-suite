import MultiStepWizard from "@typo3/backend/multi-step-wizard.js";
import Notification from "@typo3/backend/notification.js";
import ImageManipulation from "@typo3/backend/image-manipulation.js";
import Ajax from "@autodudes/ai-suite/helper/ajax.js";
import Generation from "@autodudes/ai-suite/helper/generation.js";
import General from "@autodudes/ai-suite/helper/general.js";

class SaveHandling {
    backToSlideOneButton(modal) {
        let aiSuiteBackToWizardSlideOneBtn = modal.find('.modal-body').find('button#aiSuiteBackToWizardSlideOneBtn');
        aiSuiteBackToWizardSlideOneBtn.on('click', function() {
            MultiStepWizard.set('generatedData', '');
            MultiStepWizard.unlockPrevStep().trigger('click');
        });
    }
    selectionHandler(modal, selector) {
        modal.find('.modal-body').find(selector).on("click", function(ev){
            let selectionGroup = ev.target.getAttribute('data-selection-group');
            let selectionId = ev.target.getAttribute('data-selection-id');
            modal.find('.modal-body').find('input[data-selection-group="'+selectionGroup+'"]').each(function(index, inputField) {
                if(inputField.id !== selectionId) {
                    inputField.checked = false;
                }
            });
        });
    }
    saveGeneratedImageButton(modal, data, slide) {
        let self = this;
        let aiSuiteSaveGeneratedImageButton = modal.find('.modal-body').find('button#aiSuiteSaveGeneratedImageBtn');
        aiSuiteSaveGeneratedImageButton.on('click', async function() {
            let selectedImageRadioBtn = modal.find('.modal-body').find('input[name="fileData[content][contentElementData]['+ data.table +']['+ data.position +']['+ data.fieldName +'][newImageUrl]"]:checked');
            if(selectedImageRadioBtn.length > 0) {
                let imageTitle = self.getSelectedImageTitle(modal, data);
                let imageUrl = selectedImageRadioBtn.data('url');
                let postData = {
                    imageUrl: imageUrl,
                    imageTitle: imageTitle
                };
                slide.html(Generation.showSpinnerModal(TYPO3.lang['aiSuite.module.modal.imageSavingProcess'], 705));
                modal.find('.spinner-wrapper').css('overflow', 'hidden');
                let resFile = await Ajax.sendAjaxRequest('aisuite_image_generation_save', postData);
                postData = {
                    ajax: {
                        0: data.objectPrefix,
                        1: resFile.sysFileUid,
                        context: JSON.stringify({config: data.fileContextConfig, hmac: data.fileContextHmac})
                    }
                };
                let res = await Ajax.sendAjaxRequest('file_reference_create', postData, true);
                if(res !== null) {
                    let dataKey = Object.keys(res.inlineData.map)[0];
                    self.addImageToFileControlsPanel(modal, data.fileContextConfig, res, dataKey);
                    document.querySelectorAll('form[name="editform"] div[data-form-field="'+dataKey+'"] .t3js-formengine-placeholder-formfield').forEach(function(placeholderField) {
                        placeholderField.style.display = 'none';
                    });
                    if(imageTitle !== undefined) {
                        self.setSysFileReferenceField('title', res.compilerInput.uid, dataKey, imageTitle);
                        self.setSysFileReferenceField('alternative', res.compilerInput.uid, dataKey, imageTitle);
                    }
                    self.addInputFieldKeyupListener(dataKey);
                    self.addLinkTooltipFunctionality(dataKey, res);
                    ImageManipulation.initializeTrigger();
                    MultiStepWizard.dismiss();
                }
            } else {
                Notification.warning(TYPO3.lang['aiSuite.module.modal.noImageSelectedTitle'], TYPO3.lang['aiSuite.module.modal.noImageSelectedMessage'], 8);
            }
        });
    }
    saveGeneratedImageFileListButton(modal, data, slide) {
        let self = this;
        let aiSuiteSaveGeneratedImageButton = modal.find('.modal-body').find('button#aiSuiteSaveGeneratedImageBtn');
        aiSuiteSaveGeneratedImageButton.on('click', async function() {
            let selectedImageRadioBtn = modal.find('.modal-body').find('input.image-selection:checked');
            if(selectedImageRadioBtn.length > 0) {
                let imageTitle = self.getSelectedImageTitle(modal, data, true);
                let imageName = General.sanitizeFileName(imageTitle);
                let imageUrl = selectedImageRadioBtn.data('url');
                slide.html(Generation.showSpinnerModal(TYPO3.lang['aiSuite.module.modal.imageSavingProcess'], 705));
                modal.find('.spinner-wrapper').css('overflow', 'hidden');
                try {
                    await fetch(imageUrl, {mode: 'cors'})
                        .then(res => res.blob())
                        .then(blob => {
                            imageName += '.' + blob.type.split('/')[1];
                        });
                } catch (e) {
                    const fileExtension = imageUrl.split('.').pop();
                    imageName += '.' + fileExtension;
                }
                let postData = {
                    'fileName': imageName,
                    'fileTarget': data.targetFolder
                };
                let existFileRes = await Ajax.sendAjaxRequest('file_exists', postData, true);
                if(General.isUsable(existFileRes) && General.isUsable(existFileRes.id)) {
                    const fileExtension = imageName.split('.').pop();
                    imageName = imageName.replace('.' + fileExtension, '');
                    imageName = imageName + '-' + Date.now();
                    imageName += '.' + fileExtension;
                }

                postData = {
                    'fileName': imageName,
                    'fileTitle': imageTitle,
                    'fileTarget': data.targetFolder,
                    'fileUrl': imageUrl
                };

                let processFileRes = await Ajax.sendAjaxRequest('aisuite_file_process', postData, true);

                if(General.isUsable(processFileRes)) {
                    if(General.isUsable(processFileRes.error)) {
                        Notification.error(TYPO3.lang['aiSuite.module.modal.error'], processFileRes.error, 8);
                        return;
                    } else {
                        Notification.info(TYPO3.lang["file_upload.reload.filelist"], TYPO3.lang["file_upload.reload.filelist.message"], 10);
                        Notification.success("", TYPO3.lang['aiSuite.module.notification.filelist.upload.success.message'], 8);
                    }
                }
                MultiStepWizard.dismiss();
            } else {
                Notification.warning(TYPO3.lang['aiSuite.module.modal.noImageSelectedTitle'], TYPO3.lang['aiSuite.module.modal.noImageSelectedMessage'], 5);
            }
        });
    }
    getSelectedImageTitle(modal, data, fromFileList = false) {
        let selectedImageTitleRadioBtn = modal.find('.modal-body').find('input[name="fileData[content][contentElementData]['+ data.table +']['+ data.position +']['+ data.fieldName +'][imageTitle]"]:checked');
        let selectedImageTitleInputFreeText = modal.find('.modal-body').find('input[name="fileData[content][contentElementData]['+ data.table +']['+ data.position +']['+ data.fieldName +'][imageTitleFreeText]"]').val();
        if(fromFileList) {
            selectedImageTitleRadioBtn = modal.find('.modal-body').find('input.image-title-selection:checked');
            selectedImageTitleInputFreeText = modal.find('.modal-body').find('input.image-title-free-text-input').val();
        }
        let imageTitle = '';
        if(selectedImageTitleRadioBtn !== null) {
            imageTitle = selectedImageTitleRadioBtn.data('image-title');
        }
        if(selectedImageTitleInputFreeText !== undefined && selectedImageTitleInputFreeText !== '') {
            imageTitle = selectedImageTitleInputFreeText;
        }
        return imageTitle;
    }
    addImageToFileControlsPanel(modal, fileContextConfig, res, dataKey) {
        document.querySelector('#'+res.inlineData.map[dataKey]+'_records').innerHTML += res.data;
        let inputField = document.querySelector('input[name="'+dataKey+'"]');
        if(inputField.value === '') {
            inputField.value = res.compilerInput.uid;
        } else {
            inputField.value += ',' + res.compilerInput.uid;
        }
        let addedImages = inputField.value.split(',');
        let parsedSettings = JSON.parse(fileContextConfig);
        if(addedImages.length >= parseInt(parsedSettings.maxitems)) {
            document.querySelectorAll('form[name="editform"] .form-control-wrap.t3js-inline-controls button').forEach(function(button) {
                button.style.display = 'none';
            });
        }
    }

    addLinkTooltipFunctionality(dataKey, res) {
        document.querySelectorAll('form[name="editform"] div[data-form-field="'+dataKey+'"] .t3js-form-field-link input[type="text"]').forEach(function(inputLinkField) {
            inputLinkField.addEventListener('change', function(event) {
                document.querySelector('input[name="'+inputLinkField.getAttribute('data-formengine-input-name')+'"]').value = event.target.value;
                let inputLinkTooltip = this.closest('.t3js-form-field-link').querySelector('.t3js-form-field-link-explanation');
                inputLinkTooltip.value = event.target.value;
            });
        });
        res.scriptItems.forEach(async function(scriptItem) {
            if(scriptItem.payload.name === '@typo3/backend/form-engine/field-control/link-popup.js') {
                let inputLinkId = scriptItem.payload.items[0].args[0];
                const LinkPopup = (await import('@typo3/backend/form-engine/field-control/link-popup.js')).default
                new LinkPopup(inputLinkId);
            }
        });
    }
    setSysFileReferenceField(fieldName, resUid, dataKey, imageTitle) {
        let fieldHidden = document.querySelector('form[name="editform"] div[data-form-field="'+dataKey+'"] input[name="data[sys_file_reference][' + resUid +']['+fieldName+']"]');
        let field = document.querySelector('form[name="editform"] div[data-form-field="'+dataKey+'"] input[data-formengine-input-name="data[sys_file_reference][' + resUid +']['+fieldName+']"]');
        if(fieldHidden !== null && field !== null) {
            fieldHidden.value = imageTitle;
            field.value = imageTitle;
            document.getElementById('control[active][sys_file_reference][' + resUid +']['+fieldName+']').click()
        }
    }
    addInputFieldKeyupListener(dataKey) {
        document.querySelectorAll('form[name="editform"] div[data-form-field="'+dataKey+'"] .t3js-formengine-placeholder-formfield input[type="text"]').forEach(function(inputField) {
            inputField.addEventListener('keyup', function(event) {
                document.querySelector('input[name="'+inputField.getAttribute('data-formengine-input-name')+'"]').value = event.target.value;
            });
        });
    }
}

export default new SaveHandling();
