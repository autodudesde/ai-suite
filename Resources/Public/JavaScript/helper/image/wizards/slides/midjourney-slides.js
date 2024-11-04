import Notification from "@typo3/backend/notification.js";
import Severity from "@typo3/backend/severity.js";
import MultiStepWizard from "@typo3/backend/multi-step-wizard.js";
import Ajax from "@autodudes/ai-suite/helper/ajax.js";
import GenerationHandling from "@autodudes/ai-suite/helper/image/generation-handling.js";
import ResponseHandling from "@autodudes/ai-suite/helper/image/response-handling.js";
import StatusHandling from "@autodudes/ai-suite/helper/image/status-handling.js";
import Generation from "@autodudes/ai-suite/helper/generation.js";

class MidjourneySlides {
    constructor() {
        this.intervalId = null;
    }
    slideOne(data) {
        let self = this;
        MultiStepWizard.addSlide('ai-suite-midjourney-image-generation-step-1', TYPO3.lang['aiSuite.module.modal.imagePreSelection'], '', Severity.notice, TYPO3.lang['aiSuite.module.modal.midjourneySlideOne'], async function (slide, settings) {
            let modalContent = MultiStepWizard.setup.$carousel.closest('.t3js-modal');
            if (modalContent !== null) {
                modalContent.addClass('aisuite-modal');
                modalContent.removeClass('modal-size-default');
                modalContent.addClass('modal-size-large');
            }
            MultiStepWizard.blurCancelStep();
            MultiStepWizard.lockNextStep();
            MultiStepWizard.lockPrevStep();
            slide.html(Generation.showSpinnerModal(TYPO3.lang['aiSuite.module.modal.imagePreSelectionGenerationInProcessMidjourney'], 677));
            let modal = MultiStepWizard.setup.$carousel.closest('.modal');
            modal.find('.spinner-wrapper').css('overflow', 'hidden');
            Promise.all([self.generatePreSelection(data), StatusHandling.fetchStatus(data, modal, self)])
                .then(([res, status]) => {
                    clearInterval(self.intervalId);
                    ResponseHandling.handleResponse(res, TYPO3.lang['aiSuite.module.modal.midjourneyPreSelectionError']);
                    slide.html(settings['generatedData']);
                    self.addPreSelectionEventListeners(modal, data, slide, self);
                })
                .catch(error => {
                    clearInterval(self.intervalId);
                });
        });
    }

    generatePreSelection(data) {
        return new Promise(async (resolve, reject) => {
            let res = await Ajax.sendAjaxRequest('aisuite_image_generation_slide_two', data);
            resolve(res);
        });
    }

    slideTwo(data, filelistScope, addSelectionEventListenersFn) {
        let self = this;
        MultiStepWizard.addSlide('ai-suite-midjourney-image-generation-step-2', TYPO3.lang['aiSuite.module.modal.imageSelection'], '', Severity.notice, TYPO3.lang['aiSuite.module.modal.midjourneySlideTwo'], async function(slide, settings) {
            MultiStepWizard.blurCancelStep();
            MultiStepWizard.lockNextStep();
            MultiStepWizard.unlockPrevStep();
            let modal = MultiStepWizard.setup.$carousel.closest('.modal');
            slide.html(Generation.showSpinnerModal(TYPO3.lang['aiSuite.module.modal.imageGenerationInProcessMidjourney'], 677));
            data = settings['data'];
            Promise.all([self.generateImage(data), StatusHandling.fetchStatus(data, modal, self)])
                .then(([res, status]) => {
                    clearInterval(self.intervalId);
                    ResponseHandling.handleResponse(res, TYPO3.lang['aiSuite.module.modal.midjourneySelectionError']);
                    slide.html(settings['generatedData']);
                    addSelectionEventListenersFn(modal, data, slide, filelistScope, self);
                })
                .catch(error => {
                    clearInterval(self.intervalId);
                });
        });
    }

    generateImage(data) {
        return new Promise(async (resolve, reject) => {
            let res = await Ajax.sendAjaxRequest('aisuite_image_generation_slide_three', data);
            resolve(res);
        });
    }
    generateImageContentElement(data) {
        return new Promise(async (resolve, reject) => {
            let res = await Ajax.sendAjaxRequest('aisuite_regenerate_images', data);
            resolve(res);
        });
    }

    slideTwoContentElement(data) {
        let self = this;
        MultiStepWizard.addSlide('ai-suite-midjourney-image-generation-step-2', TYPO3.lang['aiSuite.module.modal.imageSelection'], '', Severity.notice, TYPO3.lang['aiSuite.module.modal.midjourneySlideTwo'], async function(slide, settings) {
            MultiStepWizard.blurCancelStep();
            MultiStepWizard.lockNextStep();
            MultiStepWizard.unlockPrevStep();
            slide.html(Generation.showSpinnerModal(TYPO3.lang['aiSuite.module.modal.imageGenerationInProcessMidjourney'], 677));
            let modal = MultiStepWizard.setup.$carousel.closest('.modal');
            modal.find('.spinner-wrapper').css('overflow', 'hidden');
            data = settings['data'];
            Promise.all([self.generateImageContentElement(data), StatusHandling.fetchStatus(data, modal, self)])
                .then(([res, status]) => {
                    clearInterval(self.intervalId);
                    ResponseHandling.handleResponseContentElement(res, data, TYPO3.lang['aiSuite.module.modal.midjourneySelectionError']);
                })
                .catch(error => {
                    clearInterval(self.intervalId);
                });
        });
    }

    addPreSelectionEventListeners(modal, data, slide, self) {
        self.backToSlideOneButton(modal, data);
        self.selectionImage(modal, data, slide, self);
    }

    selectionImage(modal, data) {
        let componentButtons = modal.find('.modal-body').find('.image-preselection .component-button');
        if (componentButtons.length > 0) {
            componentButtons.on('click', function(ev) {
                ev.preventDefault();
                data.customId = ev.target.getAttribute('data-custom-id');
                data.mId = ev.target.getAttribute('data-m-id');
                data.index = ev.target.getAttribute('data-index');
                data.imagePrompt = ev.target.getAttribute('data-prompt');
                MultiStepWizard.set('data', data);
                MultiStepWizard.unlockNextStep().trigger('click');
            });
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

export default new MidjourneySlides();
