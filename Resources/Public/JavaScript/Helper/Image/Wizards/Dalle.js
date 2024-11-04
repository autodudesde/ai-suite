define([
    "TYPO3/CMS/Backend/Severity",
    "TYPO3/CMS/Backend/MultiStepWizard",
    "TYPO3/CMS/AiSuite/Helper/Ajax",
    "TYPO3/CMS/AiSuite/Helper/Image/SaveHandling",
    "TYPO3/CMS/AiSuite/Helper/Image/ResponseHandling",
    "TYPO3/CMS/AiSuite/Helper/Image/StatusHandling",
    "TYPO3/CMS/AiSuite/Helper/Generation",
    "require"
], function(
    Severity,
    MultiStepWizard,
    Ajax,
    SaveHandling,
    ResponseHandling,
    StatusHandling,
    Generation,
) {
    function addImageGenerationWizard(data, showGeneralImageSettingsModal, filelistScope = false) {
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
            slide.html(Generation.showSpinnerModal(TYPO3.lang['aiSuite.module.modal.imageGenerationInProcessDalle'], 695));
            let modal = MultiStepWizard.setup.$carousel.closest('.modal');
            modal.find('.spinner-wrapper').css('overflow', 'hidden');
            StatusHandling.fetchStatus(data, modal)
            generateImage(data)
                .then((res) => {
                    StatusHandling.stopInterval();
                    ResponseHandling.handleResponse(res, TYPO3.lang['aiSuite.module.modal.dalleSelectionError']);
                    slide.html(settings['generatedData']);
                    addSelectionEventListeners(modal, data, slide, showGeneralImageSettingsModal, filelistScope);
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

    function addSelectionEventListeners(modal, data, slide, showGeneralImageSettingsModal, filelistScope) {
        backToSlideOneButton(modal, data, showGeneralImageSettingsModal);
        SaveHandling.selectionHandler(modal, 'img.ce-image-selection');
        SaveHandling.selectionHandler(modal, 'label.ce-image-title-selection');
        if(filelistScope) {
            SaveHandling.saveGeneratedImageFileListButton(modal, data, slide);
        } else {
            SaveHandling.saveGeneratedImageButton(modal, data, slide);
        }
    }

    function backToSlideOneButton(modal, data, showGeneralImageSettingsModal) {
        let aiSuiteBackToWizardSlideOneBtn = modal.find('.modal-body').find('button#aiSuiteBackToWizardSlideOneBtn');
        aiSuiteBackToWizardSlideOneBtn.on('click', function() {
            MultiStepWizard.set('generatedData', '');
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
