import Modal from '@typo3/backend/modal.js';
import Severity from '@typo3/backend/severity.js';

class ManageCustomPromptTemplates {
    constructor() {
        this.addDeleteEventListener();
    }

    addDeleteEventListener() {
        document.querySelectorAll('.delete-prompt-template').forEach(function(element) {
            element.addEventListener('click', function(ev) {
                ev.preventDefault();
                const deleteUrl = ev.target.closest('a').href;
                const templateName = ev.target.closest('a').dataset.templateName;
                const modalText = (TYPO3.lang['AiSuite.promptTemplate.deleteModalText'] || 'Are you sure you want to delete the prompt template "{0}"?').replace('{0}', templateName);

                Modal.confirm(
                    TYPO3.lang['AiSuite.promptTemplate.deleteModalTitle'] || 'Warning',
                    modalText,
                    Severity.warning,
                    [
                        {
                            text: TYPO3.lang['AiSuite.promptTemplate.deleteConfirm'] || 'Delete',
                            active: true,
                            btnClass: 'btn-danger',
                            trigger: function() {
                                window.location.href = deleteUrl;
                                Modal.dismiss();
                            }
                        },
                        {
                            text: TYPO3.lang['AiSuite.promptTemplate.deleteCancel'] || 'Cancel',
                            trigger: function() {
                                Modal.dismiss();
                            }
                        }
                    ]
                );
            });
        });
    }
}

export default new ManageCustomPromptTemplates();
