import Notification from "@typo3/backend/notification.js";
import Severity from "@typo3/backend/severity.js";
import Modal from "@typo3/backend/modal.js";
import General from "@autodudes/ai-suite/helper/general.js";

class GenerationHandling {
    showGeneralImageSettingsModal(data, scope = '') {
        Modal.advanced(
            {
                type: Modal.types.ajax,
                size: Modal.sizes.large,
                title: TYPO3.lang['aiSuite.module.modal.generalImageGenerationSettings'],
                content: TYPO3.settings.ajaxUrls['aisuite_image_generation_slide_one'],
                severity: Severity.notice,
                ajaxCallback: (currentModal) => {
                    this.addAdditionalImageGenerationSettingsHandling(currentModal);
                    if (General.isUsable(currentModal.querySelector('#wizardSlideOne select[name="promptTemplates"]'))) {
                        currentModal.querySelector('#wizardSlideOne select[name="promptTemplates"]').addEventListener('change', function (event) {
                            currentModal.querySelector('#wizardSlideOne textarea#imageGenerationPrompt').value = event.target.value;
                        });
                    }
                    this.addGenerateImageButton(currentModal, data, scope);
                }
            },
        );
    }
    addGenerateImageButton(
        modal,
        data,
        scope
    ) {
        let self = this;
        let aiSuiteGenerateImageButton = modal.querySelector('.modal-body button#aiSuiteGenerateImageBtn');

        aiSuiteGenerateImageButton.addEventListener('click', async function (ev) {
            let enteredPrompt = modal.querySelector('.modal-body textarea#imageGenerationPrompt').value ?? '';
            let imageAiModel = modal.querySelector('.modal-body input[name="libraries[imageGenerationLibrary]"]:checked').value ?? '';

            let additionalImageSettings = self.getAdditionalImageSettings(modal, imageAiModel);
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
                        const DalleContentElement = (await import('./wizards/dalle-content-element.js')).default
                        DalleContentElement.addImageGenerationWizard(data);
                    } else if (data.imageAiModel === 'Midjourney') {
                        const MidjourneyContentElement = (await import('./wizards/midjourney-content-element.js')).default
                        MidjourneyContentElement.addImageGenerationWizard(data);
                    }
                } else if(scope === 'FileList') {
                    if (data.imageAiModel === 'DALL-E') {
                        const DalleFileList = (await import('./wizards/dalle.js')).default
                        DalleFileList.addImageGenerationWizard(data, true);
                    } else if (data.imageAiModel === 'Midjourney') {
                        const MidjourneyFileList = (await import('./wizards/midjourney.js')).default
                        MidjourneyFileList.addImageGenerationWizard(data, true);
                    }
                } else {
                    if (data.imageAiModel === 'DALL-E') {
                        const Dalle = (await import('./wizards/dalle.js')).default
                        Dalle.addImageGenerationWizard(data);
                    } else if (data.imageAiModel === 'Midjourney') {
                        const Midjourney = (await import('./wizards/midjourney.js')).default
                        Midjourney.addImageGenerationWizard(data);
                    }
                }
            }
        });
    }
    addAdditionalImageGenerationSettingsHandling(modal) {
        let imageGenerationLibraries = modal.querySelectorAll('.modal-body .image-generation-library input[name="libraries[imageGenerationLibrary]"]');
        let imageSettingsMidjourney = modal.querySelector('.modal-body .image-settings-midjourney');

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
    getAdditionalImageSettings(modal, imageAiModel) {
        let additionalSettings = '';
        if(imageAiModel === 'Midjourney') {
            let imageSettingsMidjourneySelect = modal.querySelectorAll('.modal-body .image-settings-midjourney select');
            let imageSettingsMidjourneyInputText = modal.querySelectorAll('.modal-body .image-settings-midjourney input[type="text"]');
            imageSettingsMidjourneySelect.forEach(function(settingsElement) {
                let prefix = settingsElement.getAttribute('data-prefix');
                let value = settingsElement.value;
                additionalSettings += ' ' + prefix + ' ' + value;
            });
            imageSettingsMidjourneyInputText.forEach(function(settingsElement) {
                let prefix = settingsElement.getAttribute('data-prefix');
                let value = settingsElement.value;
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
}

export default new GenerationHandling();
