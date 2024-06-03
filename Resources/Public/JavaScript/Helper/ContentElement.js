define([
    "TYPO3/CMS/Backend/Modal",
    "TYPO3/CMS/Backend/Severity",
    "TYPO3/CMS/AiSuite/Helper/Generation",
    "TYPO3/CMS/AiSuite/Helper/Image/GenerationHandling",
], function(
    Modal,
    Severity,
    Generation,
    ImageGeneration
) {
    function showImageFieldsWithoutSelectionModal(imageFieldsWithoutSelection, form) {
        Modal.confirm(
            'Warning',
            TYPO3.lang['aiSuite.module.modal.noSelectionForFields'] + imageFieldsWithoutSelection.slice(0, -2)  + '. ' + TYPO3.lang['aiSuite.module.modal.continue'],
            Severity.warning, [
                {
                    text: TYPO3.lang['aiSuite.module.modal.saveAnyway'],
                    active: true,
                    trigger: function() {
                        Generation.showFormSpinner();
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
    function addValidationEventListener(selector) {
        document.querySelectorAll(selector).forEach(function(button) {
            button.addEventListener('click', function(ev) {
                ev.preventDefault();
                let data = {
                    pageId: ev.target.getAttribute('data-page-id'),
                    table: ev.target.getAttribute('data-table'),
                    position: ev.target.getAttribute('data-position'),
                    fieldName: ev.target.getAttribute('data-fieldname'),
                }
                ImageGeneration.showGeneralImageSettingsModal(data, 'ContentElement');
            });
        });
    }
    function addValidationEventDelegation() {
        document.querySelectorAll('div[data-module-id="aiSuite"] .t3js-module-body').forEach(function(element) {
            element.addEventListener("click", function(ev) {
                if(ev && ev.target) {
                    checkDelegation(ev.target, "IMG", "ce-image-selection");
                    checkDelegation(ev.target, "LABEL", "ce-image-title-selection");
                }
            });
        });
    }
    function checkDelegation(target, nodeName, className) {
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
    return {
        showImageFieldsWithoutSelectionModal: showImageFieldsWithoutSelectionModal,
        addValidationEventListener: addValidationEventListener,
        addValidationEventDelegation: addValidationEventDelegation,
    };
});
