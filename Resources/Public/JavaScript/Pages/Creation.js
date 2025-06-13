define([
    "TYPO3/CMS/AiSuite/Helper/Generation",
    "TYPO3/CMS/AiSuite/Helper/PromptTemplate",
], function(Generation, PromptTemplate) {
    Generation.cancelGeneration();
    Generation.addFormSubmitEventListener('plainPrompt');
    PromptTemplate.loadPromptTemplates('plainPrompt');
    Generation.languageSelectionEventListener();
});


