define([
    "TYPO3/CMS/Backend/Notification",
    "TYPO3/CMS/Backend/InfoWindow",
    "TYPO3/CMS/AiSuite/Helper/General",
    "TYPO3/CMS/AiSuite/Helper/Generation",
    "TYPO3/CMS/AiSuite/Helper/Ajax",
], function(Notification, InfoWindow, General, Generation, Ajax) {
    'use strict';

    /**
     * FileReferencePrepare constructor
     *
     * @constructor
     */
    let FileReferencePrepare = function() {
        this.parentUuid = '';
        this.fileReferencesPrepareExecuteFormEventListener();
        Generation.cancelGeneration();
        this.fileReferencesSelectionEventDelegation();
        this.init();
    };

    /**
     * Initialize
     */
    FileReferencePrepare.prototype.init = function() {
        let startFromPidInput = document.querySelector('input[name="massActionFileReferencesPrepare[startFromPid]"]');
        if (!General.isUsable(startFromPidInput) || !General.isUsable(startFromPidInput.value) || startFromPidInput.value === '' || startFromPidInput.value === '0') {
            return;
        }
        this.prepareFileReferences().then(() => {});
    };

    /**
     * Add event listener for file references prepare execute form
     */
    FileReferencePrepare.prototype.fileReferencesPrepareExecuteFormEventListener = function() {
        let self = this;
        let fileReferencesPrepareExecuteForm = document.querySelector('form[name="fileReferencesPrepareExecute"]');
        fileReferencesPrepareExecuteForm.addEventListener('submit', async function(ev) {
            ev.preventDefault();
            let startFromPidInput = document.querySelector('input[name="massActionFileReferencesPrepare[startFromPid]"]');
            if (!General.isUsable(startFromPidInput) || !General.isUsable(startFromPidInput.value) || startFromPidInput.value === '') {
                Notification.warning(TYPO3.lang['AiSuite.notification.generation.massAction.missingSelection'], TYPO3.lang['AiSuite.notification.generation.massAction.missingStartFromPid']);
                return;
            }
            Generation.showSpinner();
            await self.prepareFileReferences();
            Generation.hideSpinner();
        });
    };

    /**
     * Add event delegation for file references selection
     */
    FileReferencePrepare.prototype.fileReferencesSelectionEventDelegation = function() {
        let self = this;
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
                        let table = ev.target.dataset.table;
                        let uid = ev.target.dataset.uid;
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
                            const postData = {
                                massActionFileReferencesExecute: {
                                    column: document.querySelector('select[name="massActionFileReferencesPrepare[column]"]').value,
                                    sysLanguage: document.querySelector('select[name="massActionFileReferencesPrepare[sysLanguage]"]').value,
                                    fileReferences: JSON.stringify(selectedFileReferences)
                                }
                            }
                            await self.sendFileReferencesToUpdate(postData);
                            await self.prepareFileReferences();
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
                            let counter = 0;
                            let currentFileReferences = {};
                            let handledFileReferences = {};
                            Generation.showSpinner();
                           let postData = {
                                massActionFileReferencesExecute: {
                                    parentUuid: self.parentUuid,
                                    column: document.querySelector('select[name="massActionFileReferencesPrepare[column]"]').value,
                                    sysLanguage: document.querySelector('select[name="massActionFileReferencesPrepare[sysLanguage]"]').value,
                                    textAiModel: document.querySelector('.text-generation-library input[type="radio"]:checked').value
                                }
                            }
                            for (let key in selectedFileReferences) {
                                if(counter === 3) {
                                    try {
                                        handledFileReferences = { ...handledFileReferences, ...currentFileReferences };
                                        postData.massActionFileReferencesExecute.fileReferences = JSON.stringify(currentFileReferences);
                                        await self.sendFileReferencesToExecute(postData, selectedFileReferences, handledFileReferences);
                                        counter = 0;
                                        currentFileReferences = {};
                                    } catch (e) {
                                        console.error(e);
                                    }
                                }
                                currentFileReferences[key] = selectedFileReferences[key];
                                counter++;
                            }
                            if(Object.keys(currentFileReferences).length > 0) {
                                postData.massActionFileReferencesExecute.fileReferences = JSON.stringify(currentFileReferences);
                                await self.sendFileReferencesToExecute(postData, selectedFileReferences, handledFileReferences);
                            }
                            Generation.hideSpinner();
                            Notification.success(TYPO3.lang['AiSuite.notification.generation.massAction.success'], TYPO3.lang['AiSuite.notification.generation.massAction.successDescription']);
                            await self.prepareFileReferences();
                        }
                    }
                }
            });
        });
    };

    /**
     * Prepare file references
     * @returns {Promise}
     */
    FileReferencePrepare.prototype.prepareFileReferences = async function() {
        let self = this;
        const postData = {
            massActionFileReferencesPrepare: {
                startFromPid: document.querySelector('input[name="massActionFileReferencesPrepare[startFromPid]"]').value,
                depth: document.querySelector('select[name="massActionFileReferencesPrepare[depth]"]').value,
                column: document.querySelector('select[name="massActionFileReferencesPrepare[column]"]').value,
                sysLanguage: document.querySelector('select[name="massActionFileReferencesPrepare[sysLanguage]"]').value,
                showOnlyEmpty: document.querySelector('input[name="massActionFileReferencesPrepare[showOnlyEmpty]"]#showOnlyEmpty').checked
            }
        };
        let res = await Ajax.sendAjaxRequest('aisuite_massaction_filereferences_prepare', postData);
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
    };

    /**
     * Send file references to execute
     *
     * @param {Object} postData
     * @param {Object} selectedFileReferences
     * @param {Object} handledFileReferences
     * @returns {Promise}
     */
    FileReferencePrepare.prototype.sendFileReferencesToExecute = function(postData, selectedFileReferences, handledFileReferences) {
        return new Promise(function(resolve, reject) {
            Ajax.sendAjaxRequest('aisuite_massaction_filereferences_execute', postData).then(function(res) {
                if (General.isUsable(res)) {
                    if(res.output.failedFileReferences.length > 0) {
                        Notification.error(TYPO3.lang['AiSuite.notification.generation.error'], TYPO3.lang['AiSuite.notification.generation.failedFileReferences'] + res.output.failedFileReferences.join(', '));
                    }
                    let statusElement = document.querySelector('.module-body .spinner-overlay .status');
                    if (statusElement !== null) {
                        statusElement.innerHTML = res.output.message + Object.keys(handledFileReferences).length + ' / ' + Object.keys(selectedFileReferences).length;
                    }
                    resolve();
                } else {
                    Notification.error(TYPO3.lang['AiSuite.notification.generation.error'], TYPO3.lang['AiSuite.notification.generation.requestError']);
                    reject();
                }
            }).catch(function(error) {
                reject(error);
            });
        });
    };

    /**
     * Send file references to update
     *
     */
    FileReferencePrepare.prototype.sendFileReferencesToUpdate = async function(postData) {
        let res = await Ajax.sendAjaxRequest('aisuite_massaction_filereferences_update', postData);
        if (General.isUsable(res)) {
            Generation.hideSpinner();
            document.querySelector('#resultsToExecute').innerHTML = '';
            Notification.success(TYPO3.lang['AiSuite.notification.generation.massAction.successUpdate']);
        }
    };

    /**
     * Calculate request amount
     */
    FileReferencePrepare.prototype.calculateRequestAmount = function() {
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
    };

    // FileReferencePrepare.prototype.updateContent = function() {
    //     Generation.showSpinner();
    //     const postData = {
    //         massActionFileReferencesPrepare: {
    //             startFromPid: document.querySelector('input[name="massActionFileReferencesPrepare[startFromPid]"]').value,
    //             depth: document.querySelector('select[name="massActionFileReferencesPrepare[depth]"]').value,
    //             column: document.querySelector('select[name="massActionFileReferencesPrepare[column]"]').value,
    //             sysLanguage: document.querySelector('select[name="massActionFileReferencesPrepare[sysLanguage]"]').value,
    //             showOnlyEmpty: document.querySelector('input[name="massActionFileReferencesPrepare[showOnlyEmpty]"]#showOnlyEmpty').checked
    //         }
    //     };
    //     return Ajax.sendAjaxRequest('aisuite_massaction_filereferences_prepare', postData)
    //         .then(function(res) {
    //             if (General.isUsable(res)) {
    //                 if(General.isUsable(res.output) && !General.isUsable(res.output.content)) {
    //                     document.querySelector('#resultsToExecute').innerHTML = res.output;
    //                 } else {
    //                     document.querySelector('#resultsToExecute').innerHTML = res.output.content;
    //                 }
    //             } else {
    //                 Notification.error(TYPO3.lang['AiSuite.notification.generation.error'], TYPO3.lang['AiSuite.notification.generation.requestError']);
    //             }
    //             Generation.hideSpinner();
    //         })
    //         .catch(function(error) {
    //             console.error(error);
    //             Generation.hideSpinner();
    //         });
    // };

    return new FileReferencePrepare();
});
