import {Plugin} from '@ckeditor/ckeditor5-core';
import {ButtonView} from '@ckeditor/ckeditor5-ui';
import General from '@autodudes/ai-suite/helper/general.js';
import Ajax from '@autodudes/ai-suite/helper/ajax.js';
import Severity from "@typo3/backend/severity.js";
import Modal from '@typo3/backend/modal.js';
import Notification from "@typo3/backend/notification.js";
import Provenance from '@autodudes/ai-suite/helper/provenance.js';
import {fallbackIcon, loadPluginIcon} from '@autodudes/ai-suite/ckeditor/plugin-icon.js';
import {openCompareModal} from '@autodudes/ai-suite/ckeditor/CompareModal/compare-modal.js';

const TARGET_MARKER = 'aiSuite:easyLanguageTarget';

export default class AiEasyLanguagePluginUi extends Plugin {
    static get requires() {
        return [ ButtonView ];
    }

    init() {
        const editor = this.editor;
        const aiSuiteConfig = editor.config.get('aiSuite') || (TYPO3.settings && TYPO3.settings.aiSuite) || {};
        this.languageCode = aiSuiteConfig.rteLanguageCode || 'en';
        this.selectedContent = '';
        this.library = {};
        this.uuid = '';
        this._prefillLoading = null;
        const iconLoading = loadPluginIcon();

        editor.ui.componentFactory.add( 'AiEasyLanguagePlugin', () => {
            const button = new ButtonView();

            button.label = TYPO3.lang['aiSuite.easyLanguagePlugin.title'];
            button.icon = fallbackIcon;
            button.tooltip = true;
            button.withText = true;
            iconLoading.then((icon) => {
                button.icon = icon;
            });

            button.on( 'execute', async () => {
                await this._ensurePrefill();
                if(Object.keys(this.library).length === 0) {
                    Notification.warning(TYPO3.lang['aiSuite.easyLanguagePlugin.noLibraryFound'], '', 8);
                    return;
                }
                this.selectedContent = '';
                this.modifyWholeContent = false;

                this.selectedContent = await this._getSelectedContent(editor);

                if(this.selectedContent.trim() === '') {
                    const self = this;
                    Modal.confirm(TYPO3.lang['aiSuite.module.general.noContentSelectedTitle'], TYPO3.lang['aiSuite.easyLanguagePlugin.noContentSelectedModalText'], Severity.info, [
                        {
                            text: TYPO3.lang['aiSuite.easyLanguagePlugin.useWholeContent'],
                            active: true,
                            trigger: async function(event, modal) {
                                modal.hideModal();
                                self.modifyWholeContent = true;
                                self.selectedContent = editor.getData();
                                await self._sendRequest(editor);
                            }
                        }, {
                            text: TYPO3.lang['aiSuite.easyLanguagePlugin.abort'],
                            trigger: function(event, modal) {
                                modal.hideModal();
                            }
                        }
                    ]);
                } else {
                    await this._sendRequest(editor);
                }
            });
            return button;
        } );

        this._ensurePrefill();
    }

    _ensurePrefill() {
        if (this._prefillLoading === null) {
            this._prefillLoading = this._fetchRteContent().then((prefillContent) => {
                if (prefillContent === null || typeof prefillContent !== 'object') {
                    this._prefillLoading = null;
                    return;
                }
                this.library = prefillContent['library'] || {};
                this.uuid = prefillContent['uuid'] || '';
                if (Object.keys(this.library).length === 0) {
                    this._prefillLoading = null;
                }
            });
        }
        return this._prefillLoading;
    }

    async _fetchRteContent() {
        const res = await Ajax.fetchLibraries('aisuite_ckeditor_easy_language_libraries');
        return General.isUsable(res) ? res.output : null;
    }

    async _sendRequest(editor) {
        const firstKey = Object.keys(this.library)[0];
        const postData = {
            textModel: this.library[firstKey].model_identifier,
            selectedContent: this.selectedContent,
            languageCode: this.languageCode,
            uuid: this.uuid,
            type: 'easy-language',
        };
        const modifyWholeContent = this.modifyWholeContent;
        if (!modifyWholeContent) {
            this._markTarget();
        }
        Notification.info(TYPO3.lang['aiSuite.easyLanguagePlugin.inProgress'], TYPO3.lang['aiSuite.easyLanguagePlugin.pleaseWait'], 8);
        const res = await Ajax.sendRteAjaxRequest( postData );
        if (!General.isUsable(res)) {
            this._clearTarget();
            Notification.error(TYPO3.lang['aiSuite.easyLanguagePlugin.failed']);
            return;
        }
        openCompareModal(res.diff ?? '', {
            onAccept: () => {
                this._apply(res.output, modifyWholeContent);
                Provenance.recordAssistedForFieldName(editor.sourceElement?.name, 'content', postData.textModel);
                Notification.success(TYPO3.lang['aiSuite.easyLanguagePlugin.success']);
            },
            onDiscard: () => this._clearTarget(),
        });
    }

    _apply(html, modifyWholeContent) {
        const model = this.editor.model;
        if (modifyWholeContent) {
            this.editor.data.set(html);
            return;
        }
        const marker = model.markers.get(TARGET_MARKER);
        model.change( (writer) => {
            if (marker) {
                writer.setSelection(marker.getRange());
            }
            model.insertContent(this.editor.data.toModel( this.editor.data.processor.toView( html ) ));
        } );
        this._clearTarget();
    }

    _markTarget() {
        const model = this.editor.model;
        model.change( (writer) => {
            const range = model.document.selection.getFirstRange();
            if (model.markers.has(TARGET_MARKER)) {
                writer.updateMarker(TARGET_MARKER, { range });
            } else {
                writer.addMarker(TARGET_MARKER, { range, usingOperation: false, affectsData: false });
            }
        } );
    }

    _clearTarget() {
        const model = this.editor.model;
        if (model.markers.has(TARGET_MARKER)) {
            model.change( (writer) => writer.removeMarker(TARGET_MARKER) );
        }
    }

    _getSelectedContent() {
        return this.editor.data.stringify(this.editor.model.getSelectedContent(this.editor.model.document.selection));
    }
}
