define([
    "TYPO3/CMS/Backend/Notification",
    "TYPO3/CMS/Backend/InfoWindow",
    "TYPO3/CMS/AiSuite/Helper/General",
    "TYPO3/CMS/AiSuite/Helper/Generation",
    "TYPO3/CMS/AiSuite/Helper/Ajax",
], function(Notification, InfoWindow, General, Generation, Ajax) {
    'use strict';

    /**
     * PagesPrepare constructor
     *
     * @constructor
     */
    let PagesPrepare = function() {
        this.parentUuid = '';
        this.pagesPrepareExecuteFormEventListener();
        Generation.cancelGeneration();
        this.pageSelectionEventDelegation();
        this.init();
    };

    PagesPrepare.prototype.init = function() {
        let startFromPidInput = document.querySelector('input[name="massActionPagesPrepare[startFromPid]"]');
        if (!General.isUsable(startFromPidInput) || !General.isUsable(startFromPidInput.value) || startFromPidInput.value === '' || startFromPidInput.value === '0') {
            return;
        }
        this.preparePages().then(() => {});
    }

    PagesPrepare.prototype.pagesPrepareExecuteFormEventListener = function() {
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
            await self.preparePages();
            Generation.hideSpinner();
        });
    }
    PagesPrepare.prototype.pageSelectionEventDelegation = function() {
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
                            const postData = {
                                massActionPagesExecute: {
                                    column: document.querySelector('select[name="massActionPagesPrepare[column]"]').value,
                                    sysLanguage: document.querySelector('select[name="massActionPagesPrepare[sysLanguage]"]').value,
                                    pages: JSON.stringify(selectedPages)
                                }
                            }
                            await self.sendPagesToUpdate(postData);
                            await self.preparePages();
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
                            let postData = {
                                massActionPagesExecute: {
                                    parentUuid: parentUuid,
                                    column: document.querySelector('select[name="massActionPagesPrepare[column]"]').value,
                                    sysLanguage: document.querySelector('select[name="massActionPagesPrepare[sysLanguage]"]').value,
                                    textAiModel: document.querySelector('.text-generation-library input[type="radio"]:checked').value
                                }
                            }
                            for (let key in selectedPages) {
                                if(counter === 5) {
                                    try {
                                        handledPages = { ...handledPages, ...currentPages };
                                        postData.massActionPagesExecute.pages = JSON.stringify(currentPages);
                                        await self.sendPagesToExecute(postData, selectedPages, handledPages);
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
                                postData.massActionPagesExecute.pages = JSON.stringify(currentPages);
                                await self.sendPagesToExecute(postData, selectedPages, handledPages);
                            }
                            Generation.hideSpinner();
                            Notification.success(TYPO3.lang['AiSuite.notification.generation.massAction.success'], TYPO3.lang['AiSuite.notification.generation.massAction.successDescription']);
                            await self.preparePages();
                        }
                    }
                }
            });
        });
    }
    PagesPrepare.prototype.preparePages = async function () {
        const postData = {
            massActionPagesPrepare: {
                startFromPid: document.querySelector('input[name="massActionPagesPrepare[startFromPid]"]').value,
                depth: document.querySelector('select[name="massActionPagesPrepare[depth]"]').value,
                column: document.querySelector('select[name="massActionPagesPrepare[column]"]').value,
                sysLanguage: document.querySelector('select[name="massActionPagesPrepare[sysLanguage]"]').value,
                pageType: document.querySelector('select[name="massActionPagesPrepare[pageType]"]').value,
                showOnlyEmpty: document.querySelector('input[name="massActionPagesPrepare[showOnlyEmpty]"]#showOnlyEmpty').checked
            }
        };
        let res = await Ajax.sendAjaxRequest('aisuite_massaction_pages_prepare', postData);
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
    PagesPrepare.prototype.sendPagesToExecute = async function(postData, selectedPages, handledPages) {
        let res = await Ajax.sendAjaxRequest('aisuite_massaction_pages_execute', postData);
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
    PagesPrepare.prototype.sendPagesToUpdate = async function(postData) {
        let res = await Ajax.sendAjaxRequest('aisuite_massaction_pages_update', postData);
        if (General.isUsable(res)) {
            Generation.hideSpinner();
            document.querySelector('#resultsToExecute').innerHTML = '';
            Notification.success(TYPO3.lang['AiSuite.notification.generation.massAction.successUpdate']);
        }
    }

    PagesPrepare.prototype.calculateRequestAmount = function () {
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
    return new PagesPrepare();
});
