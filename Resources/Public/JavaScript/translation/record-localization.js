import $ from 'jquery';
import Severity from "@typo3/backend/severity.js";
import MultiStepWizard from "@typo3/backend/multi-step-wizard.js";
import Translation from "@autodudes/ai-suite/helper/translation.js";
import StatusHandling from "@autodudes/ai-suite/helper/image/status-handling.js";

class AiSuiteRecordLocalization {
    constructor() {
        this.initialize();
    }

    initialize() {
        const self = this;
        MultiStepWizard.dismiss();
        document.querySelectorAll('.ai-suite-record-localization').forEach(function(recordLocalizationButton) {
            recordLocalizationButton.addEventListener('click', async function (event) {
                event.preventDefault();
                let href = event.target.dataset.href;
                let uuid = event.target.dataset.uuid;
                let pageId = event.target.dataset.pageId;
                const actions = await Translation.addAvailableLibraries();
                let slideContent = '<div data-bs-toggle="buttons">' + actions.join('') + '</div>';
                MultiStepWizard.addSlide('ai-suite-record-localization', TYPO3.lang['aiSuite.module.modal.modelSelection'], '', Severity.notice, TYPO3.lang['aiSuite.module.modal.recordLocalization'], async function (slide) {
                    MultiStepWizard.blurCancelStep();
                    MultiStepWizard.lockNextStep();
                    MultiStepWizard.lockPrevStep();
                    slide.html(slideContent);
                    let modal = slide.closest('.modal');
                    modal.find('.t3js-localization-option').on('click', function (optionEvt) {
                        event.preventDefault();
                        const $me = $(optionEvt.currentTarget);
                        const $radio = $me.find('input[type="radio"]:checked');
                        if ($radio.val() === undefined) {
                            return;
                        }
                        let selectedModel = $radio.val().toString();
                        selectedModel = selectedModel.replace('localize', '');
                        selectedModel = selectedModel.replace('copyFromLanguage', '');
                        href = href.replace('AI_SUITE_MODEL', selectedModel);
                        window.location.href = href;
                        slide.html(self.showSpinner(TYPO3.lang['aiSuite.module.modal.translationInProcess']));
                        let modal = MultiStepWizard.setup.$carousel.closest('.modal');
                        modal.find('.spinner-wrapper').css('overflow', 'hidden');
                        const postData = {
                            'pageId': pageId,
                            'uuid': uuid
                        }
                        StatusHandling.fetchStatus(postData, modal, self)
                    });
                });
                MultiStepWizard.show();
            });
        });
    }

    // TODO: summarize in General class
    showSpinner(message, height = 400) {
        return '<style>.modal-body{padding: 0;}.modal-multi-step-wizard .modal-body .carousel-inner {margin: 0 0 0 -5px;}.spinner-wrapper{width:600px;height:' + height +'px;position:relative;overflow:hidden;}.spinner-overlay{position:absolute;top:0;left:0;width:100%;height:100%;display:flex;justify-content:center;align-content:center;flex-wrap:wrap;background-color:#00000000;color:#fff;font-weight:700;transition:background-color .9s ease-in-out}.spinner-overlay.darken{background-color:rgba(0,0,0,.75)}.spinner,.spinner:after,.spinner:before{text-align:center;opacity:0;width:35px;aspect-ratio:1;box-shadow:0 0 0 3px inset #fff;position:relative;animation:1.5s .5s infinite;animation-name:l7-1,l7-2}.spinner:after,.spinner:before{content:"";position:absolute;left:calc(100% + 5px);animation-delay:1s,0s}.spinner:after{left:-40px;animation-delay:0s,1s}@keyframes l7-1{0%,100%,55%{border-top-left-radius:0;border-bottom-right-radius:0}20%,30%{border-top-left-radius:50%;border-bottom-right-radius:50%}}@keyframes l7-2{0%,100%,55%{border-bottom-left-radius:0;border-top-right-radius:0}20%,30%{border-bottom-left-radius:50%;border-top-right-radius:50%}}.spinner-overlay.darken .spinner,.spinner-overlay.darken .spinner:after,.spinner-overlay.darken .spinner:before{opacity:1}.spinner-overlay.darken .message{position:absolute;top:56%;font-size:.9rem}.spinner-overlay.darken .status{position:absolute;top:62%;font-size:.9rem}</style><div class="spinner-wrapper"><div class="spinner-overlay active darken"><div class="spinner"></div><p class="message">'+message+'</p><p class="status"></p></div></div>'
    }
}

export default new AiSuiteRecordLocalization();
