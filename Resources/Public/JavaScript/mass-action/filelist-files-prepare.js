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
        let filesForm = document.querySelector('form#ai-suite-filelist-files');
        filesForm.addEventListener('submit', async function(ev) {
            ev.preventDefault();
            await self.updateContent();
        });

        filesForm.querySelector('select#column').addEventListener('change', function(ev) {
            self.liveFilterFields();
        });

        filesForm.querySelector('#showOnlyEmpty').addEventListener('change', function(ev) {
            self.liveFilterFields();
        });

    }

    fileSelectionEventDelegation() {
        const self = this;
        let filesForm = document.querySelector('form#ai-suite-filelist-files');

        filesForm.querySelector('button#filesSaveMetadataSubmitBtn').addEventListener('click', async function(ev) {
            ev.preventDefault();
            let res = await self.sendFormTo('aisuite_massaction_filelist_files_update');
            if (res) {
                Notification.success(TYPO3.lang['AiSuite.notification.generation.massAction.successUpdate']);
            } else {
                Notification.error(TYPO3.lang['AiSuite.notification.generation.error'], TYPO3.lang['AiSuite.notification.generation.requestError']);
            }
            await self.updateContent();
        });

        filesForm.querySelector('button#filesExecuteFormSubmitBtn').addEventListener('click', async function(ev) {
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
                let formData = new FormData();
                formData.append('massActionFilesExecute[parentUuid]', document.querySelector('input#parentUuid').value);
                formData.append('massActionFilesExecute[column]', document.querySelector('select#column').value);
                formData.append('massActionFilesExecute[sysLanguage]', document.querySelector('input#sysLanguage').value);
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
                self.updateContent();
                Notification.success(TYPO3.lang['AiSuite.notification.generation.massAction.success'], TYPO3.lang['AiSuite.notification.generation.massAction.successDescription']);
            }
        });

        filesForm.querySelector('#toggleFileSelection').addEventListener('click', function(ev) {
            let checkboxes = document.querySelectorAll('input[name^="file-selection"]');
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = ev.target.checked;
            });
            self.calculateRequestAmount();
        });

        filesForm.querySelectorAll('input.file-metadata-field').forEach(function(element) {
            element.addEventListener('click', function(ev) {
                if(ev.target.closest('.list-group-item').querySelector('input[name^="file-selection"]')) {
                    ev.target.closest('.list-group-item').querySelector('input[name^="file-selection"]').checked = true;
                }
                self.calculateRequestAmount();
            });
        });

        filesForm.querySelectorAll('input[type="checkbox"]').forEach(function(element) {
            element.addEventListener('change', function(ev) {
                self.calculateRequestAmount();
            });
        });

        filesForm.querySelectorAll('.file-meta-content-info').forEach(function(element) {
            element.addEventListener('click', function(ev) {
                const table = ev.target.dataset.table;
                const uid = ev.target.dataset.uid;
                InfoWindow.showItem(table, uid);
            });
        });
    }

    async sendFormTo(action) {
        Generation.showSpinner();
        let formToSubmit = document.querySelector('form#ai-suite-filelist-files'); // get the current form values
        let formData = new FormData(formToSubmit);
        let res = await Ajax.sendAjaxRequest(action, formData);
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


    liveFilterFields() {
        let self = this;
        let column = document.querySelector('form#ai-suite-filelist-files #column').value;
        let showOnlyEmpty = document.querySelector('form#ai-suite-filelist-files #showOnlyEmpty').checked;
        document.querySelectorAll('form#ai-suite-filelist-files .list-group-item .file-meta-content .file-metadata-field').forEach(function(item) {
            let itemParent = item.parentNode;
            if ((column === 'all' || itemParent.classList.contains(column)) && (!showOnlyEmpty || item.value === '')) {
                itemParent.style.display = 'block';
            } else {
                itemParent.style.display = 'none';
            }
            let closestListGroupItem = itemParent.closest('.list-group-item');
            let hideParent = true;
            closestListGroupItem.querySelectorAll('.file-meta-content .filelist-input').forEach(function(input) {
                if(input.style.display !== 'none') {
                    hideParent = false;
                }
            });
            if(hideParent) {
                closestListGroupItem.style.display = 'none';
            } else {
                closestListGroupItem.style.display = 'block';
            }
        });
        self.calculateRequestAmount();
    }

    async updateContent() {
        let self = this;
        Generation.showSpinner();
        let filesForm = document.querySelector('form#ai-suite-filelist-files');
        const formData = new FormData(filesForm);
        let res = await Ajax.sendAjaxRequest('aisuite_massaction_filelist_files_update_view', formData);
        if (General.isUsable(res)) {
            if(General.isUsable(res.output) && !General.isUsable(res.output.content)) {
                document.querySelector('#resultsToExecute').innerHTML = res.output;
            } else {
                document.querySelector('#resultsToExecute').innerHTML = res.output.content;
            }
            self.fileSelectionEventDelegation();
        } else {
            Notification.error(TYPO3.lang['AiSuite.notification.generation.error'], TYPO3.lang['AiSuite.notification.generation.requestError']);
        }
        Generation.hideSpinner();
    }

    calculateRequestAmount() {
        let calculatedRequests = 0;
        document.querySelectorAll('form#ai-suite-filelist-files .library').forEach(function (library) {
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
        if (document.querySelector('form#ai-suite-filelist-files #column').value === 'all') {
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
