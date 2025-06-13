define([
    "TYPO3/CMS/Backend/Notification",
    "TYPO3/CMS/Backend/InfoWindow",
    "TYPO3/CMS/AiSuite/Helper/General",
    "TYPO3/CMS/AiSuite/Helper/Generation",
    "TYPO3/CMS/AiSuite/Helper/Ajax",
], function(Notification, InfoWindow, General, Generation, Ajax) {
    'use strict';

    /**
     * FilelistFilesPrepare constructor
     *
     * @constructor
     */
    let FilelistFilesPrepare = function() {
        this.filesPrepareFormEventListener();
        Generation.cancelGeneration();
        this.fileSelectionEventDelegation();
    };

    /**
     * Initialize form event listener
     */
    FilelistFilesPrepare.prototype.filesPrepareFormEventListener = function() {
        let self = this;
        let filesForm = document.querySelector('form[name="filesPrepareExecute"]');

        filesForm.addEventListener('submit', async function(ev) {
            ev.preventDefault();
            await self.updateContent();
        });
    };

    /**
     * Setup file selection event delegation
     */
    FilelistFilesPrepare.prototype.fileSelectionEventDelegation = function() {
        let self = this;

        document.querySelectorAll('#resultsToExecute').forEach(function(element) {
            element.addEventListener('click', async function(ev) {
                if(ev && ev.target) {
                    // Save metadata button handling
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

                                    if(!selectedFiles[checkbox.value]) {
                                        selectedFiles[checkbox.value] = {};
                                    }
                                    selectedFiles[checkbox.value] = Object.assign({}, selectedFiles[checkbox.value], obj);
                                });
                            }
                        });

                        if(Object.keys(selectedFiles).length === 0) {
                            Notification.warning(TYPO3.lang['AiSuite.notification.generation.massAction.missingSelection'], TYPO3.lang['AiSuite.notification.generation.massAction.missingFiles']);
                        } else {
                            const postData = {
                                massActionFilesExecute: {
                                    column: document.querySelector('select[name="options[column]"]').value,
                                    sysLanguage: document.querySelector('select[name="options[sysLanguage]"]').value,
                                    fileSelection: JSON.stringify(selectedFiles)
                                }
                            }
                            let success = await self.sendFilesToUpdate(postData);
                            if (success) {
                                Notification.success(TYPO3.lang['AiSuite.notification.generation.massAction.successUpdate']);
                            } else {
                                Notification.error(TYPO3.lang['AiSuite.notification.generation.error'], TYPO3.lang['AiSuite.notification.generation.requestError']);
                            }
                            await self.updateContent();
                        }
                    }

                    // Execute form button handling
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

                                    if(!selectedFiles[checkbox.value]) {
                                        selectedFiles[checkbox.value] = {};
                                    }
                                    selectedFiles[checkbox.value] = Object.assign({}, selectedFiles[checkbox.value], obj);
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

                            let postData = {
                                massActionFilesExecute: {
                                    parentUuid: document.querySelector('input#parentUuid').value,
                                    column: document.querySelector('select[name="options[column]"]').value,
                                    sysLanguage: document.querySelector('select[name="options[sysLanguage]"]').value,
                                    textAiModel: document.querySelector('.text-generation-library input[type="radio"]:checked').value
                                }
                            }
                            for (let key in selectedFiles) {
                                if(counter === 5) {
                                    try {
                                        handledFiles = { ...handledFiles, ...currentFiles };
                                        postData.massActionFilesExecute.files = JSON.stringify(currentFiles);
                                        await self.sendFilesToExecute(postData, selectedFiles, handledFiles);
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
                                postData.massActionFilesExecute.files = JSON.stringify(currentFiles);
                                await self.sendFilesToExecute(postData, selectedFiles, handledFiles);
                            }
                            Generation.hideSpinner();
                            await self.updateContent();
                            Notification.success(TYPO3.lang['AiSuite.notification.generation.massAction.success'], TYPO3.lang['AiSuite.notification.generation.massAction.successDescription']);
                        }
                    }

                    // Toggle all checkboxes
                    if(ev.target.nodeName === 'INPUT' && ev.target.type === 'checkbox' && ev.target.id === 'toggleFileSelection') {
                        let checkboxes = document.querySelectorAll('input[name^="file-selection"]');
                        checkboxes.forEach(function(checkbox) {
                            checkbox.checked = ev.target.checked;
                        });
                        self.calculateRequestAmount();
                    }

                    // Handle metadata field focus
                    if(ev.target.nodeName === 'INPUT' && ev.target.classList.contains('file-metadata-field')) {
                        if(ev.target.closest('.list-group-item').querySelector('input[name^="file-selection"]')) {
                            ev.target.closest('.list-group-item').querySelector('input[name^="file-selection"]').checked = true;
                        }
                        self.calculateRequestAmount();
                    }

                    // Handle checkbox change
                    if(ev.target.nodeName === 'INPUT' && ev.target.type === 'checkbox' && ev.target.name && ev.target.name.includes('file-selection')) {
                        self.calculateRequestAmount();
                    }

                    // Handle info window
                    if(ev.target.nodeName === 'DIV' && ev.target.classList.contains('file-meta-content-info')) {
                        let table = ev.target.dataset.table;
                        let uid = ev.target.dataset.uid;
                        InfoWindow.showItem(table, uid);
                    }
                }
            });
        });
    };

    /**
     * Send selected files for update
     *
     * @param {Object} postData Form data to send
     * @return {Promise} Promise resolving to success status
     */
    FilelistFilesPrepare.prototype.sendFilesToUpdate = function(postData) {
        Generation.showSpinner();

        return Ajax.sendAjaxRequest('aisuite_massaction_filelist_files_update', postData)
            .then(function(res) {
                Generation.hideSpinner();
                return General.isUsable(res);
            })
            .catch(function(error) {
                Generation.hideSpinner();
                console.error(error);
                return false;
            });
    };

    /**
     * Send selected files for execution
     *
     * @param {Object} postData Form data to send
     * @param {Object} selectedFiles All selected files
     * @param {Object} handledFiles Already handled files
     * @return {Promise} Promise resolving when complete
     */
    FilelistFilesPrepare.prototype.sendFilesToExecute = function(postData, selectedFiles, handledFiles) {
        return Ajax.sendAjaxRequest('aisuite_massaction_filelist_files_execute', postData)
            .then(function(res) {
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
            })
            .catch(function(error) {
                console.error(error);
            });
    };

    /**
     * Update the content view
     *
     * @return {Promise} Promise resolving when complete
     */
    FilelistFilesPrepare.prototype.updateContent = function() {
        Generation.showSpinner();
        const postData = {
            massActionFilesPrepare: {
                column: document.querySelector('select[name="options[column]"]').value,
                sysLanguage: document.querySelector('select[name="options[sysLanguage]"]').value,
                showOnlyEmpty: document.querySelector('input[name="options[showOnlyEmpty]"]#showOnlyEmpty').checked,
                showOnlyUsed: document.querySelector('input[name="options[showOnlyUsed]"]#showOnlyUsed').checked
            }
        };
        return Ajax.sendAjaxRequest('aisuite_massaction_filelist_files_update_view', postData)
            .then(function(res) {
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
            })
            .catch(function(error) {
                console.error(error);
                Generation.hideSpinner();
            });
    };

    /**
     * Calculate and update the request amount
     */
    FilelistFilesPrepare.prototype.calculateRequestAmount = function() {
        let calculatedRequests = 0;

        document.querySelectorAll('.library').forEach(function(library) {
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
    };

    // Return a new instance
    return new FilelistFilesPrepare();
});
