import General from "@autodudes/ai-suite/helper/general.js";
import Ajax from '@autodudes/ai-suite/helper/ajax.js';
import Modal from '@typo3/backend/modal.js';
import Notification from "@typo3/backend/notification.js";
import Severity from "@typo3/backend/severity.js";
import InfoWindow from "@typo3/backend/info-window.js";

class Overview {

    views;
    clickAndSave;
    activeView;

    constructor() {
        this.views = ['Page', 'FileReference', 'FileMetadata'];
        this.clickAndSave = false;
        this.activeView = 'Page';

        this.selectionHandler();
        this.addFilterEventListener();
        this.addDeleteEventListener();
        this.addDeleteAllEventListener();
        this.addSaveEventListener();
        this.addViewEventListener();
        this.addInfoWindowEventListener();
        this.addClickAndSaveEventListener();
        this.addErrorTooltipEventListener();
        this.updateDeleteAllButtonVisibility();
        this.addRefreshAndReloadButtonEventListener();
    }

    addViewEventListener() {
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
        const accordionItems = document.querySelectorAll('#accordionBackgroundTasks' + this.activeView + ' .panel').length || 0;
        document.querySelector('#accordionBackgroundTasks' + this.activeView).style.display = 'block';
        const noBackgroundTasks = document.querySelector('#accordionBackgroundTasks' + this.activeView + ' #noBackgroundTasks');
        if(noBackgroundTasks) {
            if (accordionItems === 0) {
                noBackgroundTasks.style.display = 'block';
            } else {
                noBackgroundTasks.style.display = 'none';
            }
        }
        const firstAccordionItem = document.querySelector('#accordionBackgroundTasks' + this.activeView + ' .panel .open-accordion-item');
        if (firstAccordionItem) {
            firstAccordionItem.click();
        }
        this.updateDeleteAllButtonVisibility();
    }

    addFilterEventListener() {
        const self = this;
        document.querySelector('#backgroundTaskFilter').addEventListener('change', function(ev) {
            let filterValue = ev.target.value;
            self.updateBackgroundTaskUrl();
            document.querySelectorAll('.background-tasks-wrapper').forEach(function(element) {
                if(element.dataset.type === filterValue) {
                    element.style.display = 'block';
                    let accordionItems = element.querySelectorAll('.panel').length || 0;
                    if(accordionItems === 0) {
                        element.querySelector('#noBackgroundTasks').style.display = 'block';
                        element.querySelector('.action-buttons-wrapper').style.display = 'none';
                    } else {
                        const firstAccordionItem = element.querySelector('.panel');
                        if(firstAccordionItem && firstAccordionItem.querySelector('button.panel-button').classList.contains('collapsed')) {
                            const openButton = firstAccordionItem.querySelector('.open-accordion-item');
                            if (openButton) {
                                openButton.click();
                            }
                        }
                        element.querySelector('#noBackgroundTasks').style.display = 'none';
                        element.querySelector('.action-buttons-wrapper').style.display = 'flex';
                    }
                } else {
                    element.style.display = 'none';
                }
            });
            self.activeView = filterValue;
        });
    }

    addDeleteEventListener() {
        const self = this;
        document.querySelectorAll('.delete-accordion-item').forEach(function(element) {
            element.addEventListener('click', function(ev) {
                ev.preventDefault();
                let accordionBackgroundTasksElement = ev.target.closest('.background-tasks-wrapper');
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

                                const accordionItem = document.querySelector('.panel[data-uuid="'+uuid+'"][data-column="'+column+'"]');
                                const columnSection = accordionItem.closest('.panel-body');
                                accordionItem.remove();

                                if (columnSection && columnSection.querySelectorAll('.panel').length === 0) {
                                    columnSection.closest('.panel').remove();
                                } else if (columnSection) {
                                    const taskCount = columnSection.querySelectorAll('.panel').length;
                                    columnSection.closest('.panel').querySelector('.column-task-count').textContent = taskCount;
                                }

                                if(accordionBackgroundTasksElement.querySelectorAll('.panel').length === 0) {
                                    accordionBackgroundTasksElement.querySelector('#noBackgroundTasks').style.display = 'block';
                                    accordionBackgroundTasksElement.querySelector('.action-buttons-wrapper').style.display = 'none';
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

    addDeleteAllEventListener() {
        const self = this;
        document.querySelectorAll('.delete-all-accordion-items').forEach(function(element) {
            element.addEventListener('click', function(ev) {
                ev.preventDefault();
                let accordionBackgroundTasksElement = document.querySelector('#accordionBackgroundTasks' + self.activeView);
                let uuids = [];
                let columns = [];

                accordionBackgroundTasksElement.querySelectorAll('.panel').forEach(function(item) {
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
                                accordionBackgroundTasksElement.querySelectorAll('.panel').forEach(function(item) {
                                    item.remove();
                                });
                                accordionBackgroundTasksElement.querySelector('#noBackgroundTasks').style.display = 'block';
                                accordionBackgroundTasksElement.querySelector('.action-buttons-wrapper').style.display = 'none';
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

    addSaveEventListener() {
        const self = this;
        document.querySelectorAll('.save-accordion-item').forEach(function(element) {
            element.addEventListener('click', async function(ev) {
                ev.preventDefault();
                let accordionBackgroundTasksElement = ev.target.closest('.background-tasks-wrapper');
                let uuid = ev.target.dataset.uuid;
                let column = ev.target.dataset.column;
                let inputValue = document.querySelector('.panel[data-uuid="'+uuid+'"] .panel-body[data-column="'+column+'"] input.metadata-value').value;
                if(inputValue.trim() === '') {
                    Notification.warning(TYPO3.lang['aiSuite.module.notification.modal.noMetadataInputTitle'], TYPO3.lang['aiSuite.module.notification.modal.noMetadataInputMessage']);
                    return;
                }
                let title = document.querySelector('.panel[data-uuid="'+uuid+'"][data-column="'+column+'"] .task-title').innerText;
                let res = await Ajax.sendAjaxRequest('aisuite_background_task_save', {uuid: uuid, column: column, inputValue: inputValue});
                if (General.isUsable(res)) {
                    Notification.success(TYPO3.lang['AiSuite.notification.saveSuccess'], inputValue + ' (' + title + ')');

                    const accordionItem = document.querySelector('.panel[data-uuid="'+uuid+'"][data-column="'+column+'"]');
                    const columnSection = accordionItem.closest('.panel-body');
                    accordionItem.remove();

                    if (columnSection && columnSection.querySelectorAll('.panel').length === 0) {
                        columnSection.closest('.panel').remove();
                    } else if (columnSection) {
                        const taskCount = columnSection.querySelectorAll('.panel').length;
                        columnSection.closest('.panel').querySelector('.column-task-count').textContent = taskCount;
                    }

                    if(accordionBackgroundTasksElement.querySelectorAll('.panel').length === 0) {
                        accordionBackgroundTasksElement.querySelector('#noBackgroundTasks').style.display = 'block';
                        accordionBackgroundTasksElement.querySelector('.action-buttons-wrapper').style.display = 'none';
                    } else {
                        const firstAccordionItem = accordionBackgroundTasksElement.querySelector('.panel');
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

    selectionHandler() {
        const self = this;
        document.querySelectorAll('input.metadata-selection').forEach(function(radioInput) {
            radioInput.addEventListener('change', function(ev) {
                const groupName = ev.target.name;

                document.querySelectorAll(`input[name="${groupName}"]`).forEach(function(inputField) {
                    const formCheck = inputField.closest('.form-check');
                    if (formCheck) {
                        formCheck.classList.remove('checked');
                    }
                });

                const selectedFormCheck = ev.target.closest('.form-check');
                if (selectedFormCheck) {
                    selectedFormCheck.classList.add('checked');
                }

                let metadataValueElement = ev.target.closest('.panel-body').querySelector('.metadata-value');
                const suggestionText = ev.target.closest('label').querySelector('.form-check-label').textContent.trim();
                metadataValueElement.value = suggestionText;

                if(self.clickAndSave) {
                    const accordionItem = metadataValueElement.closest('.panel');
                    accordionItem.querySelector('.save-accordion-item').click();
                }
            });
        });
    }

    addInfoWindowEventListener() {
        document.querySelectorAll('.page-meta-content-info, .file-reference-meta-content-info, .file-meta-content-info').forEach(function(element) {
            element.addEventListener('click', function(ev) {
                ev.preventDefault();
                const table = ev.target.dataset.table;
                const uid = ev.target.dataset.uid;
                InfoWindow.showItem(table, uid);
            });
        });
    }

    addClickAndSaveEventListener() {
        const self = this;
        document.querySelector('#clickAndSave').addEventListener('click', function() {
            self.clickAndSave = !self.clickAndSave;
        });
    }

    updateDeleteAllButtonVisibility() {
        this.views.forEach(function(view) {
            const accordionElement = document.querySelector('#accordionBackgroundTasks' + view);
            if (!accordionElement) return;

            const deleteAllWrapper = accordionElement.querySelector('.action-buttons-wrapper');
            if (!deleteAllWrapper) return;

            if (accordionElement.querySelectorAll('.panel').length > 0) {
                deleteAllWrapper.style.display = 'flex';
            } else {
                deleteAllWrapper.style.display = 'none';
            }
        });
    }

    addErrorTooltipEventListener() {
        const self = this;
        document.querySelectorAll('.panel-heading .error-info').forEach(function(element) {
            element.style.cursor = 'pointer';
            element.addEventListener('click', function(ev) {
                ev.preventDefault();
                const errorMessage = element.querySelector('.error-message').textContent;
                const accordionItem = element.closest('.panel');
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

                            let res = await Ajax.sendAjaxRequest('aisuite_background_task_retry', {uuid: uuid});
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

                                const columnSection = accordionItem.closest('.panel-body');
                                accordionItem.remove();

                                if (columnSection && columnSection.querySelectorAll('.panel').length === 0) {
                                    columnSection.closest('.panel').remove();
                                } else if (columnSection) {
                                    const taskCount = columnSection.querySelectorAll('.panel').length;
                                    columnSection.closest('.panel').querySelector('.column-task-count').textContent = taskCount;
                                }

                                const accordionBackgroundTasksElement = document.querySelector('#accordionBackgroundTasks' + self.activeView);
                                if(accordionBackgroundTasksElement.querySelectorAll('.panel').length === 0) {
                                    accordionBackgroundTasksElement.querySelector('#noBackgroundTasks').style.display = 'block';
                                    accordionBackgroundTasksElement.querySelector('.action-buttons-wrapper').style.display = 'none';
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

    addRefreshAndReloadButtonEventListener() {
        const self = this;
        document.querySelectorAll('.refresh-tasks, .load-more-tasks').forEach(function(element) {
            element.addEventListener('click', function () {
                self.updateBackgroundTaskUrl();
                document.querySelector('a.btn.btn-md[title="TaskEngine"]').click();
            });
        });
    }
    updateBackgroundTaskUrl() {
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

    checkLoadMore(columnSection) {
        const loadMoreButton = columnSection.querySelector('button.load-more-tasks');
        setTimeout(() => {
            if (columnSection && columnSection.querySelectorAll('.panel').length === 0 && loadMoreButton) {
                loadMoreButton.click();
            }
        }, 300);
    }
}
export default new Overview();
