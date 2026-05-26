import Severity from "@typo3/backend/severity.js";
import MultiStepWizard from "@typo3/backend/multi-step-wizard.js";
import Ajax from "@autodudes/ai-suite/helper/ajax.js";
import Generation from "@autodudes/ai-suite/helper/generation.js";
import ResponseHandling from "@autodudes/ai-suite/helper/image/response-handling.js";
import StatusHandling from "@autodudes/ai-suite/helper/image/status-handling.js";

class FluxContentElement {
    constructor() {
        this.intervalId = null;
    }
    addImageGenerationWizard(data) {
        let self = this;
        MultiStepWizard.addSlide('ai-suite-flux-image-generation-step-1', TYPO3.lang['aiSuite.module.modal.imageSelection'], '', Severity.notice, TYPO3.lang['aiSuite.module.modal.fluxSlideOne'], async function (slide, settings) {
            let modalContent = MultiStepWizard.setup.$carousel.closest('.t3js-modal');
            if (modalContent !== null) {
                modalContent.addClass('aisuite-modal');
                modalContent.removeClass('modal-size-default');
                modalContent.addClass('modal-size-large');
            }
            MultiStepWizard.blurCancelStep();
            MultiStepWizard.lockNextStep();
            MultiStepWizard.lockPrevStep();
            slide.html(Generation.showSpinnerModal(TYPO3.lang['aiSuite.module.modal.imageGenerationInProcessFlux'], 705));
            let modal = MultiStepWizard.setup.$carousel.closest('.modal');
            modal.find('.spinner-wrapper').css('overflow', 'hidden');
            Promise.all([self.generateImage(data), StatusHandling.fetchStatus(data, modal, self)])
                .then(([res, status]) => {
                    clearInterval(self.intervalId);
                    ResponseHandling.handleResponseContentElement(res, data, TYPO3.lang['aiSuite.module.modal.fluxSelectionError']);
                })
                .catch(error => {
                    clearInterval(self.intervalId);
                });
        });
        MultiStepWizard.show();
    }

    generateImage(data) {
        return new Promise(async (resolve, reject) => {
            let res = await Ajax.sendAjaxRequest('aisuite_regenerate_images', data);
            resolve(res);
        });
    }
}

export default new FluxContentElement();
