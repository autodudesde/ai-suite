define([
    "TYPO3/CMS/Backend/Notification",
    "TYPO3/CMS/Backend/Severity",
    "TYPO3/CMS/Backend/Modal",
    "TYPO3/CMS/AiSuite/Helper/General",
    "TYPO3/CMS/AiSuite/Helper/Image/Wizards/Dalle",
    "TYPO3/CMS/AiSuite/Helper/Image/Wizards/DalleContentElement",
    "TYPO3/CMS/AiSuite/Helper/Image/Wizards/Midjourney",
    "TYPO3/CMS/AiSuite/Helper/Image/Wizards/MidjourneyContentElement"
], function(
    Notification,
    Severity,
    Modal,
    General,
    Dalle,
    DalleContentElement,
    Midjourney,
    MidjourneyContentElement
) {
    let GenerationHandling = function() {}

    GenerationHandling.prototype.showGeneralImageSettingsModal = function(data, scope = '') {
        const self = this;
        Modal.advanced(
            {
                type: Modal.types.ajax,
                size: Modal.sizes.large,
                title: TYPO3.lang['aiSuite.module.modal.generalImageGenerationSettings'],
                content: TYPO3.settings.ajaxUrls['aisuite_image_generation_slide_one'],
                severity: Severity.notice,
                ajaxCallback: () => {
                    self.addAdditionalImageGenerationSettingsHandling(Modal.currentModal);
                    if (General.isUsable(Modal.currentModal.find('#wizardSlideOne select[name="promptTemplates"]'))) {
                        Modal.currentModal.find('#wizardSlideOne select[name="promptTemplates"]').on('change', function () {
                            Modal.currentModal.find('#wizardSlideOne textarea#imageGenerationPrompt').val(this.value);
                        });
                    }
                    Modal.currentModal.find('.modal-body #languageSelection').css('display', 'none');
                    self.addGenerateImageButton(Modal.currentModal, data, scope);
                }
            },
        );
    }
    GenerationHandling.prototype.addGenerateImageButton = function(
        modal,
        data,
        scope
    ) {
        const self = this;
        let aiSuiteGenerateImageButton = modal.find('.modal-body button#aiSuiteGenerateImageBtn');
        aiSuiteGenerateImageButton.on('click', async function (ev) {
            let enteredPrompt = modal.find('.modal-body textarea#imageGenerationPrompt').val() ?? '';
            let imageAiModel = modal.find('.modal-body input[name="libraries[imageGenerationLibrary]"]:checked').val() ?? '';

            try {
                let additionalImageSettings = self.getAdditionalImageSettings(imageAiModel, modal);
                enteredPrompt += additionalImageSettings;

                if (enteredPrompt.length < 10) {
                    Notification.warning(TYPO3.lang['aiSuite.module.modal.enteredPromptTitle'], TYPO3.lang['aiSuite.module.modal.enteredPromptMessage'], 8);
                } else {
                    data.uuid = ev.target.getAttribute('data-uuid');
                    data.imagePrompt = enteredPrompt;
                    data.imageAiModel = imageAiModel;
                    Modal.dismiss();
                    if (scope === 'ContentElement') {
                        if (data.imageAiModel === 'DALL-E') {
                            DalleContentElement.addImageGenerationWizard(data);
                        } else if (data.imageAiModel === 'Midjourney') {
                            MidjourneyContentElement.addImageGenerationWizard(data);
                        }
                    } else if (scope === 'FileList') {
                        data.langIsoCode = modal.find('.modal-body #languageSelection select').val() ?? '';
                        if (data.imageAiModel === 'DALL-E') {
                            Dalle.addImageGenerationWizard(data, true);
                        } else if (data.imageAiModel === 'Midjourney') {
                            Midjourney.addImageGenerationWizard(data, true);
                        }
                    } else {
                        if (data.imageAiModel === 'DALL-E') {
                            Dalle.addImageGenerationWizard(data);
                        } else if (data.imageAiModel === 'Midjourney') {
                            Midjourney.addImageGenerationWizard(data);
                        }
                    }
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
    }
    GenerationHandling.prototype.addAdditionalImageGenerationSettingsHandling = function(modal = null) {
        const selector = modal === null ? document : modal;
        const prefix = modal === null ? '' : '.modal-body ';
        const imageGenerationLibraries = modal ? selector.find(`${prefix}.image-generation-library input[name="libraries[imageGenerationLibrary]"]`) : selector.querySelectorAll(`${prefix}.image-generation-library input[name="libraries[imageGenerationLibrary]"]`);
        const imageSettingsMidjourney = modal ? selector.find(`${prefix}.image-settings-midjourney`) : selector.querySelector(`${prefix}.image-settings-midjourney`);

        if(modal) {
            imageGenerationLibraries.each(function(index, element) {
                $(element).on('click', function(ev) {
                    if($(this).val() === 'Midjourney') {
                        imageSettingsMidjourney.css('display', 'block');
                    } else {
                        imageSettingsMidjourney.css('display', 'none');
                    }
                });
            });
        } else {
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
    }
    GenerationHandling.prototype.getAdditionalImageSettings = function(imageAiModel, modal = null) {
        const self = this;
        let additionalSettings = '';
        if(imageAiModel === 'Midjourney') {
            const selector = modal === null ? document : modal;
            const prefix = modal === null ? '' : '.modal-body ';

            const imageSettingsMidjourneySelect = modal ? selector.find(`${prefix}.image-settings-midjourney select`) : selector.querySelectorAll(`${prefix}.image-settings-midjourney select`);
            const imageSettingsMidjourneyInputText = modal ? selector.find(`${prefix}.image-settings-midjourney input[type="text"]`) : selector.querySelectorAll(`${prefix}.image-settings-midjourney input[type="text"]`);
            if(modal) {
                imageSettingsMidjourneySelect.each(function(index, element) {
                    let prefix = $(element).data('prefix');
                    let value = $(element).val();
                    additionalSettings += ' ' + prefix + ' ' + value;
                });
                imageSettingsMidjourneyInputText.each(function(index, element) {
                    let prefix = $(element).data('prefix');
                    let value = $(element).val();
                    additionalSettings = self.buildAdditionalSettings(additionalSettings, prefix, value);
                });
            } else {
                imageSettingsMidjourneySelect.forEach(function(settingsElement) {
                    let prefix = settingsElement.getAttribute('data-prefix');
                    let value = settingsElement.value;
                    additionalSettings += ' ' + prefix + ' ' + value;
                });
                imageSettingsMidjourneyInputText.forEach(function(settingsElement) {
                    let prefix = settingsElement.getAttribute('data-prefix');
                    let value = settingsElement.value;
                    additionalSettings = self.buildAdditionalSettings(additionalSettings, prefix, value);
                });
            }
        }
        return additionalSettings;
    }
    GenerationHandling.prototype.buildAdditionalSettings = function(additionalSettings, prefix, value) {
        if(prefix === '--no' && value.trim() !== '') {
            value = value.replace(' ', ', ');
        }
        if(prefix === '--sref' && value.trim() !== '') {
            let isValidUrl;
            try {
                const url = new URL(value);
                isValidUrl = url.protocol === 'http:' || url.protocol === 'https:';
            } catch (e) {
                isValidUrl = false;
            }
            if(!isValidUrl) {
                throw new Error('Invalid URL for --sref parameter');
            }
        }
        value = value.trim();
        if(value !== '') {
            additionalSettings += ' ' + prefix + ' ' + value;
        }
        return additionalSettings;
    }

    return new GenerationHandling();
});
