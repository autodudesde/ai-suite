import {Plugin} from '@ckeditor/ckeditor5-core';
import {ButtonView,ContextualBalloon,clickOutsideHandler} from '@ckeditor/ckeditor5-ui';
import ModalView from '@autodudes/ai-suite/ckeditor/AiPlugin/ai-plugin-view.js';
import General from '@autodudes/ai-suite/helper/general.js';
import Ajax from '@autodudes/ai-suite/helper/ajax.js';
import Notification from '@typo3/backend/notification.js';
import Provenance from '@autodudes/ai-suite/helper/provenance.js';
import {fallbackIcon, loadPluginIcon} from '@autodudes/ai-suite/ckeditor/plugin-icon.js';
import {openCompareModal} from '@autodudes/ai-suite/ckeditor/CompareModal/compare-modal.js';

const TARGET_MARKER = 'aiSuite:target';

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
                await this._ensurePrefill();
                if (this.libraries.length === 0) {
                    Notification.warning(TYPO3.lang['aiSuite.easyLanguagePlugin.noLibraryFound'], '', 8);
                    return;
                }

                const modalView = this._createFormView();

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
        const res = await Ajax.fetchLibraries('aisuite_ckeditor_libraries', data);
        return General.isUsable(res) ? res.output : null;
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
            this._clearTarget();
        } );
        this.listenTo( modalView.promptInputView, 'input', () => modalView.showPromptError(false) );
        this.listenTo( modalView.saveButtonView, 'execute', async () => {
            const prompt = modalView.getPrompt();
            if (prompt.trim() === '') {
                modalView.showPromptError(true);
                return;
            }
            this.selectedContent = this._getSelectedContent();
            const textModel = modalView.getSelectedModel();
            this._markTarget();
            modalView.setLoading(true);

            const postData = {
                prompt: prompt,
                textModel: textModel,
                selectedContent: this.selectedContent,
                wholeContent: editor.getData(),
                languageCode: this.languageCode,
                uuid: this.uuid,
                pageId: this.pageId,
            };
            const res = await Ajax.sendRteAjaxRequest( postData );
            modalView.setLoading(false);
            if (!General.isUsable(res)) {
                return;
            }
            if (this.contextualBalloon.hasView(modalView)) {
                this.contextualBalloon.remove( modalView );
            }
            openCompareModal(res.diff ?? '', {
                onAccept: () => {
                    this._insertAtTarget(res.output);
                    Provenance.recordAssistedForFieldName(editor.sourceElement?.name, 'content', textModel);
                    this._clearTarget();
                },
                onDiscard: () => this.contextualBalloon.add( { view: modalView, position: this._getBalloonPositionData() } ),
            });
        });
        return modalView;
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

    _insertAtTarget(html) {
        const model = this.editor.model;
        const marker = model.markers.get(TARGET_MARKER);
        model.change( (writer) => {
            if (marker) {
                writer.setSelection(marker.getRange());
            }
            const modelFragment = this.editor.data.toModel( this.editor.data.processor.toView( html ) );
            model.insertContent(modelFragment);
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
