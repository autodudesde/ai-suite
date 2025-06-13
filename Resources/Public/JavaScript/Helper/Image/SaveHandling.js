define([
    "TYPO3/CMS/Backend/MultiStepWizard",
    "TYPO3/CMS/Backend/Notification",
    "TYPO3/CMS/Backend/ImageManipulation",
    "TYPO3/CMS/AiSuite/Helper/Ajax",
    "TYPO3/CMS/AiSuite/Helper/Generation",
    "TYPO3/CMS/AiSuite/Helper/General",
    "require"
], function(MultiStepWizard, Notification, ImageManipulation, Ajax, Generation, General, require) {
    'use strict';

    let SaveHandling = function() {};

    SaveHandling.prototype.backToSlideOneButton = function(modal) {
        const aiSuiteBackToWizardSlideOneBtn = modal.find('.modal-body').find('button#aiSuiteBackToWizardSlideOneBtn');
        aiSuiteBackToWizardSlideOneBtn.on('click', function() {
            MultiStepWizard.set('generatedData', '');
            MultiStepWizard.unlockPrevStep().trigger('click');
        });
    };

    /**
     * Handle selection events
     *
     * @param {Object} modal The modal object
     * @param {String} selector The selector for clickable elements
     */
    SaveHandling.prototype.selectionHandler = function(modal, selector) {
        modal.find('.modal-body').find(selector).on("click", function(ev){
            let selectionGroup = ev.target.getAttribute('data-selection-group');
            let selectionId = ev.target.getAttribute('data-selection-id');
            modal.find('.modal-body').find('input[data-selection-group="'+selectionGroup+'"]').each(function(index, inputField) {
                if(inputField.id !== selectionId) {
                    inputField.checked = false;
                }
            });
        });
    };

    /**
     * Add save generated image button functionality
     *
     * @param {Object} modal The modal object
     * @param {Object} data The data object
     * @param {Object} slide The slide object
     */
    SaveHandling.prototype.saveGeneratedImageButton = function(modal, data, slide) {
        let self = this;
        let aiSuiteSaveGeneratedImageButton = modal.find('.modal-body').find('button#aiSuiteSaveGeneratedImageBtn');

        aiSuiteSaveGeneratedImageButton.on('click', async function() {
            let selectedImageRadioBtn = modal.find('.modal-body').find('input[name="fileData[contentElementData]['+ data.table +']['+ data.position +']['+ data.fieldName +'][newImageUrl]"]:checked');

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
                let res = await Ajax.sendAjaxRequest('record_inline_create', postData, true);
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
    };

    /**
     * Add save generated image button functionality for file list
     *
     * @param {Object} modal The modal object
     * @param {Object} data The data object
     * @param {Object} slide The slide object
     */
    SaveHandling.prototype.saveGeneratedImageFileListButton = function(modal, data, slide) {
        const self = this;
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
    };

    /**
     * Get the selected image title
     *
     * @param {Object} modal The modal object
     * @param {Object} data The data object
     * @param {Boolean} fromFileList Whether the selection is from file list
     * @return {String} The selected image title
     */
    SaveHandling.prototype.getSelectedImageTitle = function(modal, data, fromFileList) {
        fromFileList = fromFileList || false;

        let selectedImageTitleRadioBtn, selectedImageTitleInputFreeText;

        if(fromFileList) {
            selectedImageTitleRadioBtn = modal.find('.modal-body').find('input.image-title-selection:checked');
            selectedImageTitleInputFreeText = modal.find('.modal-body').find('input.image-title-free-text-input').val();
        } else {
            selectedImageTitleRadioBtn = modal.find('.modal-body').find('input[name="fileData[contentElementData]['+ data.table +']['+ data.position +']['+ data.fieldName +'][imageTitle]"]:checked');
            selectedImageTitleInputFreeText = modal.find('.modal-body').find('input[name="fileData[contentElementData]['+ data.table +']['+ data.position +']['+ data.fieldName +'][imageTitleFreeText]"]').val();
        }

        let imageTitle = '';
        if(selectedImageTitleRadioBtn !== null && selectedImageTitleRadioBtn.length > 0) {
            imageTitle = selectedImageTitleRadioBtn.data('image-title');
        }
        if(selectedImageTitleInputFreeText !== undefined && selectedImageTitleInputFreeText !== '') {
            imageTitle = selectedImageTitleInputFreeText;
        }

        return imageTitle;
    };

    /**
     * Add image to file controls panel
     *
     * @param {Object} modal The modal object
     * @param {String} fileContextConfig The file context configuration
     * @param {Object} res The response object
     * @param {String} dataKey The data key
     */
    SaveHandling.prototype.addImageToFileControlsPanel = function(modal, fileContextConfig, res, dataKey) {
        document.querySelector('#'+res.inlineData.map[dataKey]).innerHTML += res.data;
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
    };

    /**
     * Add link tooltip functionality
     *
     * @param {String} dataKey The data key
     * @param {Object} res The response object
     */
    SaveHandling.prototype.addLinkTooltipFunctionality = function(dataKey, res) {
        document.querySelectorAll('form[name="editform"] div[data-form-field="'+dataKey+'"] .t3js-form-field-link input[type="text"]').forEach(function(inputLinkField) {
            inputLinkField.addEventListener('change', function(event) {
                document.querySelector('input[name="'+inputLinkField.getAttribute('data-formengine-input-name')+'"]').value = event.target.value;
                let inputLinkTooltip = this.closest('.t3js-form-field-link').querySelector('.t3js-form-field-link-explanation');
                inputLinkTooltip.value = event.target.value;
            });
        });

        for (let i = 0; i < res.scriptItems.length; i++) {
            let scriptItem = res.scriptItems[i];
            if(scriptItem.payload.name === 'TYPO3/CMS/Backend/FormEngine/FieldControl/LinkPopup') {
                let inputLinkId = scriptItem.payload.items[0].args[0];
                require(["TYPO3/CMS/Backend/FormEngine/FieldControl/LinkPopup"], function(LinkPopup) {
                    new LinkPopup(inputLinkId);
                });
            }
        }
    };

    /**
     * Set sys file reference field
     *
     * @param {String} fieldName The field name
     * @param {String} resUid The response UID
     * @param {String} dataKey The data key
     * @param {String} imageTitle The image title
     */
    SaveHandling.prototype.setSysFileReferenceField = function(fieldName, resUid, dataKey, imageTitle) {
        let fieldHidden = document.querySelector('form[name="editform"] div[data-form-field="'+dataKey+'"] input[name="data[sys_file_reference][' + resUid +']['+fieldName+']"]');
        let field = document.querySelector('form[name="editform"] div[data-form-field="'+dataKey+'"] input[data-formengine-input-name="data[sys_file_reference][' + resUid +']['+fieldName+']"]');

        if(fieldHidden !== null && field !== null) {
            fieldHidden.value = imageTitle;
            field.value = imageTitle;
            document.getElementById('control[active][sys_file_reference][' + resUid +']['+fieldName+']').click();
        }
    };

    /**
     * Add input field keyup listener
     *
     * @param {String} dataKey The data key
     */
    SaveHandling.prototype.addInputFieldKeyupListener = function(dataKey) {
        document.querySelectorAll('form[name="editform"] div[data-form-field="'+dataKey+'"] .t3js-formengine-placeholder-formfield input[type="text"]').forEach(function(inputField) {
            inputField.addEventListener('keyup', function(event) {
                document.querySelector('input[name="'+inputField.getAttribute('data-formengine-input-name')+'"]').value = event.target.value;
            });
        });
    };

    return new SaveHandling();
});
