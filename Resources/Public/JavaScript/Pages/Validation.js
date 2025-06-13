define([
    'jquery',
    'TYPO3/CMS/Backend/Notification',
    'TYPO3/CMS/AiSuite/Helper/General',
    'TYPO3/CMS/AiSuite/Helper/Generation',
    'TYPO3/CMS/AiSuite/Helper/Sortable',
    'TYPO3/CMS/AiSuite/Helper/PromptTemplate'
], function($, Notification, General, Generation, Sortable, PromptTemplate) {
    'use strict';

    /**
     * Validation Constructor
     *
     * @constructor
     */
    function PagesValidation() {
        this.addEventListener();
    }

    /**
     * Add event listeners
     */
    PagesValidation.prototype.addEventListener = function() {
        this.addEventListenerGeneratePageStructure();
        Generation.addFormSubmitEventListener('plainPrompt');
        PromptTemplate.loadPromptTemplates('plainPrompt');
        Generation.languageSelectionEventListener();
    };

    /**
     * Add event listener for page structure generation
     */
    PagesValidation.prototype.addEventListenerGeneratePageStructure = function() {
        // generate array out of sortable items and submit form
        var pageStructureSubmitButton = document.querySelector('div[data-module-id="aiSuite"] form.page-structure-create span.submit-page-structure');
        if (General.isUsable(pageStructureSubmitButton)) {
            pageStructureSubmitButton.addEventListener('click', function (event) {
                event.preventDefault();
                var sortableItems = Array.from(document.querySelectorAll('div[data-module-id="aiSuite"] .sortable-wrap > .nested-sortable > .list-group-item'));
                var result = Sortable.findItemsInSortable(sortableItems);
                document.querySelector('input[name="selectedPageTreeContent"]').value = JSON.stringify(result);
                var selectedPage = document.querySelector('form.page-structure-create input.searchableInputProperty[name="startStructureFromPid"]').value;
                if(selectedPage === '') {
                    Notification.warning(TYPO3.lang['aiSuite.module.notification.modal.noSelectedPageTitle'], TYPO3.lang['aiSuite.module.notification.modal.noSelectedPageMessage'], 8);
                } else {
                    Generation.showSpinner();
                    document.querySelector('div[data-module-id="aiSuite"] form.page-structure-create').submit();
                }
            });
        }
    };

    // Return a new instance
    return new PagesValidation();
});
