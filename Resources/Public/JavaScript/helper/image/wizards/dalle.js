import Notification from "@typo3/backend/notification.js";
import Severity from "@typo3/backend/severity.js";
import MultiStepWizard from "@typo3/backend/multi-step-wizard.js";
import Ajax from "@autodudes/ai-suite/helper/ajax.js";
import GenerationHandling from "@autodudes/ai-suite/helper/image/generation-handling.js";
import SaveHandling from "@autodudes/ai-suite/helper/image/save-handling.js";
import ResponseHandling from "@autodudes/ai-suite/helper/image/response-handling.js";
import StatusHandling from "@autodudes/ai-suite/helper/image/status-handling.js";

class Dalle {

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
                    ResponseHandling.handleResponse(res, TYPO3.lang['aiSuite.module.modal.dalleSelectionError']);
                    slide.html(settings['generatedImages']);
                    self.addSelectionEventListeners(modal, data, slide, self);
                })
                .catch(error => {
                    clearInterval(self.intervalId);
                });
        });
        MultiStepWizard.show();
    }

     generateImage(data) {
        return new Promise(async (resolve, reject) => {
            let res = await Ajax.sendAjaxRequest('aisuite_image_generation_slide_two', data);
            resolve(res);
        });
    }

    addSelectionEventListeners(modal, data, slide, self) {
        self.backToSlideOneButton(modal, data);
        SaveHandling.selectionHandler(modal, 'img.ce-image-selection');
        SaveHandling.selectionHandler(modal, 'label.ce-image-title-selection');
        SaveHandling.saveGeneratedImageButton(modal, data, slide);
    }

    backToSlideOneButton(modal, data) {
        let aiSuiteBackToWizardSlideOneBtn = modal.find('.modal-body').find('button#aiSuiteBackToWizardSlideOneBtn');
        aiSuiteBackToWizardSlideOneBtn.on('click', function() {
            MultiStepWizard.set('generatedImages', '');
            MultiStepWizard.dismiss();
            GenerationHandling.showGeneralImageSettingsModal(data);
        });
    }
}

export default new Dalle();
