define([
    "TYPO3/CMS/Backend/Severity",
    "TYPO3/CMS/Backend/MultiStepWizard",
    'TYPO3/CMS/AiSuite/Helper/Ajax',
    'TYPO3/CMS/AiSuite/Helper/Image/GenerationHandling',
    'TYPO3/CMS/AiSuite/Helper/Image/SaveHandling',
    'TYPO3/CMS/AiSuite/Helper/Image/ResponseHandling',
    'TYPO3/CMS/AiSuite/Helper/Image/StatusHandling',
    "TYPO3/CMS/AiSuite/Helper/Generation",
], function(
    Severity,
    MultiStepWizard,
    Ajax,
    GenerationHandling,
    SaveHandling,
    ResponseHandling,
    StatusHandling,
    Generation
) {
    'use strict';

    /**
     * Dalle constructor
     *
     * @constructor
     */
    let Dalle = function() {
        this.intervalId = null;
    };

    /**
     * Add image generation wizard
     *
     * @param {Object} data Configuration data
     * @param {Boolean} filelistScope Whether the function is called in filelist scope
     */
    Dalle.prototype.addImageGenerationWizard = function(data, filelistScope = false) {
        const self = this;

        MultiStepWizard.addSlide(
            'ai-suite-dalle-image-generation-step-1',
            TYPO3.lang['aiSuite.module.modal.imageSelection'],
            '',
            Severity.notice,
            TYPO3.lang['aiSuite.module.modal.dalleSlideOne'],
            function(slide, settings) {
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
                self.generateImage(data)
                    .then((res) => {
                        StatusHandling.stopInterval();
                        ResponseHandling.handleResponse(res, TYPO3.lang['aiSuite.module.modal.dalleSelectionError']);
                        slide.html(settings['generatedData']);
                        self.addSelectionEventListeners(modal, data, slide, filelistScope);
                    })
                    .catch(error => {
                        StatusHandling.stopInterval();
                    });
            }
        );

        MultiStepWizard.show();
    };

    /**
     * Generate image using AJAX
     *
     * @param {Object} data Configuration data
     * @return {Promise} Promise resolving to AJAX response
     */
    Dalle.prototype.generateImage = function(data) {
        return new Promise(async (resolve, reject) => {
            let res = await Ajax.sendAjaxRequest('aisuite_image_generation_slide_two', data);
            resolve(res);
        });
    };

    /**
     * Add selection event listeners
     *
     * @param {Object} modal Modal DOM element
     * @param {Object} data Configuration data
     * @param {Object} slide Slide DOM element
     * @param {Boolean} filelistScope Whether in filelist scope
     */
    Dalle.prototype.addSelectionEventListeners = function(modal, data, slide, filelistScope) {
        this.backToSlideOneButton(modal, data);
        SaveHandling.selectionHandler(modal, 'img.ce-image-selection');
        SaveHandling.selectionHandler(modal, 'label.ce-image-title-selection');

        if(filelistScope) {
            SaveHandling.saveGeneratedImageFileListButton(modal, data, slide);
        } else {
            SaveHandling.saveGeneratedImageButton(modal, data, slide);
        }
    };

    /**
     * Add back to slide one button functionality
     *
     * @param {Object} modal Modal DOM element
     * @param {Object} data Configuration data
     */
    Dalle.prototype.backToSlideOneButton = function(modal, data) {
        let aiSuiteBackToWizardSlideOneBtn = modal.find('.modal-body').find('button#aiSuiteBackToWizardSlideOneBtn');

        aiSuiteBackToWizardSlideOneBtn.on('click', function() {
            MultiStepWizard.set('generatedData', '');
            MultiStepWizard.dismiss();
            GenerationHandling.showGeneralImageSettingsModal(data);
        });
    };

    return new Dalle();
});
