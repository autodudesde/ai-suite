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

    addViewEventListener() {
        const self = this;
        let viewUpdated = false;
        this.views.forEach(function(view) {
            if(document.querySelector('#accordionBackgroundTasks' + view).querySelectorAll('.accordion-item').length > 0 && !viewUpdated) {
                self.activeView = view;
                viewUpdated = true;
            }
        });
        if(this.activeView === 'Page' && document.querySelector('#accordionBackgroundTasksPage').querySelectorAll('.accordion-item').length === 0) {
            document.querySelector('#accordionBackgroundTasks' + this.activeView).style.display = 'block';
            document.querySelector('.accordion-background-tasks').querySelector('#noBackgroundTasks').style.display = 'block';
        } else {
            document.querySelector('#backgroundTaskFilter').value = this.activeView;
            document.querySelector('#accordionBackgroundTasks' + this.activeView).style.display = 'block';
            document.querySelector('#accordionBackgroundTasks' + this.activeView).querySelector('#noBackgroundTasks').style.display = 'none';
            document.querySelector('#accordionBackgroundTasks' + this.activeView + ' .accordion-item .open-accordion-item')?.click();
        }
        this.updateDeleteAllButtonVisibility();
    }

    addFilterEventListener() {
        const self = this;
        document.querySelector('#backgroundTaskFilter').addEventListener('change', function(ev) {
            let filterValue = ev.target.value;
            document.querySelectorAll('.accordion-background-tasks').forEach(function(element) {
                if(element.dataset.type === filterValue) {
                    element.style.display = 'block';
                    if(element.querySelectorAll('.accordion-item').length === 0) {
                        element.querySelector('#noBackgroundTasks').style.display = 'block';
                        element.querySelector('.delete-all-wrapper').style.display = 'none';
                    } else {
                        const firstAccordionItem = element.querySelector('.accordion-item');
                        if(firstAccordionItem.querySelector('button.accordion-button').classList.contains('collapsed')) {
                            firstAccordionItem.querySelector('.open-accordion-item')?.click();
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

    addDeleteEventListener() {
        const self = this;
        document.querySelectorAll('.delete-accordion-item').forEach(function(element) {
            element.addEventListener('click', function(ev) {
                ev.preventDefault();
                let accordionBackgroundTasksElement = ev.target.closest('.accordion-background-tasks');
                let uuid = ev.target.dataset.uuid;
                Modal.confirm('Warning', TYPO3.lang['AiSuite.backgroundTasks.deleteModalTitle'], Severity.warning, [
                    {
                        text: TYPO3.lang['AiSuite.backgroundTasks.deleteModalText'],
                        active: true,
                        trigger: async function() {
                            let res = await Ajax.sendAjaxRequest('aisuite_background_task_delete', {uuids: [uuid]});
                            if (General.isUsable(res)) {
                                Notification.success(TYPO3.lang['AiSuite.notification.deleteSuccess']);
                                document.querySelector('.accordion-item[data-uuid="'+uuid+'"]').remove();
                                if(accordionBackgroundTasksElement.querySelectorAll('.accordion-item').length === 0) {
                                    accordionBackgroundTasksElement.querySelector('#noBackgroundTasks').style.display = 'block';
                                    accordionBackgroundTasksElement.querySelector('.delete-all-wrapper').style.display = 'none';
                                }
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

                accordionBackgroundTasksElement.querySelectorAll('.accordion-item').forEach(function(item) {
                    uuids.push(item.dataset.uuid);
                });

                if (uuids.length === 0) {
                    return;
                }

                Modal.confirm('Warning', TYPO3.lang['AiSuite.backgroundTasks.deleteAllModalTitle'], Severity.warning, [
                    {
                        text: TYPO3.lang['AiSuite.backgroundTasks.deleteAllModalText'],
                        active: true,
                        trigger: async function() {
                            let res = await Ajax.sendAjaxRequest('aisuite_background_task_delete', {uuids: uuids});
                            if (General.isUsable(res)) {
                                Notification.success(res.count + TYPO3.lang['AiSuite.notification.deleteAllSuccess']);
                                accordionBackgroundTasksElement.querySelectorAll('.accordion-item').forEach(function(item) {
                                    item.remove();
                                });
                                document.querySelector('a.btn.btn-md[title="TaskEngine"]').click();
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

    addSaveEventListener() {
        const self = this;
        document.querySelectorAll('.save-accordion-item').forEach(function(element) {
            element.addEventListener('click', async function(ev) {
                ev.preventDefault();
                let accordionBackgroundTasksElement = ev.target.closest('.accordion-background-tasks');
                let uuid = ev.target.dataset.uuid;
                let inputValue = document.querySelector('.accordion-item[data-uuid="'+uuid+'"] input.metadata-value').value;
                let title = document.querySelector('.accordion-item[data-uuid="'+uuid+'"] .accordion-header .title').innerText;
                let res = await Ajax.sendAjaxRequest('aisuite_background_task_save', {uuid: uuid, inputValue: inputValue});
                if (General.isUsable(res)) {
                    Notification.success(TYPO3.lang['AiSuite.notification.saveSuccess'], inputValue + ' (' + title + ')');
                    document.querySelector('.accordion-item[data-uuid="'+uuid+'"]').remove();
                    if(accordionBackgroundTasksElement.querySelectorAll('.accordion-item').length === 0) {
                        accordionBackgroundTasksElement.querySelector('#noBackgroundTasks').style.display = 'block';
                        accordionBackgroundTasksElement.querySelector('.delete-all-wrapper').style.display = 'none';
                    } else {
                        accordionBackgroundTasksElement
                            .querySelector('.accordion-item')
                            .querySelector('.open-accordion-item').click();
                        accordionBackgroundTasksElement.querySelector('.accordion-item .open-accordion-item')?.click();
                    }
                    self.updateDeleteAllButtonVisibility();
                }
            });
        });
    }

    selectionHandler() {
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
                    metadataValueElement
                        .closest('.accordion-item')
                        .querySelector('.save-accordion-item').click();
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

    updateDeleteAllButtonVisibility() {
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
    addErrorTooltipEventListener() {
        const self = this;
        document.querySelectorAll('.accordion-header .error-info').forEach(function(element) {
            element.style.cursor = 'pointer';
            element.addEventListener('click', function(ev) {
                ev.preventDefault();
                const errorMessage = element.querySelector('.error-message').textContent;
                const accordionItem = element.closest('.accordion-item');
                const uuid = accordionItem.dataset.uuid;

                Modal.confirm(TYPO3.lang['AiSuite.errorDetails.title'] || 'Error Details', errorMessage, Severity.error, [
                    {
                        text: TYPO3.lang['AiSuite.errorDetails.retry'] || 'Retry',
                        active: true,
                        btnClass: 'btn-warning',
                        trigger: async function() {
                            Modal.dismiss();
                            // Show loading spinner
                            Notification.info(
                                TYPO3.lang['AiSuite.errorDetails.retryingTask'] || 'Retrying task...',
                                TYPO3.lang['AiSuite.errorDetails.pleaseWait'] || 'Please wait'
                            );

                            let res = await Ajax.sendAjaxRequest('aisuite_background_task_retry', {uuid: uuid});
                            if (General.isUsable(res)) {
                                Notification.success(TYPO3.lang['AiSuite.errorDetails.retrySuccess'] || 'Task has been queued for retry');
                                // Optionally reload the accordion item or refresh the list
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

                            let res = await Ajax.sendAjaxRequest('aisuite_background_task_delete', {uuid: uuid});
                            if (General.isUsable(res)) {
                                Notification.success(TYPO3.lang['AiSuite.notification.deleteSuccess'] || 'Task deleted successfully');
                                accordionItem.remove();
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
    addRefreshAndReloadButtonEventListener() {
        document.querySelectorAll('.refresh-tasks, .load-more-tasks').forEach(function(element) {
            element.addEventListener('click', function () {
                document.querySelector('a.btn.btn-md[title="TaskEngine"]').click();
            });
        });
    }
}
export default new Overview();
