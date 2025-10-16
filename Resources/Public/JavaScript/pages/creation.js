import Generation from "@autodudes/ai-suite/helper/generation.js";
import PromptTemplate from "@autodudes/ai-suite/helper/prompt-template.js";
import GlobalInstructions from "@autodudes/ai-suite/helper/global-instructions.js";

class Creation {
    constructor() {
        Generation.cancelGeneration();
        Generation.addFormSubmitEventListener('plainPrompt');
        PromptTemplate.loadPromptTemplates('plainPrompt');
        Generation.languageSelectionEventListener();
        this.addGlobalInstructionEventListener().then();
    }

    async addGlobalInstructionEventListener() {
        const pageSelectionList = document.querySelectorAll('ul.dropdown-menu li');
        pageSelectionList.forEach((element) => {
            element.addEventListener('click', function(ev) {
                ev.preventDefault();
                const pageFields = document.querySelectorAll('input[name="startStructureFromPid"]');
                const startFromPid = pageFields.length >= 1 && parseInt(pageFields[0].value) !== -1 ? parseInt(pageFields[0].value) : 0;
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


