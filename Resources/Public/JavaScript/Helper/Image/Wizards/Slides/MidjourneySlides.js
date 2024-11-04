define([
    "TYPO3/CMS/Backend/Notification",
    "TYPO3/CMS/Backend/Severity",
    "TYPO3/CMS/Backend/MultiStepWizard",
    "TYPO3/CMS/AiSuite/Helper/Ajax",
    "TYPO3/CMS/AiSuite/Helper/Image/ResponseHandling",
    "TYPO3/CMS/AiSuite/Helper/Image/StatusHandling",
    "TYPO3/CMS/AiSuite/Helper/Generation",
], function(
    Notification,
    Severity,
    MultiStepWizard,
    Ajax,
    ResponseHandling,
    StatusHandling,
    Generation
) {
    let intervalId = null;

    function slideOne(data, showGeneralImageSettingsModal) {
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
            slide.html(Generation.showSpinnerModal(TYPO3.lang['aiSuite.module.modal.imagePreSelectionGenerationInProcessMidjourney'], 667));
            let modal = MultiStepWizard.setup.$carousel.closest('.modal');
            modal.find('.spinner-wrapper').css('overflow', 'hidden');
            Promise.all([generatePreSelection(data), StatusHandling.fetchStatus(data, modal, intervalId)])
                .then(([res, status]) => {
                    StatusHandling.stopInterval();
                    ResponseHandling.handleResponse(res, TYPO3.lang['aiSuite.module.modal.midjourneyPreSelectionError']);
                    slide.html(settings['generatedData']);
                    addPreSelectionEventListeners(modal, data, slide, showGeneralImageSettingsModal);
                })
                .catch(error => {
                    StatusHandling.stopInterval();
                });
        });
    }

    function generatePreSelection(data) {
        return new Promise(async (resolve, reject) => {
            let res = await Ajax.sendAjaxRequest('aisuite_image_generation_slide_two', data);
            resolve(res);
        });
    }

    function slideTwo(data, showSpinner, addSelectionEventListeners, filelistScope) {
        MultiStepWizard.addSlide('ai-suite-midjourney-image-generation-step-2', TYPO3.lang['aiSuite.module.modal.imageSelection'], '', Severity.notice, TYPO3.lang['aiSuite.module.modal.midjourneySlideTwo'], async function(slide, settings) {
            MultiStepWizard.blurCancelStep();
            MultiStepWizard.lockNextStep();
            MultiStepWizard.unlockPrevStep();
            let modal = MultiStepWizard.setup.$carousel.closest('.modal');
            slide.html(Generation.showSpinnerModal(TYPO3.lang['aiSuite.module.modal.imageGenerationInProcessMidjourney'], 667));
            data = settings['data'];
            Promise.all([generateImage(data), StatusHandling.fetchStatus(data, modal)])
                .then(([res, status]) => {
                    StatusHandling.stopInterval();
                    ResponseHandling.handleResponse(res, TYPO3.lang['aiSuite.module.modal.midjourneySelectionError']);
                    slide.html(settings['generatedData']);
                    addSelectionEventListeners(modal, data, slide, showSpinner, filelistScope);
                })
                .catch(error => {
                    StatusHandling.stopInterval();
                });
        });
    }

    function generateImage(data) {
        return new Promise(async (resolve, reject) => {
            let res = await Ajax.sendAjaxRequest('aisuite_image_generation_slide_three', data);
            resolve(res);
        });
    }
    function generateImageContentElement(data) {
        return new Promise(async (resolve, reject) => {
            let res = await Ajax.sendAjaxRequest('aisuite_regenerate_images', data);
            resolve(res);
        });
    }

    function slideTwoContentElement(data, showSpinner) {
        MultiStepWizard.addSlide('ai-suite-midjourney-image-generation-step-2', TYPO3.lang['aiSuite.module.modal.imageSelection'], '', Severity.notice, TYPO3.lang['aiSuite.module.modal.midjourneySlideTwo'], async function(slide, settings) {
            MultiStepWizard.blurCancelStep();
            MultiStepWizard.lockNextStep();
            MultiStepWizard.unlockPrevStep();
            slide.html(Generation.showSpinnerModal(TYPO3.lang['aiSuite.module.modal.imageGenerationInProcessMidjourney'], 667));
            let modal = MultiStepWizard.setup.$carousel.closest('.modal');
            modal.find('.spinner-wrapper').css('overflow', 'hidden');
            data = settings['data'];
            Promise.all([generateImageContentElement(data), StatusHandling.fetchStatus(data, modal)])
                .then(([res, status]) => {
                    StatusHandling.stopInterval();
                    ResponseHandling.handleResponseContentElement(res, data, TYPO3.lang['aiSuite.module.modal.midjourneySelectionError']);
                    MultiStepWizard.dismiss();
                })
                .catch(error => {
                    StatusHandling.stopInterval();
                    MultiStepWizard.dismiss();
                });
        });
    }

    function addPreSelectionEventListeners(modal, data, slide, showGeneralImageSettingsModal) {
        backToSlideOneButton(modal, data, showGeneralImageSettingsModal);
        selectionImage(modal, data, slide);
    }

    function selectionImage(modal, data) {
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

    function backToSlideOneButton(modal, data, showGeneralImageSettingsModal) {
        let aiSuiteBackToWizardSlideOneBtn = modal.find('.modal-body').find('button#aiSuiteBackToWizardSlideOneBtn');
        aiSuiteBackToWizardSlideOneBtn.on('click', function() {
            MultiStepWizard.set('generatedData', '');
            MultiStepWizard.dismiss();
            showGeneralImageSettingsModal(data);
        });
    }
    return {
        slideOne: slideOne,
        slideTwo: slideTwo,
        slideTwoContentElement: slideTwoContentElement
    };
});
