import General from "@autodudes/ai-suite/helper/general.js";
import Generation from "@autodudes/ai-suite/helper/generation.js";
import Ajax from '@autodudes/ai-suite/helper/ajax.js';
import Notification from "@typo3/backend/notification.js";
import InfoWindow from "@typo3/backend/info-window.js";

class FilesPrepare {
    parentUuid;
    constructor() {
        this.filesPrepareExecuteFormEventListener();
        Generation.cancelGeneration();
        this.fileSelectionEventDelegation();
        this.parentUuid = '';
        this.init();
    }

    init() {
        let startFromPidInput = document.querySelector('input[name="massActionFilesPrepare[startFromPid]"]');
        if (!General.isUsable(startFromPidInput) || !General.isUsable(startFromPidInput.value) || startFromPidInput.value === '' || startFromPidInput.value === '0') {
            return;
        }
        let filesPrepareExecuteForm = document.querySelector('form[name="filesPrepareExecute"]');
        const formData = new FormData(filesPrepareExecuteForm);
        this.prepareFiles(formData).then(() => {});
    }

    filesPrepareExecuteFormEventListener() {
        const self = this;
        let filesPrepareExecuteForm = document.querySelector('form[name="filesPrepareExecute"]');
        filesPrepareExecuteForm.addEventListener('submit', async function(ev) {
            ev.preventDefault();
            let startFromPidInput = document.querySelector('input[name="massActionFilesPrepare[startFromPid]"]');
            if (!General.isUsable(startFromPidInput) || !General.isUsable(startFromPidInput.value) || startFromPidInput.value === '') {
                Notification.warning(TYPO3.lang['AiSuite.notification.generation.massAction.missingSelection'], TYPO3.lang['AiSuite.notification.generation.massAction.missingStartFromPid']);
                return;
            }
            Generation.showSpinner();
            const formData = new FormData(filesPrepareExecuteForm);
            await self.prepareFiles(formData);
            Generation.hideSpinner();
        });
    }
    fileSelectionEventDelegation() {
        const self = this;
        document.querySelectorAll('#resultsToExecute').forEach(function(element) {
            element.addEventListener('click', async function(ev) {
                if(ev && ev.target) {
                    if(ev.target.nodeName === 'INPUT' && ev.target.type === 'checkbox' && ev.target.id === 'toggleFileSelection') {
                        let checkboxes = document.querySelectorAll('input[name="file-selection"]');
                        checkboxes.forEach(function(checkbox) {
                            checkbox.checked = ev.target.checked;
                        });
                        self.calculateRequestAmount();
                    }
                    if(ev.target.nodeName === 'INPUT' && ev.target.type === 'checkbox' && ev.target.name === 'file-selection') {
                        self.calculateRequestAmount();
                    }
                    if(ev.target.nodeName === 'INPUT' && ev.target.type === 'text' && ev.target.classList.contains('file-metadata-field')) {
                        if(ev.target.closest('.list-group-item').querySelector('input[name="file-selection"]')) {
                            ev.target.closest('.list-group-item').querySelector('input[name="file-selection"]').checked = true;
                            self.calculateRequestAmount();
                        }
                    }
                    if(ev.target.nodeName === 'DIV' && ev.target.classList.contains('file-meta-content-info')) {
                        const table = ev.target.dataset.table;
                        const uid = ev.target.dataset.uid;
                        InfoWindow.showItem(table, uid);
                    }
                    if(ev.target.nodeName === 'BUTTON' && ev.target.type === 'submit' && ev.target.id === 'filesSaveMetadataSubmitBtn') {
                        ev.preventDefault();
                        let checkboxes = document.querySelectorAll('input[name="file-selection"]');
                        let selectedFiles = {};
                        checkboxes.forEach(function(checkbox) {
                            if(checkbox.checked) {
                                const metadataValue = checkbox.closest('.list-group-item').querySelector('.file-metadata-field').value;
                                selectedFiles[checkbox.value] = metadataValue;
                            }
                        });
                        if(Object.keys(selectedFiles).length === 0) {
                            Notification.warning(TYPO3.lang['AiSuite.notification.generation.massAction.missingSelection'], TYPO3.lang['AiSuite.notification.generation.massAction.missingFiles']);
                        } else {
                            let formData = new FormData();
                            formData.append('massActionFilesExecute[column]', document.querySelector('select[name="massActionFilesPrepare[column]"]').value);
                            formData.append('massActionFilesExecute[sysLanguage]', document.querySelector('select[name="massActionFilesPrepare[sysLanguage]"]').value);
                            formData.append('massActionFilesExecute[files]', JSON.stringify(selectedFiles));
                            await self.sendFilesToUpdate(formData);
                        }
                    }
                    if(ev.target.nodeName === 'BUTTON' && ev.target.type === 'submit' && ev.target.id === 'filesExecuteFormSubmitBtn') {
                        ev.preventDefault();
                        let checkboxes = document.querySelectorAll('input[name="file-selection"]');
                        let selectedFiles = {};
                        checkboxes.forEach(function(checkbox) {
                            if(checkbox.checked) {
                                selectedFiles[checkbox.value] = checkbox.dataset.sysFileUid;
                            }
                        });
                        if(Object.keys(selectedFiles).length === 0) {
                            Notification.warning(TYPO3.lang['AiSuite.notification.generation.massAction.missingSelection'], TYPO3.lang['AiSuite.notification.generation.massAction.missingFiles']);
                        } else {
                            let counter = 0;
                            let currentFiles = {};
                            let handledFiles = {};
                            Generation.showSpinner();
                            let formData = new FormData();
                            formData.append('massActionFilesExecute[parentUuid]', self.parentUuid);
                            formData.append('massActionFilesExecute[column]', document.querySelector('select[name="massActionFilesPrepare[column]"]').value);
                            formData.append('massActionFilesExecute[sysLanguage]', document.querySelector('select[name="massActionFilesPrepare[sysLanguage]"]').value);
                            formData.append('massActionFilesExecute[textAiModel]', document.querySelector('.text-generation-library input[type="radio"]:checked').value);
                            for (let key in selectedFiles) {
                                if(counter === 10) {
                                    try {
                                        handledFiles = { ...handledFiles, ...currentFiles };
                                        formData.append('massActionFilesExecute[files]', JSON.stringify(currentFiles));
                                        await self.sendFilesToExecute(formData, selectedFiles, handledFiles);
                                        counter = 0;
                                        currentFiles = {};
                                    } catch (e) {
                                        console.error(e);
                                    }
                                }
                                currentFiles[key] = selectedFiles[key];
                                counter++;
                            }
                            if(Object.keys(currentFiles).length > 0) {
                                formData.append('massActionFilesExecute[files]', JSON.stringify(currentFiles));
                                await self.sendFilesToExecute(formData, selectedFiles, handledFiles);
                            }
                            Generation.hideSpinner();
                            document.querySelector('#resultsToExecute').innerHTML = '';
                            Notification.success(TYPO3.lang['AiSuite.notification.generation.massAction.success'], TYPO3.lang['AiSuite.notification.generation.massAction.successDescription']);
                        }
                    }
                }
            });
        });
    }

    async prepareFiles(formData) {
        let res = await Ajax.sendAjaxRequest('aisuite_massaction_files_prepare', formData);
        if (General.isUsable(res)) {
            if(General.isUsable(res.output) && !General.isUsable(res.output.content)) {
                document.querySelector('#resultsToExecute').innerHTML = res.output;
            } else {
                self.parentUuid = res.output.parentUuid;
                document.querySelector('#resultsToExecute').innerHTML = res.output.content;
            }
        } else {
            Notification.error(TYPO3.lang['AiSuite.notification.generation.error'], TYPO3.lang['AiSuite.notification.generation.requestError']);
        }
    }

    async sendFilesToExecute(formData, selectedFiles, handledFiles) {
        let res = await Ajax.sendAjaxRequest('aisuite_massaction_files_execute', formData);
        if (General.isUsable(res)) {
            if(res.output.failedFiles.length > 0) {
                Notification.error(TYPO3.lang['AiSuite.notification.generation.error'], TYPO3.lang['AiSuite.notification.generation.failedFiles'] + res.output.failedFiles.join(', '));
            }
            let statusElement = document.querySelector('.module-body .spinner-overlay .status');
            if (statusElement !== null) {
                statusElement.innerHTML = res.output.message + Object.keys(handledFiles).length + ' / ' + Object.keys(selectedFiles).length;
            }
        } else {
            Notification.error(TYPO3.lang['AiSuite.notification.generation.error'], TYPO3.lang['AiSuite.notification.generation.requestError']);
        }
    }
    async sendFilesToUpdate(formData) {
        let res = await Ajax.sendAjaxRequest('aisuite_massaction_files_update', formData);
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
        let checkboxes = document.querySelectorAll('input[name="file-selection"]');
        let selectedFiles = 0;
        checkboxes.forEach(function(checkbox) {
            if(checkbox.checked) {
                selectedFiles++;
            }
        });
        calculatedRequests *= selectedFiles;
        let marker = TYPO3.lang['aiSuite.module.multipleCredits'];
        if(calculatedRequests === 1) {
            marker = TYPO3.lang['aiSuite.module.oneCredit'];
        }
        document.querySelector('div[data-module-id="aiSuite"] .calculated-requests').textContent = '(' + calculatedRequests + ' ' + marker + ')';
    }
}
export default new FilesPrepare();
