import Modal from '@typo3/backend/modal.js';
import Severity from '@typo3/backend/severity.js';

class Overview {
    constructor() {
        this.addDeleteEventListener();
    }

    addDeleteEventListener() {
        document.querySelectorAll('.delete-global-instruction').forEach(function(element) {
            element.addEventListener('click', function(ev) {
                ev.preventDefault();
                const deleteUrl = ev.target.closest('a').href;
                const instructionName = ev.target.closest('a').dataset.instructionName;
                const modalText = (TYPO3.lang['AiSuite.globalInstructions.deleteModalText'] || 'Are you sure you want to delete the global instruction "{0}"?').replace('{0}', instructionName);

                Modal.confirm(
                    TYPO3.lang['AiSuite.globalInstructions.deleteModalTitle'] || 'Warning',
                    modalText,
                    Severity.warning,
                    [
                        {
                            text: TYPO3.lang['AiSuite.globalInstructions.deleteConfirm'] || 'Delete',
                            active: true,
                            btnClass: 'btn-danger',
                            trigger: function() {
                                window.location.href = deleteUrl;
                                Modal.dismiss();
                            }
                        },
                        {
                            text: TYPO3.lang['AiSuite.globalInstructions.deleteCancel'] || 'Cancel',
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

export default new Overview();
