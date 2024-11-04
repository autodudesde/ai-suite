import $ from 'jquery';
import DocumentService from "@typo3/core/document-service.js";
import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import 'lit';
import { SeverityEnum } from '@typo3/backend/enum/severity.js';
import Icons from '@typo3/backend/icons.js';
import Modal from "@typo3/backend/modal.js";
import MultiStepWizard from "@typo3/backend/multi-step-wizard.js";
import StatusHandling from "@autodudes/ai-suite/helper/image/status-handling.js";
import Translation from "@autodudes/ai-suite/helper/translation.js";
import Generation from "@autodudes/ai-suite/helper/generation.js";

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
        DocumentService.ready().then(() => {
            this.initialize();
        });
        this.intervalId = null;
    }
    async initialize() {
        const self = this;
        $(self.triggerButton).removeClass('disabled');
        $(document).on('click', self.triggerButton, async (e) => {
            e.preventDefault();
            const $triggerButton = $(e.currentTarget);
            const permissions = await this.checkLocalizationPermissions();
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
            if ($triggerButton.data('allowTranslate') === 0 && $triggerButton.data('allowCopy') === 0 && actions.length === 0) {
                Modal.confirm(
                    TYPO3.lang['window.localization.mixed_mode.title'],
                    TYPO3.lang['window.localization.mixed_mode.message'],
                    SeverityEnum.warning,
                    [
                        {
                            text: TYPO3?.lang?.['button.ok'] || 'OK',
                            btnClass: 'btn-warning',
                            name: 'ok',
                            trigger: (e, modal) => modal.hideModal()
                        }
                    ]
                );
                return;
            }
            const availableLanguages = await (await this.loadAvailableLanguages(
                parseInt($triggerButton.data('pageId'), 10),
                parseInt($triggerButton.data('languageId'), 10),
            )).resolve();

            if (availableLocalizationModes.length === 1) {
                MultiStepWizard.set('localizationMode', availableLocalizationModes[0]);
            } else {
                const buttonContainer = document.createElement('div');
                buttonContainer.dataset.bsToggle = 'buttons';
                buttonContainer.append(...actions.map((actionMarkup) => document.createRange().createContextualFragment(actionMarkup)));

                MultiStepWizard.addSlide(
                    'localize-choose-action',
                    TYPO3.lang['localize.wizard.header_page']
                        .replace('{0}', $triggerButton.data('page'))
                        .replace('{1}', $triggerButton.data('languageName')),
                    buttonContainer,
                    SeverityEnum.notice,
                    TYPO3.lang['localize.wizard.step.selectMode'],
                    ($slide, settings) => {
                        if (settings.localizationMode !== undefined) {
                            MultiStepWizard.unlockNextStep();
                        }
                    }
                );
            }
            if (availableLanguages.length === 1) {
                MultiStepWizard.set('sourceLanguage', availableLanguages[0].uid);
            } else {
                MultiStepWizard.addSlide(
                    'localize-choose-language',
                    TYPO3.lang['localize.view.chooseLanguage'],
                    '', SeverityEnum.notice,
                    TYPO3.lang["localize.wizard.step.chooseLanguage"],
                    async ($slide, settings) => {
                    if (settings.sourceLanguage !== undefined) {
                        MultiStepWizard.unlockNextStep();
                    }
                    $slide.html('<div class="text-center">' + (await Icons.getIcon('spinner-circle', Icons.sizes.large)) + '</div>');

                    MultiStepWizard.getComponent().on('change', '.t3js-language-option', (optionEvt) => {
                        MultiStepWizard.set('sourceLanguage', $(optionEvt.currentTarget).val());
                        MultiStepWizard.unlockNextStep();
                    });
                    const $languageButtons = $('<div />', { class: 'row' });

                    for (const languageObject of availableLanguages) {
                        const id = 'language' + languageObject.uid;
                        const $input = $('<input />', {
                            type: 'radio',
                            name: 'language',
                            id: id,
                            value: languageObject.uid,
                            class: 'btn-check t3js-language-option'
                        });
                        const $label = $('<label />', {
                            class: 'btn btn-default btn-block',
                            'for': id
                        })
                            .text(' ' + languageObject.title)
                            .prepend(languageObject.flagIcon);

                        $languageButtons.append(
                            $('<div />', { class: 'col-sm-4' })
                                .append($input)
                                .append($label),
                        );
                    }
                    $slide.empty().append($languageButtons);
                });
            }
            MultiStepWizard.addSlide(
                'localize-summary',
                TYPO3.lang['localize.view.summary'],
                '', SeverityEnum.notice,
                TYPO3.lang["localize.wizard.step.selectRecords"],
                async ($slide, settings) => {
                $slide.empty().html('<div class="text-center">' + (await Icons.getIcon('spinner-circle', Icons.sizes.large)) + '</div>');

                    const result = await (await this.getSummary(
                        parseInt($triggerButton.data('pageId'), 10),
                        parseInt($triggerButton.data('languageId'), 10),
                        settings.sourceLanguage
                    )).resolve();

                    $slide.empty();

                    MultiStepWizard.set('records', []);

                    const columns = result.columns.columns;
                    const columnList = result.columns.columnList;

                    columnList.forEach((colPos) => {
                        if (typeof result.records[colPos] === 'undefined') {
                            return;
                        }

                        const column = columns[colPos];
                        const rowElement = document.createElement('div');
                        rowElement.classList.add('row', 'gy-2')

                        result.records[colPos].forEach((record) => {
                            const label = ' (' + record.uid + ') ' + record.title;
                            settings.records.push(record.uid);

                            const columnElement = document.createElement('div');
                            columnElement.classList.add('col-sm-6');

                            const inputGroupElement = document.createElement('div');
                            inputGroupElement.classList.add('input-group');

                            const inputGroupTextElement = document.createElement('span');
                            inputGroupTextElement.classList.add('input-group-text');

                            const checkboxContainerElement = document.createElement('span');
                            checkboxContainerElement.classList.add('form-check', 'form-check-type-toggle');

                            const checkboxInputElement = document.createElement('input');
                            checkboxInputElement.type = 'checkbox';
                            checkboxInputElement.id = 'record-uid-' + record.uid;
                            checkboxInputElement.classList.add('form-check-input', 't3js-localization-toggle-record');
                            checkboxInputElement.checked = true;
                            checkboxInputElement.dataset.uid = record.uid.toString();
                            checkboxInputElement.ariaLabel = label;

                            const labelElement = document.createElement('label');
                            labelElement.classList.add('form-control');
                            labelElement.htmlFor = 'record-uid-' + record.uid;
                            labelElement.innerHTML = record.icon;
                            labelElement.appendChild(document.createTextNode(label));

                            checkboxContainerElement.appendChild(checkboxInputElement);
                            inputGroupTextElement.appendChild(checkboxContainerElement);
                            inputGroupElement.appendChild(inputGroupTextElement);
                            inputGroupElement.appendChild(labelElement);
                            columnElement.appendChild(inputGroupElement);

                            rowElement.appendChild(columnElement);
                        });

                        const fieldsetElement = document.createElement('fieldset');
                        fieldsetElement.classList.add('localization-fieldset');

                        const fieldsetCheckboxContaineElement = document.createElement('div');
                        fieldsetCheckboxContaineElement.classList.add('form-check', 'form-check-type-toggle');

                        const fieldsetCheckboxInputElement = document.createElement('input');
                        fieldsetCheckboxInputElement.classList.add('form-check-input', 't3js-localization-toggle-column');
                        fieldsetCheckboxInputElement.id = 'records-column-' + colPos;
                        fieldsetCheckboxInputElement.type = 'checkbox';
                        fieldsetCheckboxInputElement.checked = true;

                        const fieldsetCheckboxInputLabel = document.createElement('label');
                        fieldsetCheckboxInputLabel.classList.add('form-check-label');
                        fieldsetCheckboxInputLabel.htmlFor = 'records-column-' + colPos;
                        fieldsetCheckboxInputLabel.textContent = column;

                        fieldsetCheckboxContaineElement.appendChild(fieldsetCheckboxInputElement);
                        fieldsetCheckboxContaineElement.appendChild(fieldsetCheckboxInputLabel);
                        fieldsetElement.appendChild(fieldsetCheckboxContaineElement);
                        fieldsetElement.appendChild(rowElement);

                        $slide.append(fieldsetElement);
                    });

                    MultiStepWizard.unlockNextStep();

                    MultiStepWizard.getComponent().on('change', '.t3js-localization-toggle-record', (cmpEvt) => {
                        const $me = $(cmpEvt.currentTarget);
                        const uid = $me.data('uid');
                        const $parent = $me.closest('fieldset');
                        const $columnCheckbox = $parent.find('.t3js-localization-toggle-column');

                        if ($me.is(':checked')) {
                            settings.records.push(uid);
                        } else {
                            const index = settings.records.indexOf(uid);
                            if (index > -1) {
                                settings.records.splice(index, 1);
                            }
                        }

                        const $allChildren = $parent.find('.t3js-localization-toggle-record');
                        const $checkedChildren = $parent.find('.t3js-localization-toggle-record:checked');

                        $columnCheckbox.prop('checked', $checkedChildren.length > 0);
                        $columnCheckbox.prop('__indeterminate', $checkedChildren.length > 0 && $checkedChildren.length < $allChildren.length);

                        if (settings.records.length > 0) {
                            MultiStepWizard.unlockNextStep();
                        } else {
                            MultiStepWizard.lockNextStep();
                        }
                    }).on('change', '.t3js-localization-toggle-column', (toggleEvt) => {
                        const $me = $(toggleEvt.currentTarget);
                        const $children = $me.closest('fieldset').find('.t3js-localization-toggle-record');

                        $children.prop('checked', $me.is(':checked'));
                        $children.trigger('change');
                    });

                });
            MultiStepWizard.addFinalProcessingSlide(async ($slide, settings) => {
                $slide.html(Generation.showSpinnerModal(TYPO3.lang['aiSuite.module.modal.translationInProcess']));
                let modal = MultiStepWizard.setup.$carousel.closest('.modal');
                modal.find('.spinner-wrapper').css('overflow', 'hidden');
                const postData = {
                    'pageId': $triggerButton.data('pageId'),
                    'uuid': $triggerButton.data('uuid')
                }
                StatusHandling.fetchStatus(postData, modal, self)
                await this.localizeRecords(
                    parseInt($triggerButton.data('pageId'), 10),
                    parseInt($triggerButton.data('languageId'), 10),
                    settings.sourceLanguage,
                    settings.localizationMode,
                    settings.records,
                    $triggerButton.data('uuid')
                );
                MultiStepWizard.dismiss();
                document.location.reload();
            }).then(() => {
                MultiStepWizard.show();

                MultiStepWizard.getComponent().on('change', '.t3js-localization-option', (optionEvt) => {
                    MultiStepWizard.set('localizationMode', $(optionEvt.currentTarget).val());
                    MultiStepWizard.unlockNextStep();
                });
            });
        });
    }

    loadAvailableLanguages(pageId, languageId) {
        return new AjaxRequest(TYPO3.settings.ajaxUrls.page_languages).withQueryArguments({
            pageId: pageId,
            languageId: languageId,
        })
            .get();
    }

    getSummary(pageId, languageId, sourceLanguage) {
        return new AjaxRequest(TYPO3.settings.ajaxUrls.records_localize_summary).withQueryArguments({
            pageId: pageId,
            destLanguageId: languageId,
            languageId: sourceLanguage,
        }).get();
    }

    localizeRecords(pageId, languageId, sourceLanguage, localizationMode, uidList, uuid) {
        return new AjaxRequest(TYPO3.settings.ajaxUrls.records_localize).withQueryArguments({
            pageId: pageId,
            srcLanguageId: sourceLanguage,
            destLanguageId: languageId,
            action: localizationMode,
            uidList: uidList,
            uuid: uuid
        }).get();
    }

    async checkLocalizationPermissions() {
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
export default new AiSuiteLocalization();
