import General from "@autodudes/ai-suite/helper/general.js";
import Ajax from '@autodudes/ai-suite/helper/ajax.js';
import Modal from '@typo3/backend/modal.js';
import Notification from "@typo3/backend/notification.js";
import Severity from "@typo3/backend/severity.js";
import InfoWindow from "@typo3/backend/info-window.js";

class Overview {
    constructor() {
        this.selectionHandler();
        this.addDeleteEventListener();
        this.addSaveEventListener();
        this.addViewEventListener();
        this.addInfoWindowEventListener();
    }

    addViewEventListener() {
        document.querySelector('#accordionBackgroundTasksPage').style.display = 'block';
        if(document.querySelector('#accordionBackgroundTasksPage').querySelectorAll('.accordion-item').length === 0) {
            document.querySelector('.accordion-background-tasks').querySelector('#noBackgroundTasks').style.display = 'block';
        }
        document.querySelector('#backgroundTaskFilter').addEventListener('change', function(ev) {
            let filterValue = ev.target.value;
            document.querySelectorAll('.accordion-background-tasks').forEach(function(element) {
                if(element.dataset.type === filterValue) {
                    element.style.display = 'block';
                    if(element.querySelectorAll('.accordion-item').length === 0) {
                        element.querySelector('#noBackgroundTasks').style.display = 'block';
                    }
                } else {
                    element.style.display = 'none';
                    element.querySelector('#noBackgroundTasks').style.display = 'none';
                }
            });
        });
    }
    addDeleteEventListener() {
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
                            let res = await Ajax.sendAjaxRequest('aisuite_background_task_delete', {uuid: uuid});
                            if (General.isUsable(res)) {
                                Notification.success(TYPO3.lang['AiSuite.notification.deleteSuccess']);
                                document.querySelector('.accordion-item[data-uuid="'+uuid+'"]').remove();
                                if(accordionBackgroundTasksElement.querySelectorAll('.accordion-item').length === 0) {
                                    accordionBackgroundTasksElement.querySelector('#noBackgroundTasks').style.display = 'block';
                                }
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
        document.querySelectorAll('.save-accordion-item').forEach(function(element) {
            element.addEventListener('click', async function(ev) {
                ev.preventDefault();
                let accordionBackgroundTasksElement = ev.target.closest('.accordion-background-tasks');
                let uuid = ev.target.dataset.uuid;
                let inputValue = document.querySelector('.accordion-item[data-uuid="'+uuid+'"] input.metadata-value').value;
                let res = await Ajax.sendAjaxRequest('aisuite_background_task_save', {uuid: uuid, inputValue: inputValue});
                if (General.isUsable(res)) {
                    Notification.success(TYPO3.lang['AiSuite.notification.saveSuccess']);
                    document.querySelector('.accordion-item[data-uuid="'+uuid+'"]').remove();
                    if(accordionBackgroundTasksElement.querySelectorAll('.accordion-item').length === 0) {
                        accordionBackgroundTasksElement.querySelector('#noBackgroundTasks').style.display = 'block';
                    }
                }
            });
        });
    }
    selectionHandler() {
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
            });
        });
    }

    addInfoWindowEventListener() {
        document.querySelectorAll('.page-meta-content-info, .file-meta-content-info').forEach(function(element) {
            element.addEventListener('click', function(ev) {
                ev.preventDefault();
                const table = ev.target.dataset.table;
                const uid = ev.target.dataset.uid;
                InfoWindow.showItem(table, uid);
            });
        });
    }
}
export default new Overview();
