define([
    "TYPO3/CMS/AiSuite/Helper/ContentElement",
    "TYPO3/CMS/AiSuite/Helper/Generation",
    "TYPO3/CMS/AiSuite/Helper/Ajax",
    "TYPO3/CMS/AiSuite/Helper/Image/ResponseHandling",
    "TYPO3/CMS/AiSuite/Helper/Image/GenerationHandling"
], function(
    ContentElement,
    Generation,
    Ajax,
    ResponseHandling,
    GenerationHandling
) {
    init();
    addEventListenerFormSubmit();
    preselectionEventListener();

    function init() {
        ContentElement.addValidationEventListener('.create-content-update-image');
        ContentElement.addValidationEventListener('.create-image-amount-exceeded');
        ContentElement.addValidationEventDelegation();
    }

    function addEventListenerFormSubmit() {
        document.querySelectorAll('form[name="tx_aisuite_web_aisuiteaisuite[content]"]').forEach(function(form) {
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
                if(imageFieldsWithoutSelection !== '') {
                    ContentElement.showImageFieldsWithoutSelectionModal(imageFieldsWithoutSelection, form);
                } else {
                    Generation.showFormSpinner();
                    form.submit();
                }
            });
        });
    }
    function preselectionEventListener() {
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
                        preselectionContent = document.querySelector('form[name="content"] #fields-' + data.table + ' #generated-images-' + data.fieldName).innerHTML;
                        document.querySelector('form[name="content"] #fields-' + data.table + ' #generated-images-' + data.fieldName).innerHTML = GenerationHandling.showSpinner(TYPO3.lang['aiSuite.module.modal.imageGenerationInProcessMidjourney']);
                    } else {
                        preselectionContent = document.querySelector('form[name="content"] #fields-' + data.table +'-' + data.position + ' #generated-images-' + data.fieldName).innerHTML;
                        document.querySelector('form[name="content"] #fields-' + data.table +'-' + data.position + ' #generated-images-' + data.fieldName).innerHTML = GenerationHandling.showSpinner(TYPO3.lang['aiSuite.module.modal.imageGenerationInProcessMidjourney']);
                    }
                    document.querySelector('form[name="content"] #fields-' + data.table + ' #generated-images-' + data.fieldName).innerHTML;
                    let res = await Ajax.sendAjaxRequest('aisuite_regenerate_images', data);
                    ResponseHandling.handleResponseContentElement(res, data, TYPO3.lang['aiSuite.module.modal.midjourneySelectionError'], preselectionContent);
                });
            });
        }
    }
});


