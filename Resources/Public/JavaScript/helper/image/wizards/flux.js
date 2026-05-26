import Severity from "@typo3/backend/severity.js";
import MultiStepWizard from "@typo3/backend/multi-step-wizard.js";
import Ajax from "@autodudes/ai-suite/helper/ajax.js";
import GenerationHandling from "@autodudes/ai-suite/helper/image/generation-handling.js";
import SaveHandling from "@autodudes/ai-suite/helper/image/save-handling.js";
import ResponseHandling from "@autodudes/ai-suite/helper/image/response-handling.js";
import StatusHandling from "@autodudes/ai-suite/helper/image/status-handling.js";
import Generation from "@autodudes/ai-suite/helper/generation.js";

class Flux {

    constructor() {
        this.intervalId = null;
    }
    addImageGenerationWizard(data, filelistScope = false) {
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
            slide.html(Generation.showSpinnerModal(TYPO3.lang['aiSuite.module.modal.imageGenerationInProcessFlux'], 695));
            let modal = MultiStepWizard.setup.$carousel.closest('.modal');
            modal.find('.spinner-wrapper').css('overflow', 'hidden');
            Promise.all([self.generateImage(data), StatusHandling.fetchStatus(data, modal, self)])
                .then(([res, status]) => {
                    clearInterval(self.intervalId);
                    ResponseHandling.handleResponse(res, TYPO3.lang['aiSuite.module.modal.fluxSelectionError']);
                    slide.html(settings['generatedData']);
                    self.addSelectionEventListeners(modal, data, slide, self, filelistScope);
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

    addSelectionEventListeners(modal, data, slide, self, filelistScope) {
        self.backToSlideOneButton(modal, data);
        SaveHandling.selectionHandler(modal, 'img.ce-image-selection');
        SaveHandling.selectionHandler(modal, 'label.ce-image-title-selection');
        if(filelistScope) {
            SaveHandling.saveGeneratedImageFileListButton(modal, data, slide);
        } else {
            SaveHandling.saveGeneratedImageButton(modal, data, slide);
        }
    }

    backToSlideOneButton(modal, data) {
        let aiSuiteBackToWizardSlideOneBtn = modal.find('.modal-body').find('button#aiSuiteBackToWizardSlideOneBtn');
        aiSuiteBackToWizardSlideOneBtn.on('click', function() {
            MultiStepWizard.set('generatedData', '');
            MultiStepWizard.dismiss();
            GenerationHandling.showGeneralImageSettingsModal(data);
        });
    }
}

export default new Flux();
