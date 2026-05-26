import $ from 'jquery';
import Severity from "@typo3/backend/severity.js";
import MultiStepWizard from "@typo3/backend/multi-step-wizard.js";
import Translation from "@autodudes/ai-suite/helper/translation.js";
import StatusHandling from "@autodudes/ai-suite/helper/image/status-handling.js";
import General from "@autodudes/ai-suite/helper/general.js";
import Generation from "@autodudes/ai-suite/helper/generation.js";

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
                const actions = await Translation.addAvailableLibraries(true);
                let slideContent = '<div data-bs-toggle="buttons">' + actions.join('') + '</div>';
                MultiStepWizard.addSlide(
                    'ai-suite-record-localization',
                    TYPO3.lang['aiSuite.module.modal.modelSelection'],
                    '', Severity.notice,
                    TYPO3.lang['aiSuite.module.modal.recordLocalization'],
                    async function (slide) {
                    MultiStepWizard.blurCancelStep();
                    MultiStepWizard.lockNextStep();
                    MultiStepWizard.lockPrevStep();
                    slide.html(slideContent);
                    let modal = slide.closest('.modal');
                    modal.find('.t3js-localization-option').on('change', function (optionEvt) {
                        event.preventDefault();
                        if (!General.isUsable($(optionEvt.currentTarget).val())) {
                            return;
                        }
                        let selectedModel = $(optionEvt.currentTarget).val();
                        selectedModel = selectedModel.replace('localize', '');
                        selectedModel = selectedModel.replace('copyFromLanguage', '');
                        href = href.replace('AI_SUITE_MODEL', selectedModel);
                        window.location.href = href;
                        slide.html(Generation.showSpinnerModal(TYPO3.lang['aiSuite.module.modal.translationInProcess']));
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
}

export default new AiSuiteRecordLocalization();
