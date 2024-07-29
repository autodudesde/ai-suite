import $ from 'jquery';
import DocumentService from "@typo3/core/document-service.js";
import { AjaxResponse } from '@typo3/core/ajax/ajax-response.js';
import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import 'lit';
import { SeverityEnum } from '@typo3/backend/enum/severity.js';
import Icons from '@typo3/backend/icons.js';
import Wizard from "@typo3/backend/wizard.js";
import StatusHandling from "@autodudes/ai-suite/helper/image/status-handling.js";
import Translation from "@autodudes/ai-suite/helper/translation.js";

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */
class AiSuiteLocalization {
    constructor() {
        this.triggerButton = '.t3js-localize-ai-suite';
        this.localizationMode = null;
        this.sourceLanguage = null;
        this.records = [];
        DocumentService.ready().then(() => {
            this.initialize();
        });
        this.intervalId = null;
    }
    initialize() {
        const self = this;
        $(self.triggerButton).removeClass('disabled');
        $(document).on('click', self.triggerButton, async (e) => {
            e.preventDefault();
            const $triggerButton = $(e.currentTarget);
            const actions = await Translation.addAvailableLibraries($triggerButton.data('allowTranslate'), $triggerButton.data('allowCopy'));
            const availableLocalizationModes = [];
            if ($triggerButton.data('allowTranslate')) {
                availableLocalizationModes.push('localize');
            }
            if ($triggerButton.data('allowCopy')) {
                availableLocalizationModes.push('copyFromLanguage');
            }
            if (actions.length > 0) {
                availableLocalizationModes.push('copyWithAI');
            }
            const slideContent = '<div data-bs-toggle="buttons">' + actions.join('') + '</div>';
            Wizard.addSlide('localize-choose-action', TYPO3.lang['localize.wizard.header_page']
                .replace('{0}', $triggerButton.data('page'))
                .replace('{1}', $triggerButton.data('languageName')), slideContent, SeverityEnum.info, () => {
                if (availableLocalizationModes.length === 1) {
                    // In case only one mode is available, select the mode and continue
                    this.localizationMode = availableLocalizationModes[0];
                    Wizard.unlockNextStep().trigger('click');
                }
            });
            Wizard.addSlide('localize-choose-language', TYPO3.lang['localize.view.chooseLanguage'], '', SeverityEnum.info, ($slide) => {
                Icons.getIcon('spinner-circle-dark', Icons.sizes.large).then((markup) => {
                    $slide.html('<div class="text-center">' + markup + '</div>');
                    this.loadAvailableLanguages(parseInt($triggerButton.data('pageId'), 10), parseInt($triggerButton.data('languageId'), 10)).then(async (response) => {
                        const result = await response.resolve();
                        if (result.length === 1) {
                            // We only have one result, auto select the record and continue
                            this.sourceLanguage = result[0].uid;
                            Wizard.unlockNextStep().trigger('click');
                            return;
                        }
                        Wizard.getComponent().on('click', '.t3js-language-option', (optionEvt) => {
                            const $me = $(optionEvt.currentTarget);
                            const $radio = $me.prev();
                            this.sourceLanguage = $radio.val();
                            Wizard.unlockNextStep();
                        });
                        const $languageButtons = $('<div />', {class: 'row'});
                        for (const languageObject of result) {
                            const id = 'language' + languageObject.uid;
                            const $input = $('<input />', {
                                type: 'radio',
                                name: 'language',
                                id: id,
                                value: languageObject.uid,
                                style: 'display: none;',
                                class: 'btn-check',
                            });
                            const $label = $('<label />', {
                                class: 'btn btn-default d-block t3js-language-option option',
                                for: id,
                            })
                                .text(' ' + languageObject.title)
                                .prepend(languageObject.flagIcon);
                            $languageButtons.append($('<div />', {class: 'col-sm-4'}).append($input).append($label));
                        }
                        $slide.empty().append($languageButtons);
                    });
                });
            });
            Wizard.addSlide('localize-summary', TYPO3.lang['localize.view.summary'], '', SeverityEnum.info, ($slide) => {
                Icons.getIcon('spinner-circle-dark', Icons.sizes.large).then((markup) => {
                    $slide.html('<div class="text-center">' + markup + '</div>');
                });
                this.getSummary(parseInt($triggerButton.data('pageId'), 10), parseInt($triggerButton.data('languageId'), 10)).then(async (response) => {
                    const result = await response.resolve();
                    $slide.empty();
                    this.records = [];
                    const columns = result.columns.columns;
                    const columnList = result.columns.columnList;
                    columnList.forEach((colPos) => {
                        if (typeof result.records[colPos] === 'undefined') {
                            return;
                        }
                        const column = columns[colPos];
                        const $row = $('<div />', {class: 'row'});
                        result.records[colPos].forEach((record) => {
                            const label = ' (' + record.uid + ') ' + record.title;
                            this.records.push(record.uid);
                            $row.append($('<div />', {class: 'col-sm-6'}).append($('<div />', {class: 'input-group'}).append($('<span />', {class: 'input-group-addon'}).append($('<input />', {
                                type: 'checkbox',
                                class: 't3js-localization-toggle-record',
                                id: 'record-uid-' + record.uid,
                                checked: 'checked',
                                'data-uid': record.uid,
                                'aria-label': label,
                            })), $('<label />', {
                                class: 'form-control',
                                for: 'record-uid-' + record.uid,
                            })
                                .text(label)
                                .prepend(record.icon))));
                        });
                        $slide.append($('<fieldset />', {
                            class: 'localization-fieldset',
                        }).append($('<label />')
                            .text(column)
                            .prepend($('<input />', {
                                class: 't3js-localization-toggle-column',
                                type: 'checkbox',
                                checked: 'checked',
                            })), $row));
                    });
                    Wizard.unlockNextStep();
                    Wizard.getComponent()
                        .on('change', '.t3js-localization-toggle-record', (cmpEvt) => {
                            const $me = $(cmpEvt.currentTarget);
                            const uid = $me.data('uid');
                            const $parent = $me.closest('fieldset');
                            const $columnCheckbox = $parent.find('.t3js-localization-toggle-column');
                            if ($me.is(':checked')) {
                                this.records.push(uid);
                            } else {
                                const index = this.records.indexOf(uid);
                                if (index > -1) {
                                    this.records.splice(index, 1);
                                }
                            }
                            const $allChildren = $parent.find('.t3js-localization-toggle-record');
                            const $checkedChildren = $parent.find('.t3js-localization-toggle-record:checked');
                            $columnCheckbox.prop('checked', $checkedChildren.length > 0);
                            $columnCheckbox.prop('indeterminate', $checkedChildren.length > 0 && $checkedChildren.length < $allChildren.length);
                            if (this.records.length > 0) {
                                Wizard.unlockNextStep();
                            } else {
                                Wizard.lockNextStep();
                            }
                        })
                        .on('change', '.t3js-localization-toggle-column', (toggleEvt) => {
                            const $me = $(toggleEvt.currentTarget);
                            const $children = $me.closest('fieldset').find('.t3js-localization-toggle-record');
                            $children.prop('checked', $me.is(':checked'));
                            $children.trigger('change');
                        });
                });
            });
            Wizard.addFinalProcessingSlide(($slide) => {
                $slide.html(self.showSpinner(TYPO3.lang['aiSuite.module.modal.translationInProcess']));
                let modal = Wizard.setup.$carousel.closest('.modal');
                modal.find('.spinner-wrapper').css('overflow', 'hidden');
                const postData = {
                    'pageId': $triggerButton.data('pageId'),
                    'uuid': $triggerButton.data('uuid')
                }
                StatusHandling.fetchStatus(postData, modal, self)
                this.localizeRecords(parseInt($triggerButton.data('pageId'), 10), parseInt($triggerButton.data('languageId'), 10), this.records, $triggerButton.data('uuid')).then(() => {
                    clearInterval(this.intervalId);
                    Wizard.dismiss();
                    document.location.reload();
                });
            }).then(() => {
                Wizard.show();
                Wizard.getComponent().on('click', '.t3js-localization-option', (optionEvt) => {
                    const $me = $(optionEvt.currentTarget);
                    const $radio = $me.find('input[type="radio"]');
                    if ($me.data('helptext')) {
                        const $container = $(optionEvt.delegateTarget);
                        $container.find('.t3js-localization-option').removeClass('active');
                        $container.find('.t3js-helptext').addClass('text-body-secondary');
                        $me.addClass('active');
                        $container.find($me.data('helptext')).removeClass('text-body-secondary');
                    }
                    this.loadAvailableLanguages(parseInt($triggerButton.data('pageId'), 10), parseInt($triggerButton.data('languageId'), 10)).then(async (response) => {
                        const result = await response.resolve();
                        if (result.length === 1) {
                            this.sourceLanguage = result[0].uid;
                        } else {
                            // This seems pretty ugly solution to find the right language uid but its done the same way in the core... line 211-213
                            // If we have more then 1 language we need to find the first radio button and check its value to get the source language
                            this.sourceLanguage = $radio.prev().val();
                        }
                    });
                    this.localizationMode = $radio.val().toString();
                    Wizard.unlockNextStep();
                });
            });
        });
    }
    /**
     * Load available languages from page
     *
     * @param {number} pageId
     * @param {number} languageId
     * @returns {Promise<AjaxResponse>}
     */
    loadAvailableLanguages(pageId, languageId) {
        return new AjaxRequest(TYPO3.settings.ajaxUrls.page_languages)
            .withQueryArguments({
            pageId: pageId,
            languageId: languageId,
        })
            .get();
    }
    /**
     * Get summary for record processing
     *
     * @param {number} pageId
     * @param {number} languageId
     * @returns {Promise<AjaxResponse>}
     */
    getSummary(pageId, languageId) {
        return new AjaxRequest(TYPO3.settings.ajaxUrls.records_localize_summary)
            .withQueryArguments({
            pageId: pageId,
            destLanguageId: languageId,
            languageId: this.sourceLanguage,
        })
            .get();
    }
    /**
     * Localize records
     *
     * @param {number} pageId
     * @param {number} languageId
     * @param {Array<number>} uidList
     * @param {string} uuid
     * @returns {Promise<AjaxResponse>}
     */
    localizeRecords(pageId, languageId, uidList, uuid) {
        return new AjaxRequest(TYPO3.settings.ajaxUrls.records_localize)
            .withQueryArguments({
            pageId: pageId,
            srcLanguageId: this.sourceLanguage,
            destLanguageId: languageId,
            action: this.localizationMode,
            uidList: uidList,
            uuid: uuid
        })
            .get();
    }

    // TODO: summarize in General class
    showSpinner(message, height = 400) {
        return '<style>.modal-body{padding: 0;}.modal-multi-step-wizard .modal-body .carousel-inner {margin: 0 0 0 -5px;}.spinner-wrapper{width:600px;height:' + height +'px;position:relative;overflow:hidden;}.spinner-overlay{position:absolute;top:0;left:0;width:100%;height:100%;display:flex;justify-content:center;align-content:center;flex-wrap:wrap;background-color:#00000000;color:#fff;font-weight:700;transition:background-color .9s ease-in-out}.spinner-overlay.darken{background-color:rgba(0,0,0,.75)}.spinner,.spinner:after,.spinner:before{text-align:center;opacity:0;width:35px;aspect-ratio:1;box-shadow:0 0 0 3px inset #fff;position:relative;animation:1.5s .5s infinite;animation-name:l7-1,l7-2}.spinner:after,.spinner:before{content:"";position:absolute;left:calc(100% + 5px);animation-delay:1s,0s}.spinner:after{left:-40px;animation-delay:0s,1s}@keyframes l7-1{0%,100%,55%{border-top-left-radius:0;border-bottom-right-radius:0}20%,30%{border-top-left-radius:50%;border-bottom-right-radius:50%}}@keyframes l7-2{0%,100%,55%{border-bottom-left-radius:0;border-top-right-radius:0}20%,30%{border-bottom-left-radius:50%;border-top-right-radius:50%}}.spinner-overlay.darken .spinner,.spinner-overlay.darken .spinner:after,.spinner-overlay.darken .spinner:before{opacity:1}.spinner-overlay.darken .message{position:absolute;top:56%;font-size:.9rem}.spinner-overlay.darken .status{position:absolute;top:62%;font-size:.9rem}</style><div class="spinner-wrapper"><div class="spinner-overlay active darken"><div class="spinner"></div><p class="message">'+message+'</p><p class="status"></p></div></div>'
    }
}
export default new AiSuiteLocalization();
