define([
    'jquery',
    'TYPO3/CMS/AiSuite/Helper/General',
    'TYPO3/CMS/AiSuite/Helper/Ajax',
    'TYPO3/CMS/Backend/Modal',
    'TYPO3/CMS/Backend/Notification',
    'TYPO3/CMS/Backend/Severity',
    'TYPO3/CMS/Backend/InfoWindow'
], function($, General, Ajax, Modal, Notification, Severity, InfoWindow) {
    'use strict';

    /**
     * Overview object
     *
     * @constructor
     */
    function Overview() {
        this.views = ['Page', 'FileReference', 'FileMetadata'];
        this.clickAndSave = false;
        this.activeView = 'Page';

        this.selectionHandler();
        this.addDeleteEventListener();
        this.addDeleteAllEventListener();
        this.addSaveEventListener();
        this.addViewEventListener();
        this.addFilterEventListener();
        this.addInfoWindowEventListener();
        this.addClickAndSaveEventListener();
        this.addErrorTooltipEventListener();
        this.updateDeleteAllButtonVisibility();
        this.addRefreshAndReloadButtonEventListener();
    }

    Overview.prototype.addViewEventListener  = function() {
        const urlParams = new URLSearchParams(window.location.search);
        const filterParam = urlParams.get('backgroundTaskFilter');
        const clickAndSaveParam = urlParams.get('clickAndSave');

        if (filterParam && this.views.includes(filterParam)) {
            this.activeView = filterParam;
            const filterSelect = document.querySelector('#backgroundTaskFilter');
            if (filterSelect) {
                filterSelect.value = filterParam;
            }
        }
        if (clickAndSaveParam) {
            this.clickAndSave = clickAndSaveParam === '1';
            const clickAndSaveCheckbox = document.querySelector('#clickAndSave');
            if (clickAndSaveCheckbox) {
                clickAndSaveCheckbox.checked = this.clickAndSave;
            }
        }
        const accordionItems = document.querySelectorAll('#accordionBackgroundTasks' + this.activeView + ' .accordion-item').length || 0;
        document.querySelector('#accordionBackgroundTasks' + this.activeView).style.display = 'block';
        const noBackgroundTasks = document.querySelector('#accordionBackgroundTasks' + this.activeView + ' #noBackgroundTasks');
        if(noBackgroundTasks) {
            if (accordionItems === 0) {
                noBackgroundTasks.style.display = 'block';
            } else {
                noBackgroundTasks.style.display = 'none';
            }
        }
        const firstAccordionItem = document.querySelector('#accordionBackgroundTasks' + this.activeView + ' .accordion-item .open-accordion-item');
        if (firstAccordionItem) {
            firstAccordionItem.click();
        }
        this.updateDeleteAllButtonVisibility();
    }

    Overview.prototype.addFilterEventListener = function() {
        const self = this;
        document.querySelector('#backgroundTaskFilter').addEventListener('change', function(ev) {
            let filterValue = ev.target.value;
            self.updateBackgroundTaskUrl();
            document.querySelectorAll('.accordion-background-tasks').forEach(function(element) {
                if(element.dataset.type === filterValue) {
                    element.style.display = 'block';
                    let accordionItems = element.querySelectorAll('.accordion-item').length || 0;
                    if(accordionItems === 0) {
                        element.querySelector('#noBackgroundTasks').style.display = 'block';
                        element.querySelector('.delete-all-wrapper').style.display = 'none';
                    } else {
                        const firstAccordionItem = element.querySelector('.accordion-item');
                        if(firstAccordionItem && firstAccordionItem.querySelector('button.accordion-button').classList.contains('collapsed')) {
                            const openButton = firstAccordionItem.querySelector('.open-accordion-item');
                            if (openButton) {
                                openButton.click();
                            }
                        }
                        element.querySelector('#noBackgroundTasks').style.display = 'none';
                        element.querySelector('.delete-all-wrapper').style.display = 'flex';
                    }
                } else {
                    element.style.display = 'none';
                    element.querySelector('#noBackgroundTasks').style.display = 'none';
                }
            });
            self.activeView = filterValue;
        });
    }

    Overview.prototype.addDeleteEventListener = function() {
        const self = this;
        document.querySelectorAll('.delete-accordion-item').forEach(function(element) {
            element.addEventListener('click', function(ev) {
                ev.preventDefault();
                let accordionBackgroundTasksElement = ev.target.closest('.accordion-background-tasks');
                let uuid = ev.target.dataset.uuid;
                let column = ev.target.dataset.column;
                Modal.confirm('Warning', TYPO3.lang['AiSuite.backgroundTasks.deleteModalTitle'], Severity.warning, [
                    {
                        text: TYPO3.lang['AiSuite.backgroundTasks.deleteModalText'],
                        active: true,
                        trigger: async function() {
                            let res = await Ajax.sendAjaxRequest('aisuite_background_task_delete', {uuids: [uuid], column: column});
                            if (General.isUsable(res)) {
                                Notification.success(TYPO3.lang['AiSuite.notification.deleteSuccess']);

                                const accordionItem = document.querySelector('.accordion-item[data-uuid="'+uuid+'"][data-column="'+column+'"]');
                                const columnSection = accordionItem.closest('.accordion-column');
                                accordionItem.remove();

                                if (columnSection && columnSection.querySelectorAll('.accordion-item').length === 0) {
                                    columnSection.remove();
                                } else if (columnSection) {
                                    const taskCount = columnSection.querySelectorAll('.accordion-item').length;
                                    columnSection.querySelector('.column-task-count').textContent = taskCount;
                                }

                                if(accordionBackgroundTasksElement.querySelectorAll('.accordion-item').length === 0) {
                                    accordionBackgroundTasksElement.querySelector('#noBackgroundTasks').style.display = 'block';
                                    accordionBackgroundTasksElement.querySelector('.delete-all-wrapper').style.display = 'none';
                                }
                                self.checkLoadMore(columnSection);
                                self.updateDeleteAllButtonVisibility();
                            }
                            Modal.dismiss();
                        }
                    }, {
                        text: TYPO3.lang['AiSuite.backgroundTasks.deleteAbort'],
                        trigger: function() {
                            Modal.dismiss();
                        }
                    }
                ]);
            });
        });
    }

    Overview.prototype.addDeleteAllEventListener = function() {
        const self = this;
        document.querySelectorAll('.delete-all-accordion-items').forEach(function(element) {
            element.addEventListener('click', function(ev) {
                ev.preventDefault();
                let accordionBackgroundTasksElement = document.querySelector('#accordionBackgroundTasks' + self.activeView);
                let uuids = [];
                let columns = [];

                accordionBackgroundTasksElement.querySelectorAll('.accordion-item').forEach(function(item) {
                    uuids.push(item.dataset.uuid);
                    columns.push(item.dataset.column);
                });

                if (uuids.length === 0) {
                    return;
                }

                Modal.confirm('Warning', TYPO3.lang['AiSuite.backgroundTasks.deleteAllModalTitle'], Severity.warning, [
                    {
                        text: TYPO3.lang['AiSuite.backgroundTasks.deleteAllModalText'],
                        active: true,
                        trigger: async function() {
                            let res = await Ajax.sendAjaxRequest('aisuite_background_task_delete', {uuids: uuids, columns: columns});
                            if (General.isUsable(res)) {
                                Notification.success(res.count + TYPO3.lang['AiSuite.notification.deleteAllSuccess']);
                                accordionBackgroundTasksElement.querySelectorAll('.accordion-item').forEach(function(item) {
                                    item.remove();
                                });
                                accordionBackgroundTasksElement.querySelectorAll('.accordion-column').forEach(function(column) {
                                    column.remove();
                                });
                                accordionBackgroundTasksElement.querySelector('#noBackgroundTasks').style.display = 'block';
                                accordionBackgroundTasksElement.querySelector('.delete-all-wrapper').style.display = 'none';
                            }
                            Modal.dismiss();
                            document.querySelector('a.btn.btn-md[title="TaskEngine"]').click();
                        }
                    }, {
                        text: TYPO3.lang['AiSuite.backgroundTasks.deleteAbort'],
                        trigger: function() {
                            Modal.dismiss();
                        }
                    }
                ]);
            });
        });
    }

    Overview.prototype.addSaveEventListener = function() {
        const self = this;
        document.querySelectorAll('.save-accordion-item').forEach(function(element) {
            element.addEventListener('click', async function(ev) {
                ev.preventDefault();
                let accordionBackgroundTasksElement = ev.target.closest('.accordion-background-tasks');
                let uuid = ev.target.dataset.uuid;
                let column = ev.target.dataset.column;
                let inputValue = document.querySelector('.accordion-item[data-uuid="'+uuid+'"][data-column="'+column+'"] input.metadata-value').value;
                let title = document.querySelector('.accordion-item[data-uuid="'+uuid+'"][data-column="'+column+'"] .accordion-header .title').innerText;
                let res = await Ajax.sendAjaxRequest('aisuite_background_task_save', {uuid: uuid, column: column, inputValue: inputValue});
                if (General.isUsable(res)) {
                    Notification.success(TYPO3.lang['AiSuite.notification.saveSuccess'], inputValue + ' (' + title + ')');

                    const accordionItem = document.querySelector('.accordion-item[data-uuid="'+uuid+'"][data-column="'+column+'"]');
                    const columnSection = accordionItem.closest('.accordion-column');
                    accordionItem.remove();

                    if (columnSection && columnSection.querySelectorAll('.accordion-item').length === 0) {
                        columnSection.remove();
                    } else if (columnSection) {
                        const taskCount = columnSection.querySelectorAll('.accordion-item').length;
                        columnSection.querySelector('.column-task-count').textContent = taskCount;
                    }

                    if(accordionBackgroundTasksElement.querySelectorAll('.accordion-item').length === 0) {
                        accordionBackgroundTasksElement.querySelector('#noBackgroundTasks').style.display = 'block';
                        accordionBackgroundTasksElement.querySelector('.delete-all-wrapper').style.display = 'none';
                    } else {
                        const firstAccordionItem = accordionBackgroundTasksElement.querySelector('.accordion-item');
                        if(firstAccordionItem) {
                            firstAccordionItem.querySelector('.open-accordion-item')?.click();
                        }
                    }
                    self.checkLoadMore(columnSection);
                    self.updateDeleteAllButtonVisibility();
                }
            });
        });
    }

    Overview.prototype.selectionHandler = function() {
        const self = this;
        document.querySelectorAll('label.ce-metadata-selection').forEach(function(element) {
            element.addEventListener('click', function(ev) {
                let selectionGroup = ev.target.dataset.selectionGroup;
                let selectionId = ev.target.dataset.selectionId;
                document.querySelectorAll('input[data-selection-group="'+selectionGroup+'"]').forEach(function(inputField) {
                    if(inputField.id !== selectionId) {
                        inputField.checked = false;
                    }
                });
                let metadataValueElement = ev.target.closest('.accordion-body').querySelector('.metadata-value');
                metadataValueElement.value = element.innerText.trim();
                if(self.clickAndSave) {
                    const accordionItem = metadataValueElement.closest('.accordion-item');
                    accordionItem.querySelector('.save-accordion-item').click();
                }
            });
        });
    }

    Overview.prototype.addInfoWindowEventListener = function() {
        document.querySelectorAll('.page-meta-content-info, .file-reference-meta-content-info, .file-meta-content-info').forEach(function(element) {
            element.addEventListener('click', function(ev) {
                ev.preventDefault();
                const table = ev.target.dataset.table;
                const uid = ev.target.dataset.uid;
                InfoWindow.showItem(table, uid);
            });
        });
    }

    Overview.prototype.addClickAndSaveEventListener = function() {
        const self = this;
        document.querySelector('#clickAndSave').addEventListener('click', function() {
            self.clickAndSave = !self.clickAndSave;
            // self.saveClickAndSaveState(self.clickAndSave);
        });
        document.querySelector('#clickAndSaveInfo .info-icon').addEventListener('click', function() {
            Modal.confirm('Info', TYPO3.lang['AiSuite.module.massAction.clickAndSave.tooltip'], Severity.info, [
                {
                    text: 'OK',
                    active: true,
                    trigger: function() {
                        Modal.dismiss();
                    }
                }
            ]);
        });
    }

    Overview.prototype.updateDeleteAllButtonVisibility = function() {
        this.views.forEach(function(view) {
            const accordionElement = document.querySelector('#accordionBackgroundTasks' + view);
            if (!accordionElement) return;

            const deleteAllWrapper = accordionElement.querySelector('.delete-all-wrapper');
            if (!deleteAllWrapper) return;

            if (accordionElement.querySelectorAll('.accordion-item').length > 0) {
                deleteAllWrapper.style.display = 'flex';
            } else {
                deleteAllWrapper.style.display = 'none';
            }
        });
    }

    Overview.prototype.addErrorTooltipEventListener = function() {
        const self = this;
        document.querySelectorAll('.accordion-header .error-info').forEach(function(element) {
            element.style.cursor = 'pointer';
            element.addEventListener('click', function(ev) {
                ev.preventDefault();
                const errorMessage = element.querySelector('.error-message').textContent;
                const accordionItem = element.closest('.accordion-item');
                const uuid = accordionItem.dataset.uuid;
                const column = accordionItem.dataset.column;

                Modal.confirm(TYPO3.lang['AiSuite.errorDetails.title'] || 'Error Details', errorMessage, Severity.error, [
                    {
                        text: TYPO3.lang['AiSuite.errorDetails.retry'] || 'Retry',
                        active: true,
                        btnClass: 'btn-warning',
                        trigger: async function() {
                            Modal.dismiss();
                            Notification.info(
                                TYPO3.lang['AiSuite.errorDetails.retryingTask'] || 'Retrying task...',
                                TYPO3.lang['AiSuite.errorDetails.pleaseWait'] || 'Please wait'
                            );

                            let res = await Ajax.sendAjaxRequest('aisuite_background_task_retry', {uuid: uuid, column: column});
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
                        text: TYPO3.lang['AiSuite.errorDetails.delete'] || 'Delete',
                        btnClass: 'btn-danger',
                        trigger: async function() {
                            Modal.dismiss();

                            let res = await Ajax.sendAjaxRequest('aisuite_background_task_delete', {uuids: [uuid], columns: [column]});
                            if (General.isUsable(res)) {
                                Notification.success(TYPO3.lang['AiSuite.notification.deleteSuccess'] || 'Task deleted successfully');

                                const columnSection = accordionItem.closest('.accordion-column');
                                accordionItem.remove();

                                if (columnSection && columnSection.querySelectorAll('.accordion-item').length === 0) {
                                    columnSection.remove();
                                } else if (columnSection) {
                                    const taskCount = columnSection.querySelectorAll('.accordion-item').length;
                                    columnSection.querySelector('.column-task-count').textContent = taskCount;
                                }

                                const accordionBackgroundTasksElement = document.querySelector('#accordionBackgroundTasks' + self.activeView);
                                if(accordionBackgroundTasksElement.querySelectorAll('.accordion-item').length === 0) {
                                    accordionBackgroundTasksElement.querySelector('#noBackgroundTasks').style.display = 'block';
                                    accordionBackgroundTasksElement.querySelector('.delete-all-wrapper').style.display = 'none';
                                }
                                self.updateDeleteAllButtonVisibility();
                            } else {
                                Notification.error(
                                    TYPO3.lang['AiSuite.errorDetails.deleteFailed'] || 'Delete failed',
                                    res.error || 'An unknown error occurred'
                                );
                            }
                        }
                    },
                    {
                        text: TYPO3.lang['AiSuite.errorDetails.close'] || 'Close',
                        trigger: function() {
                            Modal.dismiss();
                        }
                    }
                ]);
            });
        });
    }

    Overview.prototype.addRefreshAndReloadButtonEventListener = function() {
        const self = this;
        document.querySelectorAll('.refresh-tasks, .load-more-tasks').forEach(function(element) {
            element.addEventListener('click', function () {
                self.updateBackgroundTaskUrl();
                document.querySelector('a.btn.btn-md[title="TaskEngine"]').click();
            });
        });
    }
    Overview.prototype.updateBackgroundTaskUrl = function() {
        const filterSelect = document.querySelector('#backgroundTaskFilter');
        const taskEngineButton = document.querySelector('a.btn.btn-md[title="TaskEngine"]');
        if (taskEngineButton) {
            const newUrl = new URL(window.location.href);
            newUrl.searchParams.set('backgroundTaskFilter', filterSelect.value);
            const clickAndSaveValue = this.clickAndSave ? '1' : '0';
            newUrl.searchParams.set('clickAndSave', clickAndSaveValue);
            taskEngineButton.setAttribute('href', newUrl.toString());
            window.history.replaceState({}, '', newUrl.toString());
        }
    }

    Overview.prototype.checkLoadMore = function(columnSection) {
        const loadMoreButton = columnSection.querySelector('button.load-more-tasks');
        setTimeout(() => {
            if (columnSection && columnSection.querySelectorAll('.accordion-item').length === 0 && loadMoreButton) {
                loadMoreButton.click();
            }
        }, 300);
    }

    // Return a new instance
    return new Overview();
});
