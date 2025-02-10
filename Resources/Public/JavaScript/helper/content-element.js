import Modal from "@typo3/backend/modal.js";
import Severity from "@typo3/backend/severity.js";
import Generation from "@autodudes/ai-suite/helper/generation.js";
import ImageGeneration from "@autodudes/ai-suite/helper/image/generation-handling.js";

class ContentElement {
    showImageFieldsWithoutSelectionModal(imageFieldsWithoutSelection, form) {
        Modal.confirm(
            'Warning',
            TYPO3.lang['aiSuite.module.modal.noSelectionForFields'] + imageFieldsWithoutSelection.slice(0, -2)  + '. ' + TYPO3.lang['aiSuite.module.modal.continue'],
            Severity.warning, [
                {
                    text: TYPO3.lang['aiSuite.module.modal.saveAnyway'],
                    active: true,
                    trigger: function() {
                        Generation.showSpinner();
                        form.submit();
                        Modal.dismiss();
                    }
                }, {
                    text: TYPO3.lang['aiSuite.module.modal.abort'],
                    trigger: function() {
                        Modal.dismiss();
                    }
                }
            ]);
    }
    addValidationEventListener(selector) {
        document.querySelectorAll(selector).forEach(function(button) {
            button.addEventListener('click', function(ev) {
                ev.preventDefault();
                let data = {
                    pageId: ev.target.getAttribute('data-page-id'),
                    languageId: ev.target.getAttribute('data-language-id'),
                    table: ev.target.getAttribute('data-table'),
                    position: ev.target.getAttribute('data-position'),
                    fieldName: ev.target.getAttribute('data-fieldname'),
                }
                ImageGeneration.showGeneralImageSettingsModal(data, 'ContentElement');
            });
        });
    }
    addValidationEventDelegation() {
        let checkDelegationFn = this.checkDelegation;
        document.querySelectorAll('div[data-module-id="aiSuite"] .t3js-module-body').forEach(function(element) {
            element.addEventListener("click", function(ev) {
                if(ev && ev.target) {
                    checkDelegationFn(ev.target, "IMG", "ce-image-selection");
                    checkDelegationFn(ev.target, "LABEL", "ce-image-title-selection");
                }
            });
        });
    }
    checkDelegation(target, nodeName, className) {
        if(target && target.nodeName === nodeName && target.classList.contains(className)) {
            let selectionGroup = target.getAttribute('data-selection-group');
            let selectionId = target.getAttribute('data-selection-id');
            if(document.getElementById(selectionId) === selectionId && document.getElementById(selectionId).checked) {
                document.getElementById(selectionId).checked = false;
            } else {
                document.querySelectorAll('input[data-selection-group="'+selectionGroup+'"]').forEach(function(input) {
                    if(input.id !== selectionId) {
                        input.checked = false;
                    }
                });
            }
        }
    }
}

export default new ContentElement();
