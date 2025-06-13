define([
    "jquery",
    "TYPO3/CMS/Backend/Severity",
    "TYPO3/CMS/Backend/MultiStepWizard",
    "TYPO3/CMS/AiSuite/Helper/Translation",
    "TYPO3/CMS/AiSuite/Helper/Image/StatusHandling",
    "TYPO3/CMS/AiSuite/Helper/Generation",
], function($, Severity, MultiStepWizard, Translation, StatusHandling, Generation) {

    function RecordLocalization() {
        this.initialize();
    }

    RecordLocalization.prototype.initialize = function() {
        MultiStepWizard.dismiss();
        document.querySelectorAll('.ai-suite-record-localization').forEach(function(recordLocalizationButton) {
            recordLocalizationButton.addEventListener('click', async function (event) {
                event.preventDefault();
                let href = event.target.dataset.href;
                let uuid = event.target.dataset.uuid;
                let pageId = event.target.dataset.pageId;
                const actions = await Translation.addAvailableLibraries(true);
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
                        slide.html(Generation.showSpinnerModal(TYPO3.lang['aiSuite.module.modal.translationInProcess']));
                        let modal = MultiStepWizard.setup.$carousel.closest('.modal');
                        modal.find('.spinner-wrapper').css('overflow', 'hidden');
                        const postData = {
                            'pageId': pageId,
                            'uuid': uuid
                        }
                        StatusHandling.fetchStatus(postData, modal)
                    });
                });
                MultiStepWizard.show();
            });
        });
    }
    return new RecordLocalization();
});
