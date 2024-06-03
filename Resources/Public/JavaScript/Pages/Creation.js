define([
    "TYPO3/CMS/AiSuite/Helper/Generation",
    "TYPO3/CMS/AiSuite/Helper/PromptTemplate",
], function(Generation, PromptTemplate) {
    Generation.cancelGeneration();
    Generation.addFormSubmitEventListener('tx_aisuite_web_aisuiteaisuite[input][plainPrompt]');
    PromptTemplate.loadPromptTemplates('tx_aisuite_web_aisuiteaisuite[input][plainPrompt]');
});


