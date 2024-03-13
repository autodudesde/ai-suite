import HelperFunctions from "../helper/functions.js";

class Creation {
    constructor() {
        HelperFunctions.addFormSubmitEventListener();
        this.setPromptTemplate();
    }
    setPromptTemplate() {
        let promptTemplates = document.querySelector('div[data-module-id="aiSuite"] select[name="promptTemplates"]');
        if(promptTemplates !== null) {
            promptTemplates.addEventListener('change', function (event) {
                document.querySelector('div[data-module-id="aiSuite"] textarea[name="input[plainPrompt]"]').value = event.target.value;
            });
        }
    }
}
export default new Creation();


