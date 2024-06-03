define([], function() {
    /**
     * @param {string} nameAttribute
     */
    function loadPromptTemplates(nameAttribute) {
        let promptTemplates = document.querySelector('div[data-module-id="aiSuite"] select[name="promptTemplates"]');
        if(promptTemplates !== null) {
            promptTemplates.addEventListener('change', function (event) {
                document.querySelector('div[data-module-id="aiSuite"] textarea[name="' + nameAttribute + '"]').value = event.target.value;
            });
        }
    }
    return {
        loadPromptTemplates: loadPromptTemplates
    };
});
