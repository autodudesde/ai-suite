import {Plugin} from '@ckeditor/ckeditor5-core';
import {ButtonView} from '@ckeditor/ckeditor5-ui';
import General from '@autodudes/ai-suite/helper/general.js';
import Ajax from '@autodudes/ai-suite/helper/ajax.js';
import Severity from "@typo3/backend/severity.js";
import Modal from '@typo3/backend/modal.js';
import Notification from "@typo3/backend/notification.js";
import Provenance from '@autodudes/ai-suite/helper/provenance.js';
import {fallbackIcon, loadPluginIcon} from '@autodudes/ai-suite/ckeditor/plugin-icon.js';

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
                    Modal.confirm('Information', TYPO3.lang['aiSuite.easyLanguagePlugin.noContentSelectedModalText'], Severity.info, [
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
        let res = await Ajax.fetchLibraries('aisuite_ckeditor_easy_language_libraries');
        if (General.isUsable(res)) {
            return res.output;
        } else {
            console.error('Error');
            return null;
        }
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
        Notification.info(TYPO3.lang['aiSuite.easyLanguagePlugin.inProgress'], TYPO3.lang['aiSuite.easyLanguagePlugin.pleaseWait'], 8);
        let res = await Ajax.sendRteAjaxRequest( postData );
        if(General.isUsable(res)) {
            editor.model.change( () => {
                if(this.modifyWholeContent) {
                    this.editor.data.set(res.output);
                } else {
                    const viewFragment = this.editor.data.processor.toView( res.output );
                    const modelFragment = this.editor.data.toModel( viewFragment );
                    this.editor.model.insertContent(modelFragment);
                }
            } );
            Provenance.recordAssistedForFieldName(editor.sourceElement?.name, 'content', postData.textModel);
            Notification.success(TYPO3.lang['aiSuite.easyLanguagePlugin.success']);
        } else {
            console.error('Error');
            Notification.error(TYPO3.lang['aiSuite.easyLanguagePlugin.failed']);
        }
    }

    _getSelectedContent() {
        return this.editor.data.stringify(this.editor.model.getSelectedContent(this.editor.model.document.selection));
    }
}
