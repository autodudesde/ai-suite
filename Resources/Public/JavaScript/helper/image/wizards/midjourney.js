import MultiStepWizard from "@typo3/backend/multi-step-wizard.js";
import SaveHandling from "@autodudes/ai-suite/helper/image/save-handling.js";
import MidjourneySlides from "@autodudes/ai-suite/helper/image/wizards/slides/midjourney-slides.js";

class Midjourney {
    addImageGenerationWizard(data, filelistScope = false) {
        MidjourneySlides.slideOne(data);
        let addSelectionEventListenersFn = this.addSelectionEventListeners;
        MidjourneySlides.slideTwo(data, filelistScope, addSelectionEventListenersFn);
        MultiStepWizard.show();
    }

    addSelectionEventListeners(modal, data, slide, filelistScope, self) {
        self.backToSlideOneButton(modal, data);
        SaveHandling.selectionHandler(modal, 'img.ce-image-selection');
        SaveHandling.selectionHandler(modal, 'label.ce-image-title-selection');
        if(filelistScope) {
            SaveHandling.saveGeneratedImageFileListButton(modal, data, slide);
        } else {
            SaveHandling.saveGeneratedImageButton(modal, data, slide);
        }
    }
}

export default new Midjourney();
