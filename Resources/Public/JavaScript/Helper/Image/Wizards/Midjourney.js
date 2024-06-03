define([
    "TYPO3/CMS/Backend/MultiStepWizard",
    "TYPO3/CMS/AiSuite/Helper/Image/SaveHandling",
    "TYPO3/CMS/AiSuite/Helper/Image/Wizards/Slides/MidjourneySlides"
], function(
    MultiStepWizard,
    SaveHandling,
    MidjourneySlides
) {
    function addImageGenerationWizard(data, showSpinner, showGeneralImageSettingsModal, filelistScope = false) {
        MidjourneySlides.slideOne(data, showSpinner, showGeneralImageSettingsModal);
        MidjourneySlides.slideTwo(data, showSpinner, addSelectionEventListeners, filelistScope);
        MultiStepWizard.show();
    }

    function addSelectionEventListeners(modal, data, slide, showSpinner, filelistScope) {
        SaveHandling.backToSlideOneButton(modal, data);
        SaveHandling.selectionHandler(modal, 'img.ce-image-selection');
        SaveHandling.selectionHandler(modal, 'label.ce-image-title-selection');
        if(filelistScope) {
            SaveHandling.saveGeneratedImageFileListButton(modal, data, slide, showSpinner);
        } else {
            SaveHandling.saveGeneratedImageButton(modal, data, slide, showSpinner);
        }
    }
    return {
        addImageGenerationWizard,
        addSelectionEventListeners
    };
});
