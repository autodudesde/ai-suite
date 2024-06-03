define([
    "TYPO3/CMS/Backend/Notification",
    "TYPO3/CMS/Backend/Severity",
    "TYPO3/CMS/Backend/MultiStepWizard",
    "TYPO3/CMS/AiSuite/Helper/Ajax",
    "TYPO3/CMS/AiSuite/Helper/Image/SaveHandling",
    "TYPO3/CMS/AiSuite/Helper/Image/ResponseHandling",
    "TYPO3/CMS/AiSuite/Helper/Image/StatusHandling",
    "require"
], function(
    Notification,
    Severity,
    MultiStepWizard,
    Ajax,
    SaveHandling,
    ResponseHandling,
    StatusHandling
) {
    function addImageGenerationWizard(data, showSpinner, showGeneralImageSettingsModal, filelistScope = false) {
        MultiStepWizard.addSlide('ai-suite-dalle-image-generation-step-1', TYPO3.lang['aiSuite.module.modal.imageSelection'], '', Severity.notice, TYPO3.lang['aiSuite.module.modal.dalleSlideOne'], async function (slide, settings) {
            let modalContent = MultiStepWizard.setup.$carousel.closest('.t3js-modal');
            if (modalContent !== null) {
                modalContent.addClass('aisuite-modal');
                modalContent.removeClass('modal-size-default');
                modalContent.addClass('modal-size-large');
            }
            MultiStepWizard.blurCancelStep();
            MultiStepWizard.lockNextStep();
            MultiStepWizard.lockPrevStep();
            slide.html(showSpinner(TYPO3.lang['aiSuite.module.modal.imageGenerationInProcessDalle']));
            let modal = MultiStepWizard.setup.$carousel.closest('.modal');
            modal.find('.spinner-wrapper').css('overflow', 'hidden');
            Notification.info(TYPO3.lang['AiSuite.notification.generation.start'], TYPO3.lang['AiSuite.notification.generation.start.suggestions'], 8);
            StatusHandling.fetchStatus(data, modal)
            generateImage(data)
                .then((res) => {
                    StatusHandling.stopInterval();
                    ResponseHandling.handleResponse(res, TYPO3.lang['aiSuite.module.modal.dalleSelectionError']);
                    slide.html(settings['generatedImages']);
                    addSelectionEventListeners(modal, data, slide, showSpinner, showGeneralImageSettingsModal, filelistScope);
                })
                .catch(error => {
                    StatusHandling.stopInterval();
                });
        });
        MultiStepWizard.show();
    }

     function generateImage(data) {
        return new Promise(async (resolve, reject) => {
            let res = await Ajax.sendAjaxRequest('aisuite_image_generation_slide_two', data);
            resolve(res);
        });
    }

    function addSelectionEventListeners(modal, data, slide, showSpinner, showGeneralImageSettingsModal, filelistScope) {
        backToSlideOneButton(modal, data, showGeneralImageSettingsModal);
        SaveHandling.selectionHandler(modal, 'img.ce-image-selection');
        SaveHandling.selectionHandler(modal, 'label.ce-image-title-selection');
        if(filelistScope) {
            SaveHandling.saveGeneratedImageFileListButton(modal, data, slide, showSpinner);
        } else {
            SaveHandling.saveGeneratedImageButton(modal, data, slide, showSpinner);
        }
    }

    function backToSlideOneButton(modal, data, showGeneralImageSettingsModal) {
        let aiSuiteBackToWizardSlideOneBtn = modal.find('.modal-body').find('button#aiSuiteBackToWizardSlideOneBtn');
        aiSuiteBackToWizardSlideOneBtn.on('click', function() {
            MultiStepWizard.set('generatedImages', '');
            MultiStepWizard.dismiss();
            showGeneralImageSettingsModal(data);
        });
    }
    return {
        addImageGenerationWizard: addImageGenerationWizard,
        generateImage: generateImage,
        addSelectionEventListeners: addSelectionEventListeners,
        backToSlideOneButton: backToSlideOneButton
    }
});
