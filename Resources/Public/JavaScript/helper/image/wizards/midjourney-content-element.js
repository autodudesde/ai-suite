import MultiStepWizard from "@typo3/backend/multi-step-wizard.js";
import MidjourneySlides from "@autodudes/ai-suite/helper/image/wizards/slides/midjourney-slides.js";

class MidjourneyContentElement {
    addImageGenerationWizard(data) {
        MidjourneySlides.slideOne(data);
        MidjourneySlides.slideTwoContentElement(data);
        MultiStepWizard.show();
    }
}

export default new MidjourneyContentElement();
