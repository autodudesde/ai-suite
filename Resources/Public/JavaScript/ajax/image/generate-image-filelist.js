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
                let data = {
                    targetFolder: ev.target.getAttribute('data-target-folder'),
                    imagePrompt: '',
                    imageAiModel: '',
                    uuid: ev.target.getAttribute('data-uuid'),
                    langIsoCode: '',
                };
                GenerationHandling.showGeneralImageSettingsModal(data, 'FileList');
            });
        }
    }
}
export default new GenerateImage();
