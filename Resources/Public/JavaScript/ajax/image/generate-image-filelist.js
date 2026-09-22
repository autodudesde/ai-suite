import GenerationHandling from "@autodudes/ai-suite/helper/image/generation-handling.js";
import General from "@autodudes/ai-suite/helper/general.js";

class GenerateImage {
    constructor() {
        this.init();
    }
    init() {
        if(General.isUsable(document.querySelector('.t3js-ai-suite-image-generation-filelist-add-btn'))) {
            document.querySelector('.t3js-ai-suite-image-generation-filelist-add-btn').addEventListener("click", function(ev) {
                ev.preventDefault();
                // currentTarget, not target: the button carries an icon and a label, and a click on
                // either of those reads the attributes off the inner element, where they are null.
                const button = ev.currentTarget;
                let data = {
                    targetFolder: button.getAttribute('data-target-folder') ?? '',
                    imagePrompt: '',
                    imageAiModel: '',
                    uuid: button.getAttribute('data-uuid') ?? '',
                    langIsoCode: '',
                };
                GenerationHandling.showGeneralImageSettingsModal(data, 'FileList');
            });
        }
    }
}
export default new GenerateImage();
