import {Plugin} from '@ckeditor/ckeditor5-core';
import {ButtonView,ContextualBalloon,clickOutsideHandler} from '@ckeditor/ckeditor5-ui';
import ModalView from '@autodudes/ai-suite/ckeditor/AiPlugin/ai-plugin-view.js';
import General from '@autodudes/ai-suite/helper/general.js';
import Ajax from '@autodudes/ai-suite/helper/ajax.js';
import Notification from '@typo3/backend/notification.js';
import Provenance from '@autodudes/ai-suite/helper/provenance.js';
import {fallbackIcon, loadPluginIcon} from '@autodudes/ai-suite/ckeditor/plugin-icon.js';

export default class AiPluginUI extends Plugin {
    static get requires() {
        return [ ButtonView ];
    }

    init() {
        const editor = this.editor;
        this.contextualBalloon = editor.plugins.get( ContextualBalloon );
        const aiSuiteConfig = editor.config.get('aiSuite') || (TYPO3.settings && TYPO3.settings.aiSuite) || {};
        this.languageCode = aiSuiteConfig.rteLanguageCode || 'en';
        this.pageId = aiSuiteConfig.pageId || 0;
        this.libraries = [];
        this.promptTemplates = [];
        this.globalInstructions = '';
        this.uuid = '';
        this.selectedContent = '';
        this._prefillLoading = null;
        const iconLoading = loadPluginIcon();

        editor.ui.componentFactory.add( 'AiPlugin', () => {
            const button = new ButtonView();

            button.label = TYPO3.lang['aiSuite.mlangTabsTab'];
            button.icon = fallbackIcon;
            button.tooltip = true;
            button.withText = true;
            iconLoading.then((icon) => {
                button.icon = icon;
            });

            button.on( 'execute', async () => {
                this.selectedContent = '';

                await this._ensurePrefill();
                if (this.libraries.length === 0) {
                    Notification.warning(TYPO3.lang['aiSuite.easyLanguagePlugin.noLibraryFound'], '', 8);
                    return;
                }

                const modalView = await this._createFormView(editor.locale);
                this.selectedContent = await this._getSelectedContent(editor);

                this.contextualBalloon.add( {
                    view: modalView,
                    position: this._getBalloonPositionData(),
                } );
            } );

            return button;
        } );

        this._ensurePrefill();
    }

    _ensurePrefill() {
        if (this._prefillLoading === null) {
            this._prefillLoading = this._fetchRteContent({pageId: this.pageId}).then((prefillContent) => {
                if (prefillContent === null || typeof prefillContent !== 'object') {
                    this._prefillLoading = null;
                    return;
                }
                this.libraries = Object.values(prefillContent['libraries'] || {});
                this.promptTemplates = prefillContent['promptTemplates'] || [];
                this.globalInstructions = prefillContent['globalInstructions'] || '';
                this.uuid = prefillContent['uuid'] || '';
                if (this.libraries.length === 0) {
                    this._prefillLoading = null;
                }
            });
        }
        return this._prefillLoading;
    }

    async _fetchRteContent(data) {
        let res = await Ajax.fetchLibraries('aisuite_ckeditor_libraries', data);
        if (General.isUsable(res)) {
            return res.output;
        } else {
            console.error('Error');
            return null;
        }
    }

    _createFormView() {
        const editor = this.editor;
        const modalView = new ModalView( editor.locale , this.libraries, this.promptTemplates, this.globalInstructions);

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
                alert(TYPO3.lang['aiSuite.module.general.noContentSelected']);
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
                uuid: this.uuid,
                pageId: this.pageId,
            };
            let res = await Ajax.sendRteAjaxRequest( postData );
            if(General.isUsable(res)) {
                editor.model.change( () => {
                    const viewFragment = this.editor.data.processor.toView( res.output );
                    const modelFragment = this.editor.data.toModel( viewFragment );
                    this.editor.model.insertContent(modelFragment);
                    Provenance.recordAssistedForFieldName(editor.sourceElement?.name, 'content', textModel);
                } );
                modalView.element.querySelector('.ck-dialog-inputs').style.display = 'flex';
                modalView.spinner.set( { isVisible: false } );
                modalView.element.querySelector('.ck-spinner-wrapper').style.display = 'none';
            } else {
                console.error('Error');
            }
            this.contextualBalloon.remove( modalView );
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
