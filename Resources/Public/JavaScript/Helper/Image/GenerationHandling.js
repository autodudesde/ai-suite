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
    function showGeneralImageSettingsModal(data, scope = '') {
        Modal.advanced(
            {
                type: Modal.types.ajax,
                size: Modal.sizes.large,
                title: TYPO3.lang['aiSuite.module.modal.generalImageGenerationSettings'],
                content: TYPO3.settings.ajaxUrls['aisuite_image_generation_slide_one'],
                severity: Severity.notice,
                ajaxCallback: () => {
                    addAdditionalImageGenerationSettingsHandling(Modal.currentModal);
                    if (General.isUsable(Modal.currentModal.find('#wizardSlideOne select[name="promptTemplates"]'))) {
                        Modal.currentModal.find('#wizardSlideOne select[name="promptTemplates"]').on('change', function () {
                            Modal.currentModal.find('#wizardSlideOne textarea#imageGenerationPrompt').val(this.value);
                        });
                    }
                    addGenerateImageButton(Modal.currentModal, data, scope);
                }
            },
        );
    }
    function addGenerateImageButton(
        modal,
        data,
        scope
    ) {
        let aiSuiteGenerateImageButton = modal.find('.modal-body button#aiSuiteGenerateImageBtn');
        aiSuiteGenerateImageButton.on('click', async function (ev) {
            let enteredPrompt = modal.find('.modal-body textarea#imageGenerationPrompt').val() ?? '';
            let imageAiModel = modal.find('.modal-body input[name="libraries[imageGenerationLibrary]"]:checked').val() ?? '';

            let additionalImageSettings = getAdditionalImageSettings(modal, imageAiModel);
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
                        DalleContentElement.addImageGenerationWizard(data, showGeneralImageSettingsModal);
                    } else if (data.imageAiModel === 'Midjourney') {
                        MidjourneyContentElement.addImageGenerationWizard(data, showGeneralImageSettingsModal);
                    }
                } else if(scope === 'FileList') {
                    if (data.imageAiModel === 'DALL-E') {
                        Dalle.addImageGenerationWizard(data, showGeneralImageSettingsModal, true);
                    } else if (data.imageAiModel === 'Midjourney') {
                        Midjourney.addImageGenerationWizard(data, showGeneralImageSettingsModal, true, true);
                    }
                } else {
                    if (data.imageAiModel === 'DALL-E') {
                        Dalle.addImageGenerationWizard(data, showGeneralImageSettingsModal);
                    } else if (data.imageAiModel === 'Midjourney') {
                        Midjourney.addImageGenerationWizard(data, showGeneralImageSettingsModal);
                    }
                }
            }
        });
    }
    function addAdditionalImageGenerationSettingsHandling(modal) {
        let imageGenerationLibraries = modal.find('.modal-body .image-generation-library input[name="libraries[imageGenerationLibrary]"]');
        let imageSettingsMidjourney = modal.find('.modal-body .image-settings-midjourney');
        imageGenerationLibraries.each(function() {
            this.addEventListener('click', function() {
                if(this.value === 'Midjourney') {
                    imageSettingsMidjourney.css('display', 'block');
                } else {
                    imageSettingsMidjourney.css('display', 'none');
                }
            });
        });
    }
    function getAdditionalImageSettings(modal, imageAiModel) {
        let additionalSettings = '';
        if(imageAiModel === 'Midjourney') {
            let imageSettingsMidjourneySelect = modal.find('.modal-body .image-settings-midjourney select');
            let imageSettingsMidjourneyInputText = modal.find('.modal-body .image-settings-midjourney input[type="text"]');
            imageSettingsMidjourneySelect.each(function() {
                let prefix = this.getAttribute('data-prefix');
                let value = this.value;
                additionalSettings += ' ' + prefix + ' ' + value;
            });
            imageSettingsMidjourneyInputText.each(function() {
                let prefix = this.getAttribute('data-prefix');
                let value = this.value;
                if(prefix === '--no' && value.trim() !== '') {
                    value = value.replace(' ', ', ');
                }
                value = value.trim();
                if(value !== '') {
                    additionalSettings += ' ' + prefix + ' ' + value;
                }
            });
        }
        return additionalSettings;
    }

    return {
        showGeneralImageSettingsModal: showGeneralImageSettingsModal,
        addGenerateImageButton: addGenerateImageButton,
        addAdditionalImageGenerationSettingsHandling: addAdditionalImageGenerationSettingsHandling,
        getAdditionalImageSettings: getAdditionalImageSettings
    };
});
