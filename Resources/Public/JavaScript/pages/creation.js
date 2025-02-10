import Generation from "@autodudes/ai-suite/helper/generation.js";
import PromptTemplate from "@autodudes/ai-suite/helper/prompt-template.js";

class Creation {
    constructor() {
        Generation.cancelGeneration();
        Generation.addFormSubmitEventListener('plainPrompt');
        PromptTemplate.loadPromptTemplates('plainPrompt');
        Generation.languageSelectionEventListener();
    }
}
export default new Creation();


