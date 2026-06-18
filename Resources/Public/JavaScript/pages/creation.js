import Generation from "@autodudes/ai-suite/helper/generation.js";
import PromptTemplate from "@autodudes/ai-suite/helper/prompt-template.js";
import GlobalInstructions from "@autodudes/ai-suite/helper/global-instructions.js";
import Notification from "@typo3/backend/notification.js";
import General from "@autodudes/ai-suite/helper/general.js";

class Creation {
    constructor() {
        Generation.cancelGeneration();
        this.addFormSubmitEventListener('plainPrompt');
        PromptTemplate.loadPromptTemplates('plainPrompt');
        Generation.languageSelectionEventListener();
        this.addGlobalInstructionEventListener().then();
    }

    addFormSubmitEventListener(promptInputName) {
        let formsWithSpinner = Array.from(document.querySelectorAll('div[data-module-id="aiSuite"] form.with-spinner'));
        let spinnerOverlay = document.querySelector('div[data-module-id="aiSuite"] .spinner-overlay');

        if (Array.isArray(formsWithSpinner) && General.isUsable(spinnerOverlay)) {
            formsWithSpinner.forEach((form) => {
                form.addEventListener('submit', (event) => {
                    event.preventDefault();
                    let enteredPrompt = document.querySelector('div[data-module-id="aiSuite"] textarea[name="' + promptInputName + '"]').value;
                    const pageFields = document.querySelectorAll('input[name="startStructureFromPid"]');
                    const pageSelected = pageFields.length >= 1 && pageFields[0].value !== '';
                    const rootPageSelected = pageFields.length >= 1 && parseInt(pageFields[0].value) === -1;

                    if (enteredPrompt.length < 10) {
                        Notification.warning(TYPO3.lang['aiSuite.module.modal.enteredPromptTitle'], TYPO3.lang['aiSuite.module.modal.enteredPromptMessage'], 8);
                        return;
                    }
                    if (!pageSelected && !rootPageSelected) {
                        Notification.warning(TYPO3.lang['aiSuite.module.modal.noPageSelectedTitle'], TYPO3.lang['aiSuite.module.modal.noPageSelectedMessage'], 8);
                        return;
                    }
                    Generation.showSpinner();
                    form.submit();
                });
            });
        }
    }

    async addGlobalInstructionEventListener() {
        const pageSelectionList = document.querySelectorAll('ul.dropdown-menu li');
        pageSelectionList.forEach((element) => {
            element.addEventListener('click', function(ev) {
                ev.preventDefault();
                const pageFields = document.querySelectorAll('input[name="startStructureFromPid"]');
                const startFromPid = pageFields.length >= 1 && pageFields[0].value !== '' && parseInt(pageFields[0].value) !== -1 ? parseInt(pageFields[0].value) : 0;
                let data = {
                    context: 'pages',
                    scope: 'pageTree',
                    pageId: startFromPid
                }
                GlobalInstructions.fetchGlobalInstructions(data);
            });
        });
    }
}
export default new Creation();


