define([
    "TYPO3/CMS/AiSuite/Helper/Image/GenerationHandling",
    "TYPO3/CMS/AiSuite/Helper/General"
], function(GenerationHandling, General) {
    init();

    function init() {
        if(General.isUsable(document.querySelector('.t3js-ai-suite-image-generation-filelist-add-btn'))) {
            document.querySelector('.t3js-ai-suite-image-generation-filelist-add-btn').addEventListener("click", function(ev) {
                ev.preventDefault();
                let data = {
                    targetFolder: ev.target.getAttribute('data-target-folder'),
                    imagePrompt: '',
                    imageAiModel: '',
                    uuid: ev.target.getAttribute('data-uuid'),
                    pageId: ev.target.getAttribute('data-page-id')
                };
                GenerationHandling.showGeneralImageSettingsModal(data, 'FileList');
            });
        }
    }
});
