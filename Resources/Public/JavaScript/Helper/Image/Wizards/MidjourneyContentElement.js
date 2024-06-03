define([
    "TYPO3/CMS/Backend/MultiStepWizard",
    "TYPO3/CMS/AiSuite/Helper/Image/Wizards/Slides/MidjourneySlides"
], function(
    MultiStepWizard,
    MidjourneySlides
) {
    function addImageGenerationWizard(data, showSpinner, showGeneralImageSettingsModal) {
        MidjourneySlides.slideOne(data, showSpinner, showGeneralImageSettingsModal);
        MidjourneySlides.slideTwoContentElement(data, showSpinner);
        MultiStepWizard.show();
    }
    return {
        addImageGenerationWizard
    };
});
