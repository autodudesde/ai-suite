define([
    "TYPO3/CMS/Backend/MultiStepWizard",
    "TYPO3/CMS/AiSuite/Helper/Image/Wizards/Slides/MidjourneySlides"
], function(
    MultiStepWizard,
    MidjourneySlides
) {

    let MidjourneyContentElement = function() {}

    MidjourneyContentElement.prototype.addImageGenerationWizard = function(data, showSpinner, showGeneralImageSettingsModal) {
        MidjourneySlides.slideOne(data, showSpinner, showGeneralImageSettingsModal);
        MidjourneySlides.slideTwoContentElement(data, showSpinner);
        MultiStepWizard.show();
    }
    return new MidjourneyContentElement;
});
