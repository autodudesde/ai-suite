import Icons from '@typo3/backend/icons.js';
import General from "@autodudes/ai-suite/helper/general.js";
import Ajax from "@autodudes/ai-suite/helper/ajax.js";

class Translation {
    async addAvailableLibraries(permissions, allowTranslate = false, allowCopy = false) {
        let actionPrefix = 'copyFromLanguage'
        if(allowTranslate) {
            actionPrefix = 'localize'
        }
        let actions = [];
        let res = await Ajax.fetchLibraries('aisuite_localization_libraries');
        if (General.isUsable(res) && res.success === true) {
            const libraries = res.output.libraries;
            if (libraries.length === 0) {
                actions.push('<div class="row">' +
                    '<div class="col-sm-12">' +
                    '<div class="alert alert-warning">' +
                    '<p class="alert-message">' + TYPO3.lang['aiSuite.module.modal.noTranslationLibrariesAvailable'] + '</p>' +
                    '</div>' +
                    '</div>' +
                    '</div>');
            } else {
                if(permissions) {
                    for (const library of libraries) {
                        const libraryIcon = await Icons.getIcon('tx-aisuite-model-' + library.model_identifier, Icons.sizes.large);
                        const onlyPaid = library.only_paid === 1 && res.output.paidRequestsAvailable === false ? '<span class="badge badge-danger mx-2">(only paid)</span>' : '';
                        const disabled = library.only_paid === 1 && res.output.paidRequestsAvailable === false ? 'style="pointer-events: none"' : '';
                        actions.push(`
                        <div class="row align-items-center mb-4">
                            <div class="col-sm-3">
                              <input type="radio" class="btn-check t3js-localization-option" name="mode" id="mode_translate_` + library.model_identifier + `" value="` + actionPrefix + library.model_identifier + `">
                              <label class="btn btn-default btn-block-vertical" for="mode_translate_` + library.model_identifier + `" data-action="` + actionPrefix + library.model_identifier + `" style="white-space: normal;text-align: center;">
                               ` + libraryIcon + `
                                ` + library.name + `
                                ` + onlyPaid + `
                              </label>
                            </div>
                            <div class="col-sm-9">
                                <p class="text-body-secondary">` + library.info + `</p>
                            </div>
                          </div>
                    `);
                    }
                }
            }
        } else {
            actions.push('<div class="row">' +
                '<div class="col-sm-12">' +
                '<div class="alert alert-danger">' +
                '<p class="alert-message">' + TYPO3.lang['aiSuite.module.modal.noTranslationLibrariesAvailable'] + '</p>' +
                '</div>' +
                '</div>' +
                '</div>');
        }
        if (allowTranslate) {
            const localizeIconMarkup = await Icons.getIcon('actions-localize', Icons.sizes.large);
            actions.push(
                '<div class="row">'
                + '<div class="col-sm-3">'
                + '<input class="btn-check t3js-localization-option" type="radio" name="mode" id="mode_translate" value="localize">'
                + '<label class="btn btn-default btn-block-vertical" for="mode_translate" data-action="localize">'
                + localizeIconMarkup
                + TYPO3.lang['localize.wizard.button.translate']
                + '</label>'
                + '</div>'
                + '<div class="col-sm-9">'
                + '<p class="text-body-secondary">' + TYPO3.lang['localize.educate.translate'] + '</p>'
                + '</div>'
                + '</div>',
            );
        }
        if (allowCopy) {
            const copyIconMarkup = await Icons.getIcon('actions-edit-copy', Icons.sizes.large);
            actions.push(
                '<div class="row">'
                + '<div class="col-sm-3">'
                + '<input class="btn-check t3js-localization-option" type="radio" name="mode" id="mode_copy" value="copyFromLanguage">'
                + '<label class="btn btn-default btn-block-vertical" for="mode_copy" data-action="copy">'
                + copyIconMarkup
                + TYPO3.lang['localize.wizard.button.copy']
                + '</label>'
                + '</div>'
                + '<div class="col-sm-9">'
                + '<p class="t3js-helptext t3js-helptext-copy text-body-secondary">' + TYPO3.lang['localize.educate.copy'] + '</p>'
                + '</div>'
                + '</div>',
            );
        }
        return actions;
    }
}

export default new Translation();
