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
/**
 * Module: TYPO3/CMS/WvDeepltranslate/Localization11
 * UI for localization workflow.
 */
define([
    "jquery",
    "TYPO3/CMS/Backend/AjaxDataHandler",
    "TYPO3/CMS/Backend/Wizard",
    "TYPO3/CMS/Backend/Icons",
    "TYPO3/CMS/Backend/Severity",
    "TYPO3/CMS/Core/Ajax/AjaxRequest",
    "TYPO3/CMS/AiSuite/Helper/Translation",
    "TYPO3/CMS/AiSuite/Helper/Image/StatusHandling",
    "TYPO3/CMS/AiSuite/Helper/Generation",
    "bootstrap",
], function ($, AjaxDataHandler, Wizard, Icons, Severity, AjaxRequest, Translation, StatusHandling, Generation) {
    "use strict"

    /**
     * @type {{identifier: {triggerButton: string}, actions: {translate: $, copy: $}, settings: {}, records: []}}
     * @exports TYPO3/CMS/Backend/Localization
     */
    var Localization = {
        identifier: {
            triggerButton: '.t3js-localize-ai-suite',
        },
        actions: {
            translate: $('<label />', {
                class: 'btn btn-block btn-default t3js-localization-option',
                'data-helptext': '.t3js-helptext-translate',
            })
                .html('<br>Translate')
                .prepend(
                    $('<input />', {
                        type: 'radio',
                        name: 'mode',
                        id: 'mode_translate',
                        value: 'localize',
                        style: 'display: none',
                    }),
                ),
            copy: $('<label />', {
                class: 'btn btn-block btn-default t3js-localization-option',
                'data-helptext': '.t3js-helptext-copy',
            })
                .html('<br>Copy')
                .prepend(
                    $('<input />', {
                        type: 'radio',
                        name: 'mode',
                        id: 'mode_copy',
                        value: 'copyFromLanguage',
                        style: 'display: none',
                    }),
                ),
        },
        settings: {},
        records: []
    }

    Localization.initialize = function() {
        $(Localization.identifier.triggerButton).removeClass(
            'disabled',
        )
        $(document).on('click', Localization.identifier.triggerButton, async function (e) {
            e.preventDefault();
            const $triggerButton = $(e.currentTarget);
            const permissions = await Localization.checkLocalizationPermissions();
            const actions = await Translation.addAvailableLibraries(permissions, $triggerButton.data('allowTranslate'), $triggerButton.data('allowCopy'));
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
                .replace('{1}', $triggerButton.data('languageName')), slideContent, Severity.info, () => {
                if (availableLocalizationModes.length === 1) {
                    // In case only one mode is available, select the mode and continue
                    Localization.settings.mode = availableLocalizationModes[0];
                    Wizard.unlockNextStep().trigger('click');
                }
            });
            Wizard.addSlide('localize-choose-language', TYPO3.lang['localize.view.chooseLanguage'], '', Severity.info, ($slide) => {
                Icons.getIcon('spinner-circle-dark', Icons.sizes.large).then((markup) => {
                    $slide.html('<div class="text-center">' + markup + '</div>');
                    Localization.loadAvailableLanguages(parseInt($triggerButton.data('pageId'), 10), parseInt($triggerButton.data('languageId'), 10), Localization.settings.mode).then(async (response) => {
                        const result = await response.resolve();
                        if (result.length === 1) {
                            // We only have one result, auto select the record and continue
                            Localization.settings.language = result[0].uid + '' // we need a string
                            Wizard.unlockNextStep().trigger('click');
                            return;
                        }
                        Wizard.getComponent().on('click', '.t3js-language-option', (optionEvt) => {
                            const $me = $(optionEvt.currentTarget);
                            const $radio = $me.prev();
                            Localization.settings.language = $radio.val();
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
            Wizard.addSlide('localize-summary', TYPO3.lang['localize.view.summary'], '', Severity.info, ($slide) => {
                Icons.getIcon('spinner-circle-dark', Icons.sizes.large).then((markup) => {
                    $slide.html('<div class="text-center">' + markup + '</div>');
                });
                Localization.getSummary(parseInt($triggerButton.data('pageId'), 10), parseInt($triggerButton.data('languageId'), 10)).then(async (response) => {
                    const result = await response.resolve();
                    $slide.empty();
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
                            Localization.records.push(record.uid);
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
                                Localization.records.push(uid);
                            } else {
                                const index = Localization.records.indexOf(uid);
                                if (index > -1) {
                                    Localization.records.splice(index, 1);
                                }
                            }
                            const $allChildren = $parent.find('.t3js-localization-toggle-record');
                            const $checkedChildren = $parent.find('.t3js-localization-toggle-record:checked');
                            $columnCheckbox.prop('checked', $checkedChildren.length > 0);
                            $columnCheckbox.prop('indeterminate', $checkedChildren.length > 0 && $checkedChildren.length < $allChildren.length);
                            if (Localization.records.length > 0) {
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
                $slide.html(Generation.showSpinnerModal(TYPO3.lang['aiSuite.module.modal.translationInProcess']));
                let modal = Wizard.setup.$carousel.closest('.modal');
                modal.find('.spinner-wrapper').css('overflow', 'hidden');
                const postData = {
                    'pageId': $triggerButton.data('pageId'),
                    'uuid': $triggerButton.data('uuid')
                }
                StatusHandling.fetchStatus(postData, modal)
                Localization.localizeRecords(parseInt($triggerButton.data('pageId'), 10), parseInt($triggerButton.data('languageId'), 10), Localization.records, $triggerButton.data('uuid')).then(() => {
                    StatusHandling.stopInterval();
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
                    Localization.loadAvailableLanguages(parseInt($triggerButton.data('pageId'), 10), parseInt($triggerButton.data('languageId'), 10)).then(async (response) => {
                        const result = await response.resolve();
                        if (result.length === 1) {
                            Localization.settings.language = result[0].uid;
                        } else {
                            // This seems pretty ugly solution to find the right language uid but its done the same way in the core... line 211-213
                            // If we have more then 1 language we need to find the first radio button and check its value to get the source language
                            Localization.settings.language = $radio.prev().val();
                        }
                    });
                    Localization.settings[$radio.attr('name')] = $radio.val()
                    Wizard.unlockNextStep();
                });
            });
        });

        /**
         * Load available languages from page
         *
         * @param {number} pageId
         * @param {number} languageId
         * @param {string} mode
         * @returns {Promise}
         */
        Localization.loadAvailableLanguages = function(pageId, languageId, mode) {
            return new AjaxRequest(TYPO3.settings.ajaxUrls.page_languages)
                .withQueryArguments({
                    pageId: pageId,
                    languageId: languageId,
                    mode: mode,
                })
                .get();
        }
        /**
         * Get summary for record processing
         *
         * @param {number} pageId
         * @param {number} languageId
         * @returns {Promise}
         */
        Localization.getSummary = function (pageId, languageId) {
            return new AjaxRequest(TYPO3.settings.ajaxUrls.records_localize_summary)
                .withQueryArguments({
                    pageId: pageId,
                    destLanguageId: languageId,
                    languageId: Localization.settings.language,
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
         * @returns {Promise}
         */
        Localization.localizeRecords = function(pageId, languageId, uidList, uuid) {
            return new AjaxRequest(TYPO3.settings.ajaxUrls.records_localize)
                .withQueryArguments({
                    pageId: pageId,
                    srcLanguageId: Localization.settings.language,
                    destLanguageId: languageId,
                    action: Localization.settings.mode,
                    uidList: uidList,
                    uuid: uuid
                })
                .get();
        }

        Localization.checkLocalizationPermissions = function() {
            return new AjaxRequest(TYPO3.settings.ajaxUrls.aisuite_localization_permissions)
                .get()
                .then(async (response) => {
                    const resolved = await response.resolve();
                    const responseBody = JSON.parse(resolved);
                    if (responseBody.output) {
                        return responseBody.output.permissions.enable_translation;
                    }
                    return false;
                });
        }
    }

    $(Localization.initialize)

    return Localization
});
