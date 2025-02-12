import {Plugin} from '@ckeditor/ckeditor5-core';
import {ButtonView} from '@ckeditor/ckeditor5-ui';
import General from '@autodudes/ai-suite/helper/general.js';
import Ajax from '@autodudes/ai-suite/helper/ajax.js';
import Severity from "@typo3/backend/severity.js";
import Modal from '@typo3/backend/modal.js';
import Notification from "@typo3/backend/notification.js";

export default class AiEasyLanguagePluginUi extends Plugin {
    static get requires() {
        return [ ButtonView ];
    }

    async init() {
        const editor = this.editor;
        if(TYPO3.settings.aiSuite) {
            this.languageCode = TYPO3.settings.aiSuite.rteLanguageCode;
        } else {
            this.languageCode = 'en';
        }
        this.selectedContent = '';
        const prefillContent = await this._fetchRteContent();
        this.library = prefillContent['library'];
        this.uuid = prefillContent['uuid'];

        editor.ui.componentFactory.add( 'AiEasyLanguagePlugin', () => {
            const button = new ButtonView();

            button.label = 'Leichte Sprache';
            button.icon = '<svg version="1.1" id="Ebene_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64" style="enable-background:new 0 0 64 64;" xml:space="preserve"><style type="text/css">.st0{fill:url(#Rechteck_44_00000013910148143574501140000004453864656853440669_);}.st1{fill:#FFFFFF;}</style><linearGradient id="Rechteck_44_00000121964855297047390660000008192662802121740730_" gradientUnits="userSpaceOnUse" x1="-387.6407" y1="330.0303" x2="-387.5814" y2="329.9711" gradientTransform="matrix(1080 0 0 -1080 418651.9375 356432.75)"><stop offset="0" style="stop-color:#F09C42"/><stop offset="1" style="stop-color:#AF4E26"/><stop offset="1" style="stop-color:#381C19"/></linearGradient><rect id="Rechteck_44" style="fill:url(#Rechteck_44_00000121964855297047390660000008192662802121740730_);" width="64" height="64"/><path id="Vereinigungsmenge_8" className="st1" d="M25.3,44.7c4-1,4.7-1.8,5.6-6.2c0.9,4.5,1.6,5.2,5.6,6.2c-4,1-4.7,1.7-5.6,6.2 C30,46.5,29.3,45.7,25.3,44.7z M25.7,27c9.1-2.2,10.7-3.9,12.6-14c2,10,3.6,11.8,12.6,14c-9.1,2.2-10.7,3.9-12.6,14 C36.4,31,34.8,29.2,25.7,27L25.7,27z M13,32.2c4.6-1.1,5.4-2,6.4-7c1,5,1.8,5.9,6.4,7c-4.6,1.1-5.4,2-6.4,7 C18.4,34.2,17.6,33.3,13,32.2z"/></svg>';
            button.tooltip = true;
            button.withText = true;

            button.on( 'execute', async () => {
                if(this.library.length === 0) {
                    Notification.warning(TYPO3.lang['AiSuite.easyLanguagePlugin.noLibraryFound'], '', 8);
                    return;
                }
                this.selectedContent = '';
                this.modifyWholeContent = false;

                this.selectedContent = await this._getSelectedContent(editor);

                if(this.selectedContent.trim() === '') {
                    const self = this;
                    Modal.confirm('Information', TYPO3.lang['AiSuite.easyLanguagePlugin.noContentSelectedModalText'], Severity.info, [
                        {
                            text: TYPO3.lang['AiSuite.easyLanguagePlugin.useWholeContent'],
                            active: true,
                            trigger: async function() {
                                self.modifyWholeContent = true;
                                self.selectedContent = editor.getData();
                                await self._sendRequest(editor);
                            }
                        }, {
                            text: TYPO3.lang['AiSuite.easyLanguagePlugin.abort'],
                            trigger: function() {
                                Modal.dismiss();
                            }
                        }
                    ]);
                } else {
                    await this._sendRequest(editor);
                }
            });
            return button;
        } );
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
        Modal.dismiss();
        const postData = {
            textModel: this.library[0].model_identifier,
            selectedContent: this.selectedContent,
            languageCode: this.languageCode,
            uuid: this.uuid,
            type: 'easy-language',
        };
        Notification.info(TYPO3.lang['AiSuite.easyLanguagePlugin.inProgress'], TYPO3.lang['AiSuite.easyLanguagePlugin.pleaseWait'], 8);
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
            Notification.success(TYPO3.lang['AiSuite.easyLanguagePlugin.success']);
        } else {
            console.error('Error');
            Notification.error(TYPO3.lang['AiSuite.easyLanguagePlugin.failed']);
        }
    }

    _getSelectedContent() {
        return this.editor.data.stringify(this.editor.model.getSelectedContent(this.editor.model.document.selection));
    }
}
