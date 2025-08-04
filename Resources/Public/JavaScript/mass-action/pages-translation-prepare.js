import General from "@autodudes/ai-suite/helper/general.js";
import Generation from "@autodudes/ai-suite/helper/generation.js";
import Ajax from '@autodudes/ai-suite/helper/ajax.js';
import Notification from "@typo3/backend/notification.js";
import InfoWindow from "@typo3/backend/info-window.js";


class PagesTranslationPrepare {
    parentUuid;
    constructor() {
        this.pagesTranslationPrepareExecuteFormEventListener();
        Generation.cancelGeneration();
        this.pageTranslationSelectionEventDelegation();
        this.parentUuid = '';
        this.init();
    }

    init() {
        let startFromPidInput = document.querySelector('input[name="massActionPagesTranslationPrepare[startFromPid]"]');
        if (!General.isUsable(startFromPidInput) || !General.isUsable(startFromPidInput.value) || startFromPidInput.value === '' || startFromPidInput.value === '0') {
            return;
        }
        let pagesTranslationPrepareExecuteForm = document.querySelector('form[name="pagesTranslationPrepareExecute"]');
        const formData = new FormData(pagesTranslationPrepareExecuteForm);
        this.preparePagesTranslation(formData).then(() => {});
    }

    pagesTranslationPrepareExecuteFormEventListener() {
        const self = this;
        let pagesTranslationPrepareExecuteForm = document.querySelector('form[name="pagesTranslationPrepareExecute"]');
        pagesTranslationPrepareExecuteForm.addEventListener('submit', async function(ev) {
            ev.preventDefault();
            let startFromPidInput = document.querySelector('input[name="massActionPagesTranslationPrepare[startFromPid]"]');
            let sourceLanguageSelect = document.querySelector('select[name="massActionPagesTranslationPrepare[sourceLanguage]"]');
            let targetLanguageSelect = document.querySelector('select[name="massActionPagesTranslationPrepare[targetLanguage]"]');

            if (!General.isUsable(startFromPidInput) || !General.isUsable(startFromPidInput.value) || startFromPidInput.value === '') {
                Notification.warning(TYPO3.lang['AiSuite.notification.generation.massAction.missingSelection'], TYPO3.lang['AiSuite.notification.generation.massAction.missingStartFromPid']);
                return;
            }

            if (sourceLanguageSelect.value === targetLanguageSelect.value) {
                Notification.warning(TYPO3.lang['AiSuite.notification.generation.translation.error'], TYPO3.lang['AiSuite.notification.generation.translation.sameLanguage']);
                return;
            }

            Generation.showSpinner();
            const formData = new FormData(pagesTranslationPrepareExecuteForm);
            await self.preparePagesTranslation(formData);
            Generation.hideSpinner();
        });
    }

    pageTranslationSelectionEventDelegation() {
        const self = this;
        document.querySelectorAll('#resultsToExecute').forEach(function(element) {
            element.addEventListener('click', async function(ev) {
                if(ev && ev.target) {
                    if(ev.target.nodeName === 'INPUT' && ev.target.type === 'checkbox' && ev.target.id === 'togglePageTranslationSelection') {
                        let checkboxes = document.querySelectorAll('input[name="page-translation-selection"]');
                        checkboxes.forEach(function(checkbox) {
                            checkbox.checked = ev.target.checked;
                        });
                        self.calculateRequestAmount();
                    }
                    if(ev.target.nodeName === 'INPUT' && ev.target.type === 'checkbox' && ev.target.name === 'page-translation-selection') {
                        self.calculateRequestAmount();
                    }
                    if(ev.target.nodeName === 'DIV' && ev.target.classList.contains('page-translation-content-info')) {
                        const table = ev.target.dataset.table;
                        const uid = ev.target.dataset.uid;
                        InfoWindow.showItem(table, uid);
                    }
                    if(ev.target.nodeName === 'BUTTON' && ev.target.classList.contains('page-translation-content-info')) {
                        const table = ev.target.dataset.table;
                        const uid = ev.target.dataset.uid;
                        InfoWindow.showItem(table, uid);
                    }
                    if(ev.target.nodeName === 'BUTTON' && ev.target.type === 'submit' && ev.target.id === 'pagesTranslationExecuteFormSubmitBtn') {
                        ev.preventDefault();
                        let checkboxes = document.querySelectorAll('input[name="page-translation-selection"]');
                        let selectedPages = {};
                        checkboxes.forEach(function(checkbox) {
                            if(checkbox.checked) {
                                selectedPages[checkbox.value] = {
                                    title: checkbox.dataset.title,
                                    slug: checkbox.dataset.slug
                                };
                            }
                        });
                        if(Object.keys(selectedPages).length === 0) {
                            Notification.warning(TYPO3.lang['AiSuite.notification.generation.massAction.missingSelection'], TYPO3.lang['AiSuite.notification.generation.massAction.missingPages']);
                        } else {
                            let counter = 0;
                            let currentPages = {};
                            let handledPages = {};
                            Generation.showSpinner();
                            let formData = new FormData();
                            formData.append('massActionPagesTranslationExecute[parentUuid]', self.parentUuid);
                            formData.append('massActionPagesTranslationExecute[sourceLanguage]', document.querySelector('select[name="massActionPagesTranslationPrepare[sourceLanguage]"]').value);
                            formData.append('massActionPagesTranslationExecute[targetLanguage]', document.querySelector('select[name="massActionPagesTranslationPrepare[targetLanguage]"]').value);
                            formData.append('massActionPagesTranslationExecute[translationScope]', document.querySelector('select[name="massActionPagesTranslationPrepare[translationScope]"]').value);
                            formData.append('massActionPagesTranslationExecute[textAiModel]', document.querySelector('.text-generation-library input[type="radio"]:checked').value);

                            for (let key in selectedPages) {
                                if(counter === 3) { // Smaller batches for translation
                                    try {
                                        handledPages = { ...handledPages, ...currentPages };
                                        formData.append('massActionPagesTranslationExecute[pages]', JSON.stringify(currentPages));
                                        await self.sendPagesTranslationToExecute(formData, selectedPages, handledPages);
                                        counter = 0;
                                        currentPages = {};
                                    } catch (e) {
                                        console.error(e);
                                    }
                                }
                                currentPages[key] = selectedPages[key];
                                counter++;
                            }
                            if(Object.keys(currentPages).length > 0) {
                                formData.append('massActionPagesTranslationExecute[pages]', JSON.stringify(currentPages));
                                await self.sendPagesTranslationToExecute(formData, selectedPages, handledPages);
                            }
                            Generation.hideSpinner();
                            Notification.success(TYPO3.lang['AiSuite.notification.generation.massAction.success'], TYPO3.lang['AiSuite.notification.generation.translation.successDescription']);

                            setTimeout(() => {
                                top.document.dispatchEvent(new CustomEvent("typo3:pagetree:refresh"));
                            }, 100);

                            let pagesTranslationPrepareExecuteForm = document.querySelector('form[name="pagesTranslationPrepareExecute"]');
                            formData = new FormData(pagesTranslationPrepareExecuteForm);
                            self.preparePagesTranslation(formData).then(() => {});
                        }
                    }
                }
            });
        });
    }

    async preparePagesTranslation(formData) {
        let res = await Ajax.sendAjaxRequest('aisuite_massaction_pages_translation_prepare', formData);
        if (General.isUsable(res)) {
            if(General.isUsable(res.output) && !General.isUsable(res.output.content)) {
                document.querySelector('#resultsToExecute').innerHTML = res.output;
            } else {
                this.parentUuid = res.output.parentUuid;
                document.querySelector('#resultsToExecute').innerHTML = res.output.content;
                const sourceLanguageSelect = document.querySelector('select[name="massActionPagesTranslationPrepare[sourceLanguage]"]');
                if (sourceLanguageSelect && General.isUsable(res.output.availableSourceLanguages)) {
                    sourceLanguageSelect.innerHTML = '';
                    Object.entries(res.output.availableSourceLanguages).forEach(([identifier, label]) => {
                        const option = document.createElement('option');
                        option.value = identifier;
                        option.textContent = label;
                        sourceLanguageSelect.appendChild(option);
                    });
                }
                if(General.isUsable(res.output.notificationSourceLanguage) && res.output.notificationSourceLanguage !== '') {
                    Notification.info(TYPO3.lang['AiSuite.notification.sysLanguage.pageTreeChanged'], res.output.notificationSourceLanguage);
                }
                const targetLanguageSelect = document.querySelector('select[name="massActionPagesTranslationPrepare[targetLanguage]"]');
                if (targetLanguageSelect && General.isUsable(res.output.availableTargetLanguages)) {
                    targetLanguageSelect.innerHTML = '';
                    Object.entries(res.output.availableTargetLanguages).forEach(([identifier, label]) => {
                        const option = document.createElement('option');
                        option.value = identifier;
                        option.textContent = label;
                        targetLanguageSelect.appendChild(option);
                    });
                }
                if(General.isUsable(res.output.notificationTargetLanguage) && res.output.notificationTargetLanguage !== '') {
                    Notification.info(TYPO3.lang['AiSuite.notification.sysLanguage.pageTreeChanged'], res.output.notificationTargetLanguage);
                }
            }
        } else {
            Notification.error(TYPO3.lang['AiSuite.notification.generation.error'], TYPO3.lang['AiSuite.notification.generation.requestError']);
        }
    }

    async sendPagesTranslationToExecute(formData, selectedPages, handledPages) {
        let res = await Ajax.sendAjaxRequest('aisuite_massaction_pages_translation_execute', formData);
        if (General.isUsable(res)) {
            if(res.output.failedPages.length > 0) {
                Notification.error(TYPO3.lang['AiSuite.notification.generation.error'], TYPO3.lang['AiSuite.notification.generation.failedPages'] + res.output.failedPages.join(', '));
            }
            let statusElement = document.querySelector('.module-body .spinner-overlay .status');
            if (statusElement !== null) {
                statusElement.innerHTML = res.output.message + Object.keys(handledPages).length + ' / ' + Object.keys(selectedPages).length;
            }
        } else {
            Notification.error(TYPO3.lang['AiSuite.notification.generation.error'], TYPO3.lang['AiSuite.notification.generation.requestError']);
        }
    }


    calculateRequestAmount() {
        let calculatedRequests = 0;
        document.querySelectorAll('.library').forEach(function (library) {
            let amountField = library.querySelector('.request-amount span');
            if(library.style.display !== 'none' && amountField !== null) {
                let modelId = library.querySelector('input[type="radio"]:checked').id;
                let amount = parseInt(library.querySelector('label[for="' + modelId +'"] .request-amount span').textContent);
                calculatedRequests += amount;
            }
        });
        let checkboxes = document.querySelectorAll('input[name="page-translation-selection"]');
        let selectedPages = 0;
        checkboxes.forEach(function(checkbox) {
            if(checkbox.checked) {
                selectedPages++;
            }
        });
        calculatedRequests *= selectedPages;
        let marker = TYPO3.lang['aiSuite.module.multipleCredits'];
        if(calculatedRequests === 1) {
            marker = TYPO3.lang['aiSuite.module.oneCredit'];
        }
        document.querySelector('div[data-module-id="aiSuite"] .calculated-requests').textContent = '(' + calculatedRequests + ' ' + marker + ')';
    }
}
export default new PagesTranslationPrepare();
