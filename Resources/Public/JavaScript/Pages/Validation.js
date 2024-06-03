define([
    "TYPO3/CMS/Backend/Notification",
    "TYPO3/CMS/AiSuite/Helper/General",
    "TYPO3/CMS/AiSuite/Helper/Generation",
    "TYPO3/CMS/AiSuite/Helper/Sortable",
], function(Notification, General, Generation, Sortable) {

    addEventListener();
    function addEventListener() {
        addEventListenerGeneratePageStructure();
        Generation.addFormSubmitEventListener('tx_aisuite_web_aisuiteaisuite[input][plainPrompt]');
    }

    function addEventListenerGeneratePageStructure() {
        // generate array out of sortable items and submit form
        let pageStructureSubmitButton = document.querySelector('div[data-module-id="aiSuite"] form.page-structure-create span.submit-page-structure');
        if (General.isUsable(pageStructureSubmitButton)) {
            pageStructureSubmitButton.addEventListener('click', function (event) {
                event.preventDefault();
                let sortableItems = Array.from(document.querySelectorAll('div[data-module-id="aiSuite"] .sortable-wrap > .nested-sortable > .list-group-item'));
                let result = Sortable.findItemsInSortable(sortableItems);
                document.querySelector('input[name="tx_aisuite_web_aisuiteaisuite[selectedPageTreeContent]"]').value = JSON.stringify(result);
                let selectedPage = document.querySelector('form.page-structure-create input.searchableInputProperty[name="tx_aisuite_web_aisuiteaisuite[input][startStructureFromPid]"]').value;
                if(selectedPage === '0') {
                    Notification.warning(TYPO3.lang['aiSuite.module.notification.modal.noSelectedPageTitle'], TYPO3.lang['aiSuite.module.notification.modal.noSelectedPageMessage'], 8);
                } else {
                    Generation.showFormSpinner();
                    document.querySelector('div[data-module-id="aiSuite"] form.page-structure-create').submit();
                }
            });
        }
    }
});


