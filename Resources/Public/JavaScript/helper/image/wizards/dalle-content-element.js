import Notification from "@typo3/backend/notification.js";
import Severity from "@typo3/backend/severity.js";
import MultiStepWizard from "@typo3/backend/multi-step-wizard.js";
import Ajax from "@autodudes/ai-suite/helper/ajax.js";
import GenerationHandling from "@autodudes/ai-suite/helper/image/generation-handling.js";
import ResponseHandling from "@autodudes/ai-suite/helper/image/response-handling.js";
import StatusHandling from "@autodudes/ai-suite/helper/image/status-handling.js";

class DalleContentElement {
    constructor() {
        this.intervalId = null;
    }
    addImageGenerationWizard(data) {
        let self = this;
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
            slide.html(GenerationHandling.showSpinner(TYPO3.lang['aiSuite.module.modal.imageGenerationInProcessDalle']));
            let modal = MultiStepWizard.setup.$carousel.closest('.modal');
            modal.find('.spinner-wrapper').css('overflow', 'hidden');
            Notification.info(TYPO3.lang['AiSuite.notification.generation.start'], TYPO3.lang['AiSuite.notification.generation.start.suggestions'], 8);
            Promise.all([self.generateImage(data), StatusHandling.fetchStatus(data, modal, self)])
                .then(([res, status]) => {
                    clearInterval(self.intervalId);
                    ResponseHandling.handleResponseContentElement(res, data, TYPO3.lang['aiSuite.module.modal.dalleSelectionError']);
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

export default new DalleContentElement();
