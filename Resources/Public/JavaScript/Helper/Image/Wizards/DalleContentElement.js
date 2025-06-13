define([
    "TYPO3/CMS/Backend/Severity",
    "TYPO3/CMS/Backend/MultiStepWizard",
    'TYPO3/CMS/AiSuite/Helper/Ajax',
    'TYPO3/CMS/AiSuite/Helper/Generation',
    'TYPO3/CMS/AiSuite/Helper/Image/ResponseHandling',
    'TYPO3/CMS/AiSuite/Helper/Image/StatusHandling'
], function(
    Severity,
    MultiStepWizard,
    Ajax,
    Generation,
    ResponseHandling,
    StatusHandling
) {
    'use strict';

    let DalleContentElement = function() {
        this.intervalId = null;
    };

    DalleContentElement.prototype.addImageGenerationWizard = function(data) {
        const self = this;
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
            slide.html(Generation.showSpinnerModal(TYPO3.lang['aiSuite.module.modal.imageGenerationInProcessDalle'], 705));
            let modal = MultiStepWizard.setup.$carousel.closest('.modal');
            modal.find('.spinner-wrapper').css('overflow', 'hidden');
            Promise.all([self.generateImage(data), StatusHandling.fetchStatus(data, modal, self)])
                .then(([res, status]) => {
                    clearInterval(self.intervalId);
                    MultiStepWizard.dismiss();
                    ResponseHandling.handleResponseContentElement(res, data, TYPO3.lang['aiSuite.module.modal.dalleSelectionError']);
                })
                .catch(error => {
                    clearInterval(self.intervalId);
                    MultiStepWizard.dismiss();
                });
        });
        MultiStepWizard.show();
    }

    DalleContentElement.prototype.generateImage = function(data) {
        return new Promise(async (resolve, reject) => {
            let res = await Ajax.sendAjaxRequest('aisuite_regenerate_images', data);
            resolve(res);
        });
    }

    return new DalleContentElement();
});
