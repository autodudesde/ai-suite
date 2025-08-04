import General from "@autodudes/ai-suite/helper/general.js";
import Generation from "@autodudes/ai-suite/helper/generation.js";
import Ajax from '@autodudes/ai-suite/helper/ajax.js';
import Notification from "@typo3/backend/notification.js";
import InfoWindow from "@typo3/backend/info-window.js";

class PagesPrepare {
    parentUuid;
    constructor() {
        this.pagesPrepareExecuteFormEventListener();
        Generation.cancelGeneration();
        this.pageSelectionEventDelegation();
        this.parentUuid = '';
        this.init();
    }

    init() {
        let startFromPidInput = document.querySelector('input[name="massActionPagesPrepare[startFromPid]"]');
        if (!General.isUsable(startFromPidInput) || !General.isUsable(startFromPidInput.value) || startFromPidInput.value === '' || startFromPidInput.value === '0') {
            return;
        }
        let pagesPrepareExecuteForm = document.querySelector('form[name="pagesPrepareExecute"]');
        const formData = new FormData(pagesPrepareExecuteForm);
        this.preparePages(formData).then(() => {});
    }

    pagesPrepareExecuteFormEventListener() {
        const self = this;
        let pagesPrepareExecuteForm = document.querySelector('form[name="pagesPrepareExecute"]');
        pagesPrepareExecuteForm.addEventListener('submit', async function(ev) {
            ev.preventDefault();
            let startFromPidInput = document.querySelector('input[name="massActionPagesPrepare[startFromPid]"]');
            if (!General.isUsable(startFromPidInput) || !General.isUsable(startFromPidInput.value) || startFromPidInput.value === '') {
                Notification.warning(TYPO3.lang['AiSuite.notification.generation.massAction.missingSelection'], TYPO3.lang['AiSuite.notification.generation.massAction.missingStartFromPid']);
                return;
            }
            Generation.showSpinner();
            const formData = new FormData(pagesPrepareExecuteForm);
            await self.preparePages(formData);
            Generation.hideSpinner();
        });
    }
    pageSelectionEventDelegation() {
        const self = this;
        document.querySelectorAll('#resultsToExecute').forEach(function(element) {
            element.addEventListener('click', async function(ev) {
                if(ev && ev.target) {
                    if(ev.target.nodeName === 'INPUT' && ev.target.type === 'checkbox' && ev.target.id === 'togglePageSelection') {
                        let checkboxes = document.querySelectorAll('input[name="page-selection"]');
                        checkboxes.forEach(function(checkbox) {
                            checkbox.checked = ev.target.checked;
                        });
                        self.calculateRequestAmount();
                    }
                    if(ev.target.nodeName === 'INPUT' && ev.target.type === 'checkbox' && ev.target.name === 'page-selection') {
                        self.calculateRequestAmount();
                    }
                    if(ev.target.nodeName === 'INPUT' && ev.target.type === 'text' && ev.target.classList.contains('page-metadata-field')) {
                        if(ev.target.closest('.list-group-item').querySelector('input[name="page-selection"]')) {
                            ev.target.closest('.list-group-item').querySelector('input[name="page-selection"]').checked = true;
                            self.calculateRequestAmount();
                        }
                    }
                    if(ev.target.nodeName === 'DIV' && ev.target.classList.contains('page-meta-content-info')) {
                        const table = ev.target.dataset.table;
                        const uid = ev.target.dataset.uid;
                        InfoWindow.showItem(table, uid);
                    }
                    if(ev.target.nodeName === 'BUTTON' && ev.target.type === 'submit' && ev.target.id === 'pagesSaveMetadataSubmitBtn') {
                        ev.preventDefault();
                        let checkboxes = document.querySelectorAll('input[name="page-selection"]');
                        let selectedPages = {};
                        checkboxes.forEach(function(checkbox) {
                            if(checkbox.checked) {
                                const metadataValue = checkbox.closest('.list-group-item').querySelector('.page-metadata-field').value;
                                selectedPages[checkbox.value] = metadataValue;
                            }
                        });
                        if(Object.keys(selectedPages).length === 0) {
                            Notification.warning(TYPO3.lang['AiSuite.notification.generation.massAction.missingSelection'], TYPO3.lang['AiSuite.notification.generation.massAction.missingPages']);
                        } else {
                            let formData = new FormData();
                            formData.append('massActionPagesExecute[column]', document.querySelector('select[name="massActionPagesPrepare[column]"]').value);
                            formData.append('massActionPagesExecute[sysLanguage]', document.querySelector('select[name="massActionPagesPrepare[sysLanguage]"]').value);
                            formData.append('massActionPagesExecute[pages]', JSON.stringify(selectedPages));
                            await self.sendPagesToUpdate(formData);
                        }
                    }
                    if(ev.target.nodeName === 'BUTTON' && ev.target.type === 'submit' && ev.target.id === 'pagesExecuteFormSubmitBtn') {
                        ev.preventDefault();
                        let checkboxes = document.querySelectorAll('input[name="page-selection"]');
                        let selectedPages = {};
                        checkboxes.forEach(function(checkbox) {
                            if(checkbox.checked) {
                                selectedPages[checkbox.value] = checkbox.dataset.slug;
                            }
                        });
                        if(Object.keys(selectedPages).length === 0) {
                            Notification.warning(TYPO3.lang['AiSuite.notification.generation.massAction.missingSelection'], TYPO3.lang['AiSuite.notification.generation.massAction.missingPages']);
                        } else {
                            let counter = 0;
                            let currentPages = {};
                            let handledPages = {};
                            Generation.showSpinner();
                            const baseFormData = {
                                parentUuid: self.parentUuid,
                                column: document.querySelector('select[name="massActionPagesPrepare[column]"]').value,
                                sysLanguage: document.querySelector('select[name="massActionPagesPrepare[sysLanguage]"]').value,
                                textAiModel: document.querySelector('.text-generation-library input[type="radio"]:checked').value
                            };
                            for (let key in selectedPages) {
                                if(counter === 5) {
                                    try {
                                        handledPages = { ...handledPages, ...currentPages };
                                        let formData = new FormData();
                                        formData.append('massActionPagesExecute[parentUuid]', baseFormData.parentUuid);
                                        formData.append('massActionPagesExecute[column]', baseFormData.column);
                                        formData.append('massActionPagesExecute[sysLanguage]', baseFormData.sysLanguage);
                                        formData.append('massActionPagesExecute[textAiModel]', baseFormData.textAiModel);
                                        formData.append('massActionPagesExecute[pages]', JSON.stringify(currentPages));
                                        await self.sendPagesToExecute(formData, selectedPages, handledPages);
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
                                let formData = new FormData();
                                formData.append('massActionPagesExecute[parentUuid]', baseFormData.parentUuid);
                                formData.append('massActionPagesExecute[column]', baseFormData.column);
                                formData.append('massActionPagesExecute[sysLanguage]', baseFormData.sysLanguage);
                                formData.append('massActionPagesExecute[textAiModel]', baseFormData.textAiModel);
                                formData.append('massActionPagesExecute[pages]', JSON.stringify(currentPages));
                                await self.sendPagesToExecute(formData, selectedPages, handledPages);
                            }
                            Generation.hideSpinner();
                            Notification.success(TYPO3.lang['AiSuite.notification.generation.massAction.success'], TYPO3.lang['AiSuite.notification.generation.massAction.successDescription']);
                            let pagesPrepareExecuteForm = document.querySelector('form[name="pagesPrepareExecute"]');
                            let formData = new FormData(pagesPrepareExecuteForm);
                            self.preparePages(formData).then(() => {});
                        }
                    }
                }
            });
        });
    }
    async preparePages(formData) {
        let res = await Ajax.sendAjaxRequest('aisuite_massaction_pages_prepare', formData);
        if (General.isUsable(res)) {
            if(General.isUsable(res.output) && !General.isUsable(res.output.content)) {
                document.querySelector('#resultsToExecute').innerHTML = res.output;
            } else {
                this.parentUuid = res.output.parentUuid;
                document.querySelector('#resultsToExecute').innerHTML = res.output.content;
                const languageSelect = document.querySelector('select[name="massActionPagesPrepare[sysLanguage]"]');
                if (languageSelect && General.isUsable(res.output.availableSysLanguages)) {
                    languageSelect.innerHTML = '';
                    Object.entries(res.output.availableSysLanguages).forEach(([identifier, label]) => {
                        const option = document.createElement('option');
                        option.value = identifier;
                        option.textContent = label;
                        languageSelect.appendChild(option);
                    });
                }
                if(General.isUsable(res.output.notification) && res.output.notification !== '') {
                    Notification.info(TYPO3.lang['AiSuite.notification.sysLanguage.pageTreeChanged'], res.output.notification);
                }
            }
        } else {
            Notification.error(TYPO3.lang['AiSuite.notification.generation.error'], TYPO3.lang['AiSuite.notification.generation.requestError']);
        }
    }
    async sendPagesToExecute(formData, selectedPages, handledPages) {
        let res = await Ajax.sendAjaxRequest('aisuite_massaction_pages_execute', formData);
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
    async sendPagesToUpdate(formData) {
        let res = await Ajax.sendAjaxRequest('aisuite_massaction_pages_update', formData);
        if (General.isUsable(res)) {
            Generation.hideSpinner();
            document.querySelector('#resultsToExecute').innerHTML = '';
            Notification.success(TYPO3.lang['AiSuite.notification.generation.massAction.successUpdate']);
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
        let checkboxes = document.querySelectorAll('input[name="page-selection"]');
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
export default new PagesPrepare();
