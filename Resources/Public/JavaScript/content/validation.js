import ContentElement from "@autodudes/ai-suite/helper/content-element.js";
import Generation from "@autodudes/ai-suite/helper/generation.js";
import Ajax from "@autodudes/ai-suite/helper/ajax.js";
import ResponseHandling from "@autodudes/ai-suite/helper/image/response-handling.js";

class Validation {
    constructor() {
        this.init();
        this.addEventListenerFormSubmit();
        this.preselectionEventListener();
    }
    init() {
        ContentElement.addValidationEventListener('.create-content-update-image');
        ContentElement.addValidationEventListener('.create-image-amount-exceeded');
        ContentElement.addValidationEventDelegation();
    }

    addEventListenerFormSubmit() {
        document.querySelectorAll('form[name="requestContent"]').forEach(function(form) {
            form.addEventListener('submit', function(ev) {
                ev.preventDefault();

                let imageFieldsWithoutSelection = '';
                const checkboxInputs = document.querySelectorAll('input[type="checkbox"].image-selection');
                const groupsCheckedStatus = new Map();

                checkboxInputs.forEach(input => {
                    if (input.type === 'checkbox') {
                        let position = parseInt(input.getAttribute('data-position'));
                        let group = input.getAttribute('data-fieldname');
                        position++;
                        if(input.hasAttribute('data-itemlabel') && input.getAttribute('data-itemlabel') !== '') {
                            group = input.getAttribute('data-itemlabel') + ' ' + position + ': ' + input.getAttribute('data-fieldname');
                        }
                        if (!groupsCheckedStatus.has(group)) {
                            groupsCheckedStatus.set(group, false);
                        }
                        if (input.checked) {
                            groupsCheckedStatus.set(group, true);
                        }
                    }
                });
                groupsCheckedStatus.forEach(function(checked, key) {
                    if(!checked) {
                        imageFieldsWithoutSelection += key + ', ';
                    }
                })
                let rteTextFields = document.querySelectorAll('.rte-textarea');
                rteTextFields.forEach(function(rte) {
                    let rteFieldIdentifier = rte.getAttribute('data-field-identifier');
                    let rteContent = rte.querySelector('typo3-rte-ckeditor-ckeditor5 textarea').innerHTML;
                    let rteContentField = document.querySelector('input.rte-content[name="' + rteFieldIdentifier + '"]');
                    rteContentField.value = rteContent;
                });
                if(imageFieldsWithoutSelection !== '') {
                    ContentElement.showImageFieldsWithoutSelectionModal(imageFieldsWithoutSelection, form);
                } else {
                    Generation.showSpinner();
                    form.submit();
                }
            });
        });
    }
    preselectionEventListener() {
        let componentButtons = document.querySelectorAll('.image-preselection .component-button');
        if (componentButtons.length > 0) {
            componentButtons.forEach(function(button) {
                button.addEventListener('click', async function (ev) {
                    ev.preventDefault();
                    let data = {
                        customId: ev.target.getAttribute('data-custom-id'),
                        mId: ev.target.getAttribute('data-m-id'),
                        index: ev.target.getAttribute('data-index'),
                        imagePrompt: ev.target.getAttribute('data-prompt'),
                        imageAiModel: 'Midjourney',
                        pageId: ev.target.getAttribute('data-page-id'),
                        table: ev.target.getAttribute('data-table'),
                        fieldName: ev.target.getAttribute('data-fieldname'),
                        position: ev.target.getAttribute('data-position'),
                        uuid: ev.target.getAttribute('data-uuid')
                    }
                    let preselectionContent = '';
                    if(data.table === 'tt_content') {
                        preselectionContent = document.querySelector('form[name="requestContent"] #fields-' + data.table + ' #generated-images-' + data.fieldName).innerHTML;
                        document.querySelector('form[name="requestContent"] #fields-' + data.table + ' #generated-images-' + data.fieldName).innerHTML = Generation.showSpinnerModal(TYPO3.lang['aiSuite.module.modal.imageGenerationInProcessMidjourney'], 705);
                    } else {
                        preselectionContent = document.querySelector('form[name="requestContent"] #fields-' + data.table +'-' + data.position + ' #generated-images-' + data.fieldName).innerHTML;
                        document.querySelector('form[name="requestContent"] #fields-' + data.table +'-' + data.position + ' #generated-images-' + data.fieldName).innerHTML = Generation.showSpinnerModal(TYPO3.lang['aiSuite.module.modal.imageGenerationInProcessMidjourney'], 705);
                    }
                    let res = await Ajax.sendAjaxRequest('aisuite_regenerate_images', data);
                    ResponseHandling.handleResponseContentElement(res, data, TYPO3.lang['aiSuite.module.modal.midjourneySelectionError'], preselectionContent);
                });
            });
        }
    }
}
export default new Validation();


