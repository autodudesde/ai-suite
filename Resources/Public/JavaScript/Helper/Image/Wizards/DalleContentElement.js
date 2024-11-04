define([
    "TYPO3/CMS/Backend/Severity",
    "TYPO3/CMS/Backend/MultiStepWizard",
    "TYPO3/CMS/AiSuite/Helper/Ajax",
    "TYPO3/CMS/AiSuite/Helper/Image/ResponseHandling",
    "TYPO3/CMS/AiSuite/Helper/Image/StatusHandling",
    "TYPO3/CMS/AiSuite/Helper/Generation",
], function(
    Severity,
    MultiStepWizard,
    Ajax,
    ResponseHandling,
    StatusHandling,
    Generation
) {
    let intervalId = null;

    function addImageGenerationWizard(data, showGeneralImageSettingsModal) {
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
            Promise.all([generateImage(data), StatusHandling.fetchStatus(data, modal, intervalId)])
                .then(([res, status]) => {
                    StatusHandling.stopInterval();
                    ResponseHandling.handleResponseContentElement(res, data, TYPO3.lang['aiSuite.module.modal.dalleSelectionError'], showGeneralImageSettingsModal);
                    MultiStepWizard.dismiss();
                })
                .catch(error => {
                    StatusHandling.stopInterval();
                    MultiStepWizard.dismiss();
                });
        });
        MultiStepWizard.show();
    }

    function generateImage(data) {
        return new Promise(async (resolve, reject) => {
            let res = await Ajax.sendAjaxRequest('aisuite_regenerate_images', data);
            resolve(res);
        });
    }
    return {
        addImageGenerationWizard
    };
});
