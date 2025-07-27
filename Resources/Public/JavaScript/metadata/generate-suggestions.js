import Notification from "@typo3/backend/notification.js";
import Severity from "@typo3/backend/severity.js";
import MultiStepWizard from "@typo3/backend/multi-step-wizard.js";
import Ajax from "@autodudes/ai-suite/helper/ajax.js";
import Metadata from "@autodudes/ai-suite/helper/metadata.js";
import ResponseHandling from "@autodudes/ai-suite/helper/image/response-handling.js";
import StatusHandling from "@autodudes/ai-suite/helper/image/status-handling.js";
import Generation from "@autodudes/ai-suite/helper/generation.js";

class GenerateSuggestions {
    constructor() {
        this.addEventListener();
        this.intervalId = null;
    }

    addEventListener() {
        let self = this;
        document.querySelectorAll('.ai-suite-suggestions-generation-btn').forEach(function(button) {
            button.addEventListener("click", function(ev) {
                ev.preventDefault();
                let fieldName = this.getAttribute('data-field-name');
                let fieldLabel = this.getAttribute('data-field-label');
                let id = parseInt(this.getAttribute('data-id'));
                let pageId = parseInt(this.getAttribute('data-page-id')) ?? 0;
                let langIsoCode = this.getAttribute('data-lang-iso-code') ?? '';
                let languageId = parseInt(this.getAttribute('data-language-id')) ?? 0;
                let table = this.getAttribute('data-table');
                let sysFileId = this.getAttribute('data-sys-file-id');
                let postData = {
                    id: id,
                    pageId: pageId,
                    languageId: languageId,
                    langIsoCode: langIsoCode,
                    table: table,
                    fieldName: fieldName,
                    fieldLabel: fieldLabel,
                    sysFileId: sysFileId ?? 0,
                };
                self.addMetadataWizard(postData);
            });
        });
    }

    addMetadataWizard(postData) {
        let self = this;
        MultiStepWizard.setup.settings['postData'] = postData;
        MultiStepWizard.addSlide('ai-suite-metadata-generation-step-1', TYPO3.lang['aiSuite.module.modal.metaDataGeneration'], '', Severity.notice, TYPO3.lang['aiSuite.module.modal.metaDataGenerationSlideOne'], async function (slide, settings) {
            let modalContent = MultiStepWizard.setup.$carousel.closest('.t3js-modal');
            if (modalContent !== null) {
                modalContent.addClass('aisuite-modal');
                modalContent.removeClass('modal-size-default');
                modalContent.addClass('modal-size-large');
            }
            MultiStepWizard.blurCancelStep();
            MultiStepWizard.lockNextStep();
            MultiStepWizard.lockPrevStep();
            const res = await Ajax.sendAjaxRequest('aisuite_metadata_generation_slide_one', settings['postData']);
            slide.html(res.output);
            let modal = MultiStepWizard.setup.$carousel.closest('.modal');
            let aiSuiteGenerateButton = modal.find('.panel-body button#aiSuiteGenerateMetadataBtn');
            let postData = settings['postData'];
            aiSuiteGenerateButton.on('click', async function (ev) {
                let textAiModel = modal.find('.panel-body input[name="libraries[textGenerationLibrary]"]:checked').val() ?? '';
                let newsDetailPlugin = modal.find('.panel-body select#newsDetailPlugin');
                let sysLanguageSelection = modal.find('.panel-body #languageSelection select');
                if(modal.find('.panel-body select#newsDetailPlugin') && newsDetailPlugin.val() === '') {
                    Notification.warning(TYPO3.lang['AiSuite.notification.generation.newsDetailPlugin.missingSelection'], TYPO3.lang['AiSuite.notification.generation.newsDetailPlugin.missingSelectionInfo'], 8);
                    return;
                }
                postData.uuid = ev.target.getAttribute('data-uuid');
                postData.textAiModel = textAiModel;
                postData.newsDetailPlugin = newsDetailPlugin.val();
                if(sysLanguageSelection.length > 0) {
                    postData.langIsoCode = sysLanguageSelection.val();
                }
                settings['postData'] = postData;
                MultiStepWizard.unlockNextStep().trigger('click');
            });
        });
        MultiStepWizard.addSlide('ai-suite-metadata-generation-step-2', TYPO3.lang['aiSuite.module.modal.metaDataGeneration'], '', Severity.notice, TYPO3.lang['aiSuite.module.modal.metaDataGenerationSlideTwo'], async function (slide, settings) {
            let modalContent = MultiStepWizard.setup.$carousel.closest('.t3js-modal');
            if (modalContent !== null) {
                modalContent.addClass('aisuite-modal');
                modalContent.removeClass('modal-size-default');
                modalContent.addClass('modal-size-large');
            }
            MultiStepWizard.blurCancelStep();
            MultiStepWizard.lockNextStep();
            MultiStepWizard.lockPrevStep();
            slide.html(Generation.showSpinnerModal(TYPO3.lang['aiSuite.module.modal.metaDataGenerationInProcess'], 665));

            let modal = MultiStepWizard.setup.$carousel.closest('.modal');
            modal.find('.spinner-wrapper').css('overflow', 'hidden');
            StatusHandling.fetchStatus(settings['postData'], modal, self)
            self.generateMetaData(settings['postData'])
                .then((res) => {
                    clearInterval(self.intervalId);
                    ResponseHandling.handleResponse(res, TYPO3.lang['aiSuite.module.modal.metaDataError']);
                    slide.html(settings['generatedData']);
                    modal = MultiStepWizard.setup.$carousel.closest('.modal');
                    Metadata.addSelectionEventListeners(modal, settings['postData'], slide);
                })
                .catch(error => {
                    clearInterval(self.intervalId);
                });
        });
        MultiStepWizard.show();
    }

    generateMetaData(data) {
        return new Promise(async (resolve, reject) => {
            let res = await Ajax.sendAjaxRequest('aisuite_metadata_generation_slide_two', data);
            resolve(res);
        });
    }
}

export default new GenerateSuggestions();
