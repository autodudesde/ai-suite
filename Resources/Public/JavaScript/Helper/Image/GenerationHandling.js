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

            if (enteredPrompt.length < 5) {
                Notification.warning(TYPO3.lang['aiSuite.module.modal.enteredPromptTitle'], TYPO3.lang['aiSuite.module.modal.enteredPromptMessage'], 8);
            } else {
                data.uuid = ev.target.getAttribute('data-uuid');
                data.imagePrompt = enteredPrompt;
                data.imageAiModel = imageAiModel;
                Modal.dismiss();

                if (scope === 'ContentElement') {
                    if (data.imageAiModel === 'DALL-E') {
                        DalleContentElement.addImageGenerationWizard(data, showSpinner, showGeneralImageSettingsModal);
                    } else if (data.imageAiModel === 'Midjourney') {
                        MidjourneyContentElement.addImageGenerationWizard(data, showSpinner, showGeneralImageSettingsModal);
                    }
                } else if(scope === 'FileList') {
                    if (data.imageAiModel === 'DALL-E') {
                        Dalle.addImageGenerationWizard(data, showSpinner, showGeneralImageSettingsModal, true);
                    } else if (data.imageAiModel === 'Midjourney') {
                        Midjourney.addImageGenerationWizard(data, showSpinner, showGeneralImageSettingsModal, true);
                    }
                }
                else {
                    if (data.imageAiModel === 'DALL-E') {
                        Dalle.addImageGenerationWizard(data, showSpinner, showGeneralImageSettingsModal);
                    } else if (data.imageAiModel === 'Midjourney') {
                        Midjourney.addImageGenerationWizard(data, showSpinner, showGeneralImageSettingsModal);
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

    function showSpinner(message, height = 705) {
        return '<style>.modal-body{padding: 0;}.modal-multi-step-wizard .modal-body .carousel-inner {margin: 0 0 0 -5px;}.spinner-wrapper{width:1000px;height:' + height +'px;position:relative;overflow:hidden;}.spinner-overlay{position:absolute;top:0;left:0;width:100%;height:100%;display:flex;justify-content:center;align-content:center;flex-wrap:wrap;background-color:#00000000;color:#fff;font-weight:700;transition:background-color .9s ease-in-out}.spinner-overlay.darken{background-color:rgba(0,0,0,.75)}.spinner,.spinner:after,.spinner:before{text-align:center;opacity:0;width:35px;aspect-ratio:1;box-shadow:0 0 0 3px inset #fff;position:relative;animation:1.5s .5s infinite;animation-name:l7-1,l7-2}.spinner:after,.spinner:before{content:"";position:absolute;left:calc(100% + 5px);animation-delay:1s,0s}.spinner:after{left:-40px;animation-delay:0s,1s}@keyframes l7-1{0%,100%,55%{border-top-left-radius:0;border-bottom-right-radius:0}20%,30%{border-top-left-radius:50%;border-bottom-right-radius:50%}}@keyframes l7-2{0%,100%,55%{border-bottom-left-radius:0;border-top-right-radius:0}20%,30%{border-bottom-left-radius:50%;border-top-right-radius:50%}}.spinner-overlay.darken .spinner,.spinner-overlay.darken .spinner:after,.spinner-overlay.darken .spinner:before{opacity:1}.spinner-overlay.darken .message{position:absolute;top:53%;font-size:.9rem}.spinner-overlay.darken .status{position:absolute;top:57%;font-size:.9rem}</style><div class="spinner-wrapper"><div class="spinner-overlay active darken"><div class="spinner"></div><p class="message">'+message+'</p><p class="status"></p></div></div>'
    }

    return {
        showGeneralImageSettingsModal: showGeneralImageSettingsModal,
        addGenerateImageButton: addGenerateImageButton,
        addAdditionalImageGenerationSettingsHandling: addAdditionalImageGenerationSettingsHandling,
        getAdditionalImageSettings: getAdditionalImageSettings,
        showSpinner: showSpinner
    };
});
