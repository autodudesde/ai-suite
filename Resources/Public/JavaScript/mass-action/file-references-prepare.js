import General from "@autodudes/ai-suite/helper/general.js";
import Generation from "@autodudes/ai-suite/helper/generation.js";
import Ajax from '@autodudes/ai-suite/helper/ajax.js';
import Notification from "@typo3/backend/notification.js";
import InfoWindow from "@typo3/backend/info-window.js";
import GlobalInstructions from "@autodudes/ai-suite/helper/global-instructions.js";

class FileReferencePrepare {
    parentUuid;
    constructor() {
        this.fileReferencesPrepareExecuteFormEventListener();
        Generation.cancelGeneration();
        this.fileReferencesSelectionEventDelegation();
        GlobalInstructions.metadataTooltipEventDelegation();
        this.parentUuid = '';
        this.init();
    }

    init() {
        let startFromPidInput = document.querySelector('input[name="massActionFileReferencesPrepare[startFromPid]"]');
        if (!General.isUsable(startFromPidInput) || !General.isUsable(startFromPidInput.value) || startFromPidInput.value === '' || startFromPidInput.value === '0') {
            return;
        }
        let fileReferencesPrepareExecuteForm = document.querySelector('form[name="fileReferencesPrepareExecute"]');
        const formData = new FormData(fileReferencesPrepareExecuteForm);
        this.prepareFileReferences(formData).then(() => {});
    }

    fileReferencesPrepareExecuteFormEventListener() {
        const self = this;
        let fileReferencesPrepareExecuteForm = document.querySelector('form[name="fileReferencesPrepareExecute"]');
        fileReferencesPrepareExecuteForm.addEventListener('submit', async function(ev) {
            ev.preventDefault();
            let startFromPidInput = document.querySelector('input[name="massActionFileReferencesPrepare[startFromPid]"]');
            if (!General.isUsable(startFromPidInput) || !General.isUsable(startFromPidInput.value) || startFromPidInput.value === '') {
                Notification.warning(TYPO3.lang['AiSuite.notification.generation.massAction.missingSelection'], TYPO3.lang['AiSuite.notification.generation.massAction.missingStartFromPid']);
                return;
            }
            Generation.showSpinner();
            const formData = new FormData(fileReferencesPrepareExecuteForm);
            await self.prepareFileReferences(formData);
            Generation.hideSpinner();
        });
    }
    fileReferencesSelectionEventDelegation() {
        const self = this;
        document.querySelectorAll('#resultsToExecute').forEach(function(element) {
            element.addEventListener('click', async function(ev) {
                if(ev && ev.target) {
                    if(ev.target.nodeName === 'INPUT' && ev.target.type === 'checkbox' && ev.target.id === 'toggleFileReferenceSelection') {
                        let checkboxes = document.querySelectorAll('input[name="file-reference-selection"]');
                        checkboxes.forEach(function(checkbox) {
                            checkbox.checked = ev.target.checked;
                        });
                        self.calculateRequestAmount();
                    }
                    if(ev.target.nodeName === 'INPUT' && ev.target.type === 'checkbox' && ev.target.name === 'file-reference-selection') {
                        self.calculateRequestAmount();
                    }
                    if(ev.target.nodeName === 'INPUT' && ev.target.type === 'text' && ev.target.classList.contains('file-reference-metadata-field')) {
                        if(ev.target.closest('.list-group-item').querySelector('input[name="file-reference-selection"]')) {
                            ev.target.closest('.list-group-item').querySelector('input[name="file-reference-selection"]').checked = true;
                            self.calculateRequestAmount();
                        }
                    }
                    if(ev.target.nodeName === 'DIV' && ev.target.classList.contains('file-reference-meta-content-info')) {
                        const table = ev.target.dataset.table;
                        const uid = ev.target.dataset.uid;
                        InfoWindow.showItem(table, uid);
                    }
                    if(ev.target.nodeName === 'BUTTON' && ev.target.type === 'submit' && ev.target.id === 'fileReferencesSaveMetadataSubmitBtn') {
                        ev.preventDefault();
                        let checkboxes = document.querySelectorAll('input[name="file-reference-selection"]');
                        let selectedFileReferences = {};
                        checkboxes.forEach(function(checkbox) {
                            if(checkbox.checked) {
                                selectedFileReferences[checkbox.value] = checkbox.closest('.list-group-item').querySelector('.file-reference-metadata-field').value;
                            }
                        });
                        if(Object.keys(selectedFileReferences).length === 0) {
                            Notification.warning(TYPO3.lang['AiSuite.notification.generation.massAction.missingSelection'], TYPO3.lang['AiSuite.notification.generation.massAction.missingFiles']);
                        } else {
                            let formData = new FormData();
                            formData.append('massActionFileReferencesExecute[column]', document.querySelector('select[name="massActionFileReferencesPrepare[column]"]').value);
                            formData.append('massActionFileReferencesExecute[sysLanguage]', document.querySelector('select[name="massActionFileReferencesPrepare[sysLanguage]"]').value);
                            formData.append('massActionFileReferencesExecute[fileReferences]', JSON.stringify(selectedFileReferences));
                            await self.sendFileReferencesToUpdate(formData);
                        }
                    }
                    if(ev.target.nodeName === 'BUTTON' && ev.target.type === 'submit' && ev.target.id === 'fileReferencesExecuteFormSubmitBtn') {
                        ev.preventDefault();
                        let checkboxes = document.querySelectorAll('input[name="file-reference-selection"]');
                        let selectedFileReferences = {};
                        checkboxes.forEach(function(checkbox) {
                            if(checkbox.checked) {
                                selectedFileReferences[checkbox.value] = checkbox.dataset.sysFileUid;
                            }
                        });
                        if(Object.keys(selectedFileReferences).length === 0) {
                            Notification.warning(TYPO3.lang['AiSuite.notification.generation.massAction.missingSelection'], TYPO3.lang['AiSuite.notification.generation.massAction.missingFiles']);
                        } else {
                            Generation.showSpinner();
                            const baseFormData = {
                                parentUuid: self.parentUuid,
                                column: document.querySelector('select[name="massActionFileReferencesPrepare[column]"]').value,
                                sysLanguage: document.querySelector('select[name="massActionFileReferencesPrepare[sysLanguage]"]').value,
                                textAiModel: document.querySelector('.text-generation-library input[type="radio"]:checked').value,
                                startFromPid: document.querySelector('input[name="massActionFileReferencesPrepare[startFromPid]"]').value,
                            };

                            if (!baseFormData.parentUuid || !baseFormData.column || !baseFormData.sysLanguage || !baseFormData.textAiModel) {
                                Notification.error(TYPO3.lang['AiSuite.error.invalidFormData']);
                                Generation.hideSpinner();
                                return;
                            }

                            await self.processBatches(selectedFileReferences, baseFormData);
                            Generation.hideSpinner();
                            Notification.success(TYPO3.lang['AiSuite.notification.generation.massAction.success'], TYPO3.lang['AiSuite.notification.generation.massAction.successDescription']);
                            selectedFileReferences = null;
                            let fileReferencesPrepareExecuteForm = document.querySelector('form[name="fileReferencesPrepareExecute"]');
                            let formData = new FormData(fileReferencesPrepareExecuteForm);
                            self.prepareFileReferences(formData).then(() => {});
                        }
                    }
                }
            });
        });
    }

    async prepareFileReferences(formData) {
        let res = await Ajax.sendAjaxRequest('aisuite_massaction_filereferences_prepare', formData);
        if (General.isUsable(res)) {
            if(General.isUsable(res.output) && !General.isUsable(res.output.content)) {
                document.querySelector('#resultsToExecute').innerHTML = res.output;
            } else {
                this.parentUuid = res.output.parentUuid;
                document.querySelector('#resultsToExecute').innerHTML = res.output.content;
                const languageSelect = document.querySelector('select[name="massActionFileReferencesPrepare[sysLanguage]"]');
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

    async sendFileReferencesToExecute(formData, selectedFileReferences, handledFileReferences, maxRetries = 2, delay = 1000) {
        try {
            if (!formData.has('massActionFileReferencesExecute[fileReferences]')) {
                throw new Error(TYPO3.lang['AiSuite.error.invalidFormData']);
            }

            let lastError;
            for (let attempt = 1; attempt <= maxRetries; attempt++) {
                try {
                    let res = await Ajax.sendAjaxRequest('aisuite_massaction_filereferences_execute', formData);

                    if (!General.isUsable(res)) {
                        throw new Error(TYPO3.lang['AiSuite.error.invalidServerResponse']);
                    }

                    if (res.output?.failedFileReferences?.length > 0) {
                        Notification.error(
                            TYPO3.lang['AiSuite.notification.generation.error'],
                            TYPO3.lang['AiSuite.notification.generation.failedFileReferences'] + res.output.failedFileReferences.join(', ')
                        );
                    }

                    let statusElement = document.querySelector('.module-body .spinner-overlay .status');
                    if (statusElement !== null && selectedFileReferences && handledFileReferences) {
                        statusElement.innerHTML = `${res.output.message}${Object.keys(handledFileReferences).length} / ${Object.keys(selectedFileReferences).length}`;
                    }

                    return res;
                } catch (error) {
                    lastError = error;
                    console.warn(`Request attempt ${attempt} failed:`, error);
                    if (attempt < maxRetries) {
                        await new Promise(resolve => setTimeout(resolve, delay * attempt));
                    }
                }
            }
            throw lastError;

        } catch (error) {
            console.error('sendFileReferencesToExecute failed after all retries:', error.message);
            Notification.error(
                TYPO3.lang['AiSuite.notification.generation.error'],
                TYPO3.lang['AiSuite.notification.generation.requestError']
            );
            throw error;
        }
    }
    async sendFileReferencesToUpdate(formData) {
        let res = await Ajax.sendAjaxRequest('aisuite_massaction_filereferences_update', formData);
        if (General.isUsable(res)) {
            Generation.hideSpinner();
            document.querySelector('#resultsToExecute').innerHTML = '';
            Notification.success(TYPO3.lang['AiSuite.notification.generation.massAction.successUpdate']);
        }
    }

    createFormData(baseFormData, currentFileReferences) {
        let formData = new FormData();
        formData.append('massActionFileReferencesExecute[parentUuid]', baseFormData.parentUuid);
        formData.append('massActionFileReferencesExecute[column]', baseFormData.column);
        formData.append('massActionFileReferencesExecute[sysLanguage]', baseFormData.sysLanguage);
        formData.append('massActionFileReferencesExecute[textAiModel]', baseFormData.textAiModel);
        formData.append('massActionFileReferencesExecute[startFromPid]', baseFormData.startFromPid);
        formData.append('massActionFileReferencesExecute[fileReferences]', JSON.stringify(currentFileReferences));
        return formData;
    }

    async processBatches(selectedFileReferences, baseFormData) {
        const batchSize = 5;
        const delayBetweenBatches = 500;

        const fileKeys = Object.keys(selectedFileReferences);
        let handledFileReferences = {};

        for (let i = 0; i < fileKeys.length; i += batchSize) {
            const batchKeys = fileKeys.slice(i, i + batchSize);
            const currentFileReferences = {};

            batchKeys.forEach(key => {
                currentFileReferences[key] = selectedFileReferences[key];
            });

            try {
                const formData = this.createFormData(baseFormData, currentFileReferences);
                await this.sendFileReferencesToExecute(formData, selectedFileReferences, handledFileReferences);
                handledFileReferences = { ...handledFileReferences, ...currentFileReferences };

                let statusElement = document.querySelector('.module-body .spinner-overlay .status');
                if (statusElement !== null) {
                    statusElement.innerHTML = `${Object.keys(handledFileReferences).length} / ${Object.keys(selectedFileReferences).length}`;
                }

                if (i + batchSize < fileKeys.length) {
                    await new Promise(resolve => setTimeout(resolve, delayBetweenBatches));
                }
            } catch (error) {
                Notification.warning(
                    TYPO3.lang['AiSuite.notification.generation.error'],
                    TYPO3.lang['AiSuite.massAction.batchFailed'].replace('{0}', Math.floor(i/batchSize) + 1)
                );
            }
        }

        return handledFileReferences;
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
        let checkboxes = document.querySelectorAll('input[name="file-reference-selection"]');
        let selectedFileReferences = 0;
        checkboxes.forEach(function(checkbox) {
            if(checkbox.checked) {
                selectedFileReferences++;
            }
        });
        calculatedRequests *= selectedFileReferences;
        let marker = TYPO3.lang['aiSuite.module.multipleCredits'];
        if(calculatedRequests === 1) {
            marker = TYPO3.lang['aiSuite.module.oneCredit'];
        }
        document.querySelector('div[data-module-id="aiSuite"] .calculated-requests').textContent = '(' + calculatedRequests + ' ' + marker + ')';
    }
}
export default new FileReferencePrepare();
