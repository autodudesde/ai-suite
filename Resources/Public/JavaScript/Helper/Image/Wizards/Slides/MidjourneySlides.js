define([
    "TYPO3/CMS/Backend/Notification",
    "TYPO3/CMS/Backend/Severity",
    "TYPO3/CMS/Backend/MultiStepWizard",
    'TYPO3/CMS/AiSuite/Helper/Ajax',
    'TYPO3/CMS/AiSuite/Helper/Image/GenerationHandling',
    'TYPO3/CMS/AiSuite/Helper/Image/ResponseHandling',
    'TYPO3/CMS/AiSuite/Helper/Image/StatusHandling',
    'TYPO3/CMS/AiSuite/Helper/Generation',
    "TYPO3/CMS/AiSuite/Helper/Image/SaveHandling"
], function(
    Notification,
    Severity,
    MultiStepWizard,
    Ajax,
    GenerationHandling,
    ResponseHandling,
    StatusHandling,
    Generation,
    SaveHandling
) {
    'use strict';

    let MidjourneySlides = function() {
        this.intervalId = null;
    };

    MidjourneySlides.prototype.slideOne = function(data) {
        const self = this;
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
    };

    MidjourneySlides.prototype.generatePreSelection = function(data) {
        return new Promise(async (resolve, reject) => {
            let res = await Ajax.sendAjaxRequest('aisuite_image_generation_slide_two', data);
            resolve(res);
        });
    };

    MidjourneySlides.prototype.slideTwo = function(data, filelistScope) {
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
                    self.addSelectionEventListeners(modal, data, slide, filelistScope);
                })
                .catch(error => {
                    clearInterval(self.intervalId);
                });
        });
    };

    MidjourneySlides.prototype.generateImage = function(data) {
        return new Promise(async (resolve, reject) => {
            let res = await Ajax.sendAjaxRequest('aisuite_image_generation_slide_three', data);
            resolve(res);
        });
    };

    MidjourneySlides.prototype.generateImageContentElement = function(data) {
        return new Promise(async (resolve, reject) => {
            let res = await Ajax.sendAjaxRequest('aisuite_regenerate_images', data);
            resolve(res);
        });
    };

    MidjourneySlides.prototype.slideTwoContentElement = function(data) {
        const self = this;
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
                    MultiStepWizard.dismiss();
                    ResponseHandling.handleResponseContentElement(res, data, TYPO3.lang['aiSuite.module.modal.midjourneySelectionError']);
                })
                .catch(error => {
                    clearInterval(self.intervalId);
                    MultiStepWizard.dismiss();
                });
        });
    };

    MidjourneySlides.prototype.addPreSelectionEventListeners = function(modal, data, slide, self) {
        self.backToSlideOneButton(modal, data);
        self.selectionImage(modal, data, slide, self);
    };

    MidjourneySlides.prototype.addSelectionEventListeners = function(modal, data, slide, filelistScope) {
        SaveHandling.backToSlideOneButton(modal, data);
        SaveHandling.selectionHandler(modal, 'img.ce-image-selection');
        SaveHandling.selectionHandler(modal, 'label.ce-image-title-selection');
        if(filelistScope) {
            SaveHandling.saveGeneratedImageFileListButton(modal, data, slide);
        } else {
            SaveHandling.saveGeneratedImageButton(modal, data, slide);
        }
    }

    MidjourneySlides.prototype.selectionImage = function(modal, data) {
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
    };

    MidjourneySlides.prototype.backToSlideOneButton = function(modal, data) {
        let aiSuiteBackToWizardSlideOneBtn = modal.find('.modal-body').find('button#aiSuiteBackToWizardSlideOneBtn');
        aiSuiteBackToWizardSlideOneBtn.on('click', function() {
            MultiStepWizard.set('generatedData', '');
            MultiStepWizard.dismiss();
            GenerationHandling.showGeneralImageSettingsModal(data);
        });
    };

    return new MidjourneySlides();
});
