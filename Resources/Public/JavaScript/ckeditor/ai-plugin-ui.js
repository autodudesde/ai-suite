import {Plugin} from '@ckeditor/ckeditor5-core';
import {ButtonView,ContextualBalloon,clickOutsideHandler} from '@ckeditor/ckeditor5-ui';
import ModalView from '@autodudes/ai-suite/ckeditor/ai-plugin-view.js';
import General from '@autodudes/ai-suite/helper/general.js';
import Ajax from '@autodudes/ai-suite/helper/ajax.js';

export default class AiPluginUI extends Plugin {
    static get requires() {
        return [ ButtonView ];
    }

    async init() {
        const editor = this.editor;
        this.contextualBalloon = editor.plugins.get( ContextualBalloon );
        this.languageCode = TYPO3.settings.aiSuite.rteLanguageCode;
        const prefillContent = await this._fetchRteContent();
        this.libraries = prefillContent['libraries'];
        this.promptTemplates = prefillContent['promptTemplates'];
        this.uuid = prefillContent['uuid'];
        this.selectedContent = '';

        editor.ui.componentFactory.add( 'AiPlugin', () => {
            const button = new ButtonView();

            button.label = 'AI Suite';
            button.icon = '<svg version="1.1" id="Ebene_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 64 64" style="enable-background:new 0 0 64 64;" xml:space="preserve"><style type="text/css">.st0{fill:url(#Rechteck_44_00000013910148143574501140000004453864656853440669_);}.st1{fill:#FFFFFF;}</style><linearGradient id="Rechteck_44_00000121964855297047390660000008192662802121740730_" gradientUnits="userSpaceOnUse" x1="-387.6407" y1="330.0303" x2="-387.5814" y2="329.9711" gradientTransform="matrix(1080 0 0 -1080 418651.9375 356432.75)"><stop offset="0" style="stop-color:#F09C42"/><stop offset="1" style="stop-color:#AF4E26"/><stop offset="1" style="stop-color:#381C19"/></linearGradient><rect id="Rechteck_44" style="fill:url(#Rechteck_44_00000121964855297047390660000008192662802121740730_);" width="64" height="64"/><path id="Vereinigungsmenge_8" className="st1" d="M25.3,44.7c4-1,4.7-1.8,5.6-6.2c0.9,4.5,1.6,5.2,5.6,6.2c-4,1-4.7,1.7-5.6,6.2 C30,46.5,29.3,45.7,25.3,44.7z M25.7,27c9.1-2.2,10.7-3.9,12.6-14c2,10,3.6,11.8,12.6,14c-9.1,2.2-10.7,3.9-12.6,14 C36.4,31,34.8,29.2,25.7,27L25.7,27z M13,32.2c4.6-1.1,5.4-2,6.4-7c1,5,1.8,5.9,6.4,7c-4.6,1.1-5.4,2-6.4,7 C18.4,34.2,17.6,33.3,13,32.2z"/></svg>';
            button.tooltip = true;
            button.withText = true;

            button.on( 'execute', () => {
                this.selectedContent = '';

                const modalView = this._createFormView(editor.locale);
                this.selectedContent = this._getSelectedContent(editor);

                this.contextualBalloon.add( {
                    view: modalView,
                    position: this._getBalloonPositionData(),
                } );
            } );

            return button;
        } );
    }

    async _fetchRteContent() {
        let res = await Ajax.fetchLibraries('aisuite_ckeditor_libraries');
        if (General.isUsable(res)) {
            return res.output;
        } else {
            console.error('Error');
            return null;
        }
    }

    _createFormView() {
        const editor = this.editor;
        const modalView = new ModalView( editor.locale , this.libraries, this.promptTemplates);

        clickOutsideHandler( {
            emitter: modalView,
            activator: () => this.contextualBalloon.visibleView === modalView,
            contextElements: [ this.contextualBalloon.view.element ],
            callback: () => this.contextualBalloon.remove( modalView )
        } );
        this.listenTo( modalView, 'cancel', () => {
            this.contextualBalloon.remove( modalView );
        } );
        this.listenTo( editor.model.document.selection, 'change:range', (evt) => {
            this.selectedContent = this._getSelectedContent();
        });
        this.listenTo( modalView.saveButtonView, 'execute', async () => {
            const prompt = modalView.promptInputView.element.value;
            if(prompt.trim() === '') {
                alert(TYPO3.lang['tx_aisuite.module.general.noContentSelected']);
                return;
            }
            modalView.element.querySelector('.ck-dialog-inputs').style.display = 'none';
            modalView.element.querySelector('.ck-spinner-wrapper').style.display = 'flex';

            let textModel = '';
            modalView.radioButtonGroup.forEach( (radioButtonGroup) => {
                radioButtonGroup.element.childNodes.forEach( (node) => {
                    if(node.type !== undefined && node.type === 'radio' && node.checked) {
                        textModel = node.value;
                    }
                });
            })
            const postData = {
                prompt: prompt,
                textModel: textModel,
                selectedContent: this.selectedContent,
                wholeContent: editor.getData(),
                languageCode: this.languageCode,
                uuid: this.uuid
            };
            let res = await Ajax.sendRteAjaxRequest( postData );
            if(General.isUsable(res)) {
                editor.model.change( () => {
                    const viewFragment = this.editor.data.processor.toView( res.output );
                    const modelFragment = this.editor.data.toModel( viewFragment );
                    this.editor.model.insertContent(modelFragment);
                } );
                modalView.element.querySelector('.ck-dialog-inputs').style.display = 'flex';
                modalView.spinner.set( { isVisible: false } );
                modalView.element.querySelector('.ck-spinner-wrapper').style.display = 'none';
            } else {
                console.error('Error');
            }
        });
        return modalView;
    }

    _getSelectedContent() {
        return this.editor.data.stringify(this.editor.model.getSelectedContent(this.editor.model.document.selection));
    }

    _getBalloonPositionData() {
        const view = this.editor.editing.view;
        const viewDocument = view.document;
        let target = null;

        target = () => view.domConverter.viewRangeToDom(
            viewDocument.selection.getFirstRange()
        );

        return {
            target
        };
    }
}
