import General from "@autodudes/ai-suite/helper/general.js";
import Generation from "@autodudes/ai-suite/helper/generation.js";
import Ajax from '@autodudes/ai-suite/helper/ajax.js';
import Notification from "@typo3/backend/notification.js";
import InfoWindow from "@typo3/backend/info-window.js";

class FilelistFilesPrepare {

    constructor() {
        this.filesPrepareFormEventListener();
        Generation.cancelGeneration();
        this.fileSelectionEventDelegation();
    }

    filesPrepareFormEventListener() {
        const self = this;
        let filesForm = document.querySelector('form[name="filesPrepareExecute"]');
        filesForm.addEventListener('submit', async function(ev) {
            ev.preventDefault();
            await self.updateContent();
        });
    }

    fileSelectionEventDelegation() {
        const self = this;

        document.querySelectorAll('#resultsToExecute').forEach(function(element) {
            element.addEventListener('click', async function(ev) {
                if(ev && ev.target) {
                    if(ev.target.nodeName === 'BUTTON' && ev.target.type === 'submit' && ev.target.id === 'filesSaveMetadataSubmitBtn') {
                        ev.preventDefault();
                        let checkboxes = document.querySelectorAll('input[name^="file-selection"]');
                        let selectedFiles = {};
                        checkboxes.forEach(function(checkbox) {
                            if(checkbox.checked) {
                                let inputValues = document.querySelectorAll('input[name^="files[' + checkbox.value + ']"]');
                                inputValues.forEach(function(input) {
                                    let column = input.name.replace('files[' + checkbox.value + '][', '').replace(']', '');
                                    let obj = {};
                                    obj[column] = input.value;
                                    selectedFiles[checkbox.value] = { ...selectedFiles[checkbox.value], ...obj };
                                });
                            }
                        });
                        if(Object.keys(selectedFiles).length === 0) {
                            Notification.warning(TYPO3.lang['AiSuite.notification.generation.massAction.missingSelection'], TYPO3.lang['AiSuite.notification.generation.massAction.missingFiles']);
                        } else {
                            let formToSubmit = document.querySelector('form[name="filesPrepareExecute"]'); // get the current form values
                            let formData = new FormData(formToSubmit);
                            formData.append('file-selection', JSON.stringify(selectedFiles));
                            let res = await self.sendFilesToUpdate(formData);
                            if (res) {
                                Notification.success(TYPO3.lang['AiSuite.notification.generation.massAction.successUpdate']);
                            } else {
                                Notification.error(TYPO3.lang['AiSuite.notification.generation.error'], TYPO3.lang['AiSuite.notification.generation.requestError']);
                            }
                            await self.updateContent();
                        }
                    }
                    if(ev.target.nodeName === 'BUTTON' && ev.target.type === 'submit' && ev.target.id === 'filesExecuteFormSubmitBtn') {
                        ev.preventDefault();
                        let checkboxes = document.querySelectorAll('input[name^="file-selection"]');
                        let selectedFiles = {};
                        checkboxes.forEach(function(checkbox) {
                            if(checkbox.checked) {
                                let inputValues = document.querySelectorAll('input[name^="files[' + checkbox.value + ']"]');
                                inputValues.forEach(function(input) {
                                    let column = input.name.replace('files[' + checkbox.value + '][', '').replace(']', '');
                                    let obj = {};
                                    obj[column] = input.value;
                                    selectedFiles[checkbox.value] = { ...selectedFiles[checkbox.value], ...obj };
                                });
                            }
                        });

                        if(Object.keys(selectedFiles).length === 0) {
                            Notification.warning(TYPO3.lang['AiSuite.notification.generation.massAction.missingSelection'], TYPO3.lang['AiSuite.notification.generation.massAction.missingFiles']);
                        } else {
                            let counter = 0;
                            let currentFiles = {};
                            let handledFiles = {};
                            Generation.showSpinner();
                            const baseFormData = {
                                parentUuid: document.querySelector('input#parentUuid').value,
                                column: document.querySelector('select#column').value,
                                sysLanguage: document.querySelector('select#sysLanguage').value,
                                textAiModel: document.querySelector('.text-generation-library input[type="radio"]:checked').value
                            };
                            for (let key in selectedFiles) {
                                if(counter === 5) {
                                    try {
                                        handledFiles = { ...handledFiles, ...currentFiles };
                                        let formData = new FormData();
                                        formData.append('massActionFilesExecute[parentUuid]', baseFormData.parentUuid);
                                        formData.append('massActionFilesExecute[column]', baseFormData.column);
                                        formData.append('massActionFilesExecute[sysLanguage]', baseFormData.sysLanguage);
                                        formData.append('massActionFilesExecute[textAiModel]', baseFormData.textAiModel);
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
                                let formData = new FormData();
                                formData.append('massActionFilesExecute[parentUuid]', baseFormData.parentUuid);
                                formData.append('massActionFilesExecute[column]', baseFormData.column);
                                formData.append('massActionFilesExecute[sysLanguage]', baseFormData.sysLanguage);
                                formData.append('massActionFilesExecute[textAiModel]', baseFormData.textAiModel);
                                formData.append('massActionFilesExecute[files]', JSON.stringify(currentFiles));
                                await self.sendFilesToExecute(formData, selectedFiles, handledFiles);
                            }
                            Generation.hideSpinner();
                            self.updateContent();
                            Notification.success(TYPO3.lang['AiSuite.notification.generation.massAction.success'], TYPO3.lang['AiSuite.notification.generation.massAction.successDescription']);
                        }
                    }
                    if(ev.target.nodeName === 'INPUT' && ev.target.type === 'checkbox' && ev.target.id === 'toggleFileSelection') {
                        let checkboxes = document.querySelectorAll('input[name^="file-selection"]');
                        checkboxes.forEach(function(checkbox) {
                            checkbox.checked = ev.target.checked;
                        });
                        self.calculateRequestAmount();
                    }
                    if(ev.target.nodeName === 'INPUT' && ev.target.classList.contains('file-metadata-field')) {
                        if(ev.target.closest('.list-group-item').querySelector('input[name^="file-selection"]')) {
                            ev.target.closest('.list-group-item').querySelector('input[name^="file-selection"]').checked = true;
                        }
                        self.calculateRequestAmount();
                    }
                    if(ev.target.nodeName === 'INPUT' && ev.target.type === 'checkbox' && ev.target.name && ev.target.name.includes('file-selection')) {
                        self.calculateRequestAmount();
                    }
                    if(ev.target.nodeName === 'DIV' && ev.target.classList.contains('file-meta-content-info')) {
                        const table = ev.target.dataset.table;
                        const uid = ev.target.dataset.uid;
                        InfoWindow.showItem(table, uid);
                    }
                }
            });
        });
    }

    async sendFilesToUpdate(formData) {
        Generation.showSpinner();
        let res = await Ajax.sendAjaxRequest('aisuite_massaction_filelist_files_update', formData);
        Generation.hideSpinner();
        return General.isUsable(res)
    }
    async sendFilesToExecute(formData, selectedFiles, handledFiles) {
        let res = await Ajax.sendAjaxRequest('aisuite_massaction_filelist_files_execute', formData);
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

    async updateContent() {
        Generation.showSpinner();
        let filesForm = document.querySelector('form[name="filesPrepareExecute"]');
        const formData = new FormData(filesForm);
        let res = await Ajax.sendAjaxRequest('aisuite_massaction_filelist_files_update_view', formData);
        if (General.isUsable(res)) {
            if(General.isUsable(res.output) && !General.isUsable(res.output.content)) {
                document.querySelector('#resultsToExecute').innerHTML = res.output;
            } else {
                document.querySelector('#resultsToExecute').innerHTML = res.output.content;
            }
        } else {
            Notification.error(TYPO3.lang['AiSuite.notification.generation.error'], TYPO3.lang['AiSuite.notification.generation.requestError']);
        }
        Generation.hideSpinner();
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
        let checkboxes = document.querySelectorAll('input[name^="file-selection"]');
        let selectedFiles = 0;
        checkboxes.forEach(function(checkbox) {
            if(checkbox.checked) {
                selectedFiles++;
            }
        });
        if (document.querySelector('form[name="filesPrepareExecute"] #column').value === 'all') {
            selectedFiles *= 2;
        }
        calculatedRequests *= selectedFiles;
        let marker = TYPO3.lang['aiSuite.module.multipleCredits'];
        if(calculatedRequests === 1) {
            marker = TYPO3.lang['aiSuite.module.oneCredit'];
        }
        document.querySelector('div[data-module-id="aiSuite"] .calculated-requests').textContent = '(' + calculatedRequests + ' ' + marker + ')';
    }
}
export default new FilelistFilesPrepare();
