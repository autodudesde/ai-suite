define([
    "TYPO3/CMS/AiSuite/Helper/Image/GenerationHandling",
    "TYPO3/CMS/AiSuite/Helper/General"
], function(GenerationHandling, General) {
    function GeneratImageFilelist() {
        this.init();
    }

    GeneratImageFilelist.prototype.init = function() {
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
    return new GeneratImageFilelist();
});
