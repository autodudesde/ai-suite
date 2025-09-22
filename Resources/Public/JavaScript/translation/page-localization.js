/**
 * Module: @autodudes/ai-suite/translation/page-localization
 */
import DocumentService from "@typo3/core/document-service.js";
import Modal from '@typo3/backend/modal.js';
import MultiStepWizard from '@typo3/backend/multi-step-wizard.js';
import Severity from '@typo3/backend/severity.js';
import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import Ajax from '@autodudes/ai-suite/helper/ajax.js';
import General from "@autodudes/ai-suite/helper/general.js";
import Notification from "@typo3/backend/notification.js";
import Generation from "@autodudes/ai-suite/helper/generation.js";

class PageLocalization {
    constructor() {
        this.currentPageId = null;
        this.currentUuid = null;
        this.selectedSourceLanguageId = null;
        this.selectedTargetLanguageId = null;
        this.selectedTranslationServiceId = null;
        DocumentService.ready().then(() => {
            this.initialize();
        });
    }

    initialize() {
        const translateWholePageButton = document.querySelector('#aiSuiteTranslateWholePage');
        if (translateWholePageButton) {
            const self = this;
            translateWholePageButton.addEventListener('click', (event) => {
                event.preventDefault();
                const pageId = parseInt(translateWholePageButton.getAttribute('data-page-id'));
                if (!isNaN(pageId)) {
                    self.showWholePageTranslationWizard(pageId);
                } else {
                    Modal.show(TYPO3.lang['tx_aisuite.js.error.general'], TYPO3.lang['tx_aisuite.js.error.invalidPageId'], Severity.error);
                }
            });
        }

        this.addDeleteEventListener();
        this.addRetryEventListener();

        if (top.TYPO3?.Backend?.aiSuiteWholePageTranslationWizard?.shouldOpen) {
            const pageId = top.TYPO3.Backend.aiSuiteWholePageTranslationWizard.pageId;
            top.TYPO3.Backend.aiSuiteWholePageTranslationWizard.shouldOpen = false;
            if (!isNaN(pageId)) {
                this.showWholePageTranslationWizard(pageId);
            }
        }
    }

    showWholePageTranslationWizard(pageId) {
        this.currentPageId = pageId;
        this.slideOne();
        this.slideTwo();
        this.slideThree();
        MultiStepWizard.show();
    }

    slideOne() {
        const self = this;
        MultiStepWizard.addSlide(
            'ai-suite-whole-page-translation-step-1',
            TYPO3.lang['tx_aisuite.js.wizard.selectLanguages'] ?? 'Select Languages',
            '',
            Severity.info,
            TYPO3.lang['tx_aisuite.js.wizard.languageSelection'] ?? 'Language Selection',
            async function(slide) {
                MultiStepWizard.blurCancelStep();
                MultiStepWizard.lockNextStep();
                MultiStepWizard.lockPrevStep();

                const response = await Ajax.sendAjaxRequest('aisuite_translation_wizard_slide_one', { pageId: self.currentPageId });
                if (response && response.output) {
                    slide.html(response.output);
                    self.currentUuid = response.uuid;

                    self.addLanguageSelectionEventListeners();

                } else {
                    slide.html('<div class="alert alert-danger">' + TYPO3.lang['tx_aisuite.js.error.loadWizardContent'] + '</div>');
                }
            }
        );
    }

    slideTwo() {
        const self = this;
        MultiStepWizard.addSlide(
            'ai-suite-whole-page-translation-step-2',
            TYPO3.lang['tx_aisuite.js.wizard.translationSummary'] ?? 'Translation Summary',
            '',
            Severity.info,
            TYPO3.lang['tx_aisuite.js.wizard.translationSummary'] ?? 'Translation Summary',
            async function(slide) {
                MultiStepWizard.blurCancelStep();
                MultiStepWizard.lockNextStep();
                MultiStepWizard.unlockPrevStep();

                const content = `
                    <div class="ai-suite-whole-page-translation-wizard">
                        <div id="translation-summary-container">
                            <div class="alert alert-info">
                                <div id="translation-summary-content">
                                    ` + Generation.showSpinnerModal(TYPO3.lang['tx_aisuite.js.loading.translationSummary'], 350) + `
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                slide.html(content);
                await self.getTranslationSummaryForWizard();
            }
        );
    }

    slideThree() {
        const self = this;
        MultiStepWizard.addSlide(
            'ai-suite-whole-page-translation-step-3',
            TYPO3.lang['tx_aisuite.js.wizard.translationProgress'] ?? 'Translation Progress',
            '',
            Severity.info,
            TYPO3.lang['tx_aisuite.js.wizard.translationInProgress'] ?? 'Translation in Progress',
            async function(slide) {
                MultiStepWizard.blurCancelStep();
                MultiStepWizard.lockNextStep();
                MultiStepWizard.lockPrevStep();

                slide.html(Generation.showSpinnerModal(TYPO3.lang['tx_aisuite.js.progress.translatingWholePage'] + ' ' + TYPO3.lang['tx_aisuite.js.progress.mayTakeMoments']));

                if (self.selectedSourceLanguageId && self.selectedTargetLanguageId && self.selectedTranslationServiceId) {
                    await self.startWholePageTranslation();
                    MultiStepWizard.dismiss();
                    document.location.reload();
                }
            }
        );
    }

    addLanguageSelectionEventListeners() {
        const self = this;
        const modal = MultiStepWizard.setup.$carousel.closest('.t3js-modal');
        const sourceLanguageSelect = modal.find('#source-language');
        const targetLanguageSelect = modal.find('#target-language');
        const translationServiceSelect = modal.find('#translation-service');
        self.selectedSourceLanguageId = sourceLanguageSelect ? sourceLanguageSelect.val() : null;
        self.selectedTargetLanguageId = targetLanguageSelect ? targetLanguageSelect.val() : null;
        self.selectedTranslationServiceId = translationServiceSelect ? translationServiceSelect.val() : null;

        if (targetLanguageSelect && sourceLanguageSelect && translationServiceSelect) {
            targetLanguageSelect.on('change', () => {
                if (targetLanguageSelect.val()) {
                    self.selectedTargetLanguageId = targetLanguageSelect.val();
                    if(sourceLanguageSelect.val() !== targetLanguageSelect.val()) {
                        MultiStepWizard.unlockNextStep();
                    } else {
                        MultiStepWizard.lockNextStep();
                        Modal.show(TYPO3.lang['tx_aisuite.js.modal.warning'], TYPO3.lang['tx_aisuite.js.validation.sourceTargetSame'], Severity.warning);
                    }
                } else {
                    MultiStepWizard.lockNextStep();
                }
            });
            sourceLanguageSelect.on('change', () => {
                self.selectedSourceLanguageId = sourceLanguageSelect.val();
            });
            translationServiceSelect.on('change', () => {
                self.selectedTranslationServiceId = translationServiceSelect.val();
            });
        }
    }

    async getTranslationSummaryForWizard() {
        const modal = MultiStepWizard.setup.$carousel.closest('.t3js-modal');
        const summaryContent = modal.find('#translation-summary-content');

        if (summaryContent.length) {
            summaryContent.html(Generation.showSpinnerModal(TYPO3.lang['tx_aisuite.js.loading.translationSummary']));
        }
        try {
            const response = await this.getSummary();
            const result = await response.resolve();

            let summaryHtml = '<h3>' + TYPO3.lang['tx_aisuite.js.summary.wholePageTranslation'] + '</h3>';
            let totalElements = 0;

            summaryHtml += '<p>';

            if(result && result.pageMetadata && result.pageMetadata.fields) {
                summaryHtml += '<h4 class="mt-2">' + TYPO3.lang['tx_aisuite.js.summary.pageProperties'] + ' <strong>' + result.pageMetadata.fields.length + ' ' + TYPO3.lang['tx_aisuite.js.summary.fields'] + '</strong></h4>';
            }

            if (result && result.records) {
                Object.keys(result.records).forEach(colPos => {
                    if (result.records[colPos]) {
                        totalElements += result.records[colPos].length;
                    }
                });

                if (totalElements > 0) {
                    summaryHtml += '<h4 class="mt-2">' + TYPO3.lang['tx_aisuite.js.summary.contentElements'] + ' <strong>' + totalElements + ' ' + TYPO3.lang['tx_aisuite.js.summary.elements'] + '</strong></h4>';
                }
            }

            summaryHtml += '</p>';

            if (summaryContent.length) {
                summaryContent.html(summaryHtml);
            }
            MultiStepWizard.unlockNextStep();
        } catch (error) {
            if (summaryContent.length) {
                summaryContent.html('<span class="text-warning">' + TYPO3.lang['tx_aisuite.js.warning.couldNotLoadSummary'] + '</span>');
            }
        }
    }

    getSummary() {
        return new AjaxRequest(TYPO3.settings.ajaxUrls.records_localize_summary).withQueryArguments({
            pageId: parseInt(this.currentPageId),
            destLanguageId: parseInt(this.selectedTargetLanguageId),
            languageId: parseInt(this.selectedSourceLanguageId),
        }).get();
    }

    async startWholePageTranslation() {
        try {
            const response = await this.getSummary();
            const result = await response.resolve();

            let uidList = [];

            if (result && result.records) {
                Object.keys(result.records).forEach(colPos => {
                    if (result.records[colPos]) {
                        result.records[colPos].forEach(record => {
                            uidList.push(record.uid);
                        });
                    }
                });
            }

            if (uidList.length === 0) {
                uidList = [0];
            }

            const action = `localizeWholePage${this.selectedTranslationServiceId}`;
            await this.localizeRecords(action, uidList);
        } catch (error) {
            Modal.show(TYPO3.lang['tx_aisuite.js.error.general'], TYPO3.lang['tx_aisuite.js.error.failedToStartTranslation'], Severity.error);
        }
    }

    localizeRecords(localizationMode, uidList) {
        return new AjaxRequest(TYPO3.settings.ajaxUrls.records_localize).withQueryArguments({
            pageId: parseInt(this.currentPageId),
            srcLanguageId: parseInt(this.selectedSourceLanguageId),
            destLanguageId: parseInt(this.selectedTargetLanguageId),
            action: localizationMode,
            uidList: uidList,
            uuid: this.currentUuid
        }).get();
    }

    addDeleteEventListener() {
        const deleteButton = document.querySelector('#aiSuiteTranslationTaskRemove');
        if (deleteButton) {
            deleteButton.addEventListener('click', (event) => {
                event.preventDefault();
                const uuid = deleteButton.getAttribute('data-uuid');

                Modal.confirm('Warning', TYPO3.lang['AiSuite.backgroundTasks.deleteModalTitle'], Severity.warning, [
                    {
                        text: TYPO3.lang['AiSuite.backgroundTasks.deleteModalText'],
                        active: true,
                        trigger: async () => {
                            const res = await Ajax.sendAjaxRequest('aisuite_background_task_delete', {uuids: [uuid], column: 'translation'});
                            if (General.isUsable(res)) {
                                Notification.success(TYPO3.lang['AiSuite.notification.deleteSuccess']);
                                window.location.reload();
                            }
                            Modal.dismiss();
                        }
                    }, {
                        text: TYPO3.lang['AiSuite.backgroundTasks.deleteAbort'],
                        trigger: () => {
                            Modal.dismiss();
                        }
                    }
                ]);
            });
        }
    }

    addRetryEventListener() {
        const retryButton = document.querySelector('#aiSuiteTranslationTaskRetry');
        if (retryButton) {
            retryButton.addEventListener('click', (event) => {
                event.preventDefault();
                const uuid = retryButton.getAttribute('data-uuid');

                Modal.confirm(
                    TYPO3.lang['AiSuite.errorDetails.title'] || 'Retry Translation',
                    TYPO3.lang['AiSuite.errorDetails.retryQuestion'] || 'Do you want to retry the translation task?',
                    Severity.warning,
                    [
                        {
                            text: TYPO3.lang['AiSuite.errorDetails.retry'] || 'Retry',
                            active: true,
                            btnClass: 'btn-warning',
                            trigger: async () => {
                                Modal.dismiss();
                                Notification.info(
                                    TYPO3.lang['AiSuite.errorDetails.retryingTask'] || 'Retrying task...',
                                    TYPO3.lang['AiSuite.errorDetails.pleaseWait'] || 'Please wait'
                                );

                                const res = await Ajax.sendAjaxRequest('aisuite_background_task_retry', {uuid: uuid, scope: 'translate'});
                                if (General.isUsable(res)) {
                                    Notification.success(TYPO3.lang['AiSuite.errorDetails.retrySuccess'] || 'Task has been queued for retry');
                                    window.location.reload();
                                } else {
                                    Notification.error(
                                        TYPO3.lang['AiSuite.errorDetails.retryFailed'] || 'Retry failed',
                                        res.error || 'An unknown error occurred'
                                    );
                                }
                            }
                        },
                        {
                            text: TYPO3.lang['AiSuite.errorDetails.close'] || 'Cancel',
                            trigger: () => {
                                Modal.dismiss();
                            }
                        }
                    ]
                );
            });
        }
    }
}

export default new PageLocalization;
