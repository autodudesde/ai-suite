define([
    "TYPO3/CMS/Backend/MultiStepWizard",
    "TYPO3/CMS/AiSuite/Helper/Image/Wizards/Slides/MidjourneySlides"
], function(
    MultiStepWizard,
    MidjourneySlides
) {

    let Midjourney = function() {}

    Midjourney.prototype.addImageGenerationWizard = function(data, filelistScope = false) {
        MidjourneySlides.slideOne(data);
        MidjourneySlides.slideTwo(data, filelistScope);
        MultiStepWizard.show();
    }

    return new Midjourney();
});
