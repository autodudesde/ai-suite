define([
    "TYPO3/CMS/AiSuite/Helper/Image/GenerationHandling"
], function(GenerationHandling) {
    function GeneratImage() {
        this.init();
    }

    GeneratImage.prototype.init = function() {
        document.querySelectorAll('.typo3-TCEforms').forEach(function(element) {
            element.addEventListener("click", function(ev) {
                if(ev.target && ev.target.nodeName === "BUTTON" && ev.target.classList.contains('t3js-ai-suite-image-generation-add-btn')) {
                    ev.preventDefault();
                    let data = {
                        objectPrefix: ev.target.getAttribute('data-file-irre-object'),
                        fileContextConfig: ev.target.getAttribute('data-file-context-config'),
                        fileContextHmac: ev.target.getAttribute('data-file-context-hmac'),
                        table: ev.target.getAttribute('data-table'),
                        pageId: ev.target.getAttribute('data-page-id'),
                        languageId: ev.target.getAttribute('data-language-id'),
                        position: ev.target.getAttribute('data-position'),
                        fieldName: ev.target.getAttribute('data-fieldname'),
                        imagePrompt: '',
                        imageAiModel: '',
                        uuid: ''
                    };
                    GenerationHandling.showGeneralImageSettingsModal(data);
                }
            });
        });
    }
    return new GeneratImage();
});
