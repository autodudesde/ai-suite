import { View, TextareaView, ButtonView, createDropdown, addListToDropdown, ViewModel } from '@ckeditor/ckeditor5-ui';
import { icons } from '@ckeditor/ckeditor5-core';
import { Collection } from '@ckeditor/ckeditor5-utils';
import RadioView from "@autodudes/ai-suite/ckeditor/RadioView/radio-view.js";
import SpinnerView from "@autodudes/ai-suite/ckeditor/SpinnerView/spinner-view.js";

export default class ModalView extends View {
    constructor(editorLocale, libraries, promptTemplates) {
        super(editorLocale);

        this.cancelButtonView = this._createButton( '', icons.cancel, 'ck-button-cancel' );
        this.cancelButtonView.delegate( 'execute' ).to( this, 'cancel' );
        this.promptInputView = this._createTextarea();
        this.promptInputView.delegate( 'execute' ).to( this, 'promptInputViewActive' );
        this.radioButtonGroup = [];
        let count = 0;
        for (const library of libraries) {
            let radioButton;
            if (count === 0) {
                radioButton = new RadioView(editorLocale, library.name, library.model_identifier, 'textModel', library.weight, true);
                count++;
            } else {
                radioButton = new RadioView(editorLocale, library.name, library.model_identifier, 'textModel', library.weight);
            }
            this.radioButtonGroup.push(radioButton);
        }
        let promtTemplateDisplay = 'flex';
        if(promptTemplates.length > 0) {
            this.dropdown = createDropdown( editorLocale );

            this.dropdown.buttonView.set( {
                label: TYPO3.lang['tx_aisuite.module.general.selectPromptTemplate'],
                withText: true
            } );

            const items = new Collection();
            promptTemplates.forEach((promptTemplate) => {
                items.add({
                    type: 'button',
                    model: new ViewModel({
                        label: promptTemplate.name,
                        withText: true,
                        promptTemplate: promptTemplate.prompt,
                        isActive: false
                    })
                });
            });

            addListToDropdown(this.dropdown, items);

            this.listenTo(this.dropdown, 'execute', evt => {
                const selectedItem = evt.source;
                items.forEach((item) => { item.model.set('isActive', false) });
                selectedItem.set('isActive', true);
                this.promptInputView.element.value = selectedItem.promptTemplate;
            });
        } else {
            promtTemplateDisplay = 'none';
            this.dropdown = '';
        }
        // TODO: change icon
        this.saveButtonView = this._createButton(TYPO3.lang['tx_aisuite.module.PageContent.submit'], icons.pencil, 'ck-button-save');
        this.spinner = new SpinnerView(editorLocale, TYPO3.lang['tx_aisuite.module.general.contentGenerationInProcess']);

        this.setTemplate({
            tag: 'div',
            attributes: {
                tabindex: -1,
                class: 'ck-ai-plugin ck-dialog-wrapper',
            },
            children: [
                {
                    tag: 'div',
                    attributes: {
                        class: 'ck-dialog-inputs',
                        style: {
                            display: 'flex',
                            flexDirection: 'column',
                            padding: 'var(--ck-spacing-large)',
                            whiteSpace: 'initial',
                            width: '100%',
                            minWidth: '450px',
                            maxWidth: '800px'
                        },
                    },
                    children: [
                        {
                            tag: 'div',
                            attributes: {
                                class: 'ck-dialog-headerbar',
                                style: {
                                    display: 'flex',
                                    justifyContent: 'space-between',
                                    fontWeight: 'bold',
                                    fontSize: '1.1rem',
                                    marginBottom: 'var(--ck-spacing-medium)',
                                    width: '106%',
                                    marginLeft: '-13px',
                                    marginTop: '-12px',
                                    backgroundColor: '#eee',
                                    borderBottom: '1px solid black',
                                    padding: '0.5rem 1rem'
                                }
                            },
                            children: [
                                TYPO3.lang['tx_aisuite.module.general.adjustContentWithAi'],
                                this.cancelButtonView
                            ]
                        },
                        {
                            tag: 'div',
                            attributes: {
                                class: 'ck-prompt-template-title',
                                style: {
                                    display: promtTemplateDisplay,
                                    width: '100%',
                                    fontWeight: 'bold',
                                    marginBottom: 'var(--ck-spacing-medium)'
                                }
                            },
                            children: [
                                TYPO3.lang['tx_aisuite.module.general.promptTemplates'],
                            ]
                        },
                        {
                            tag: 'div',
                            attributes: {
                                class: 'ck-prompt-template-dropdown',
                                style: {
                                    display: promtTemplateDisplay,
                                    width: '100%',
                                    marginBottom: 'var(--ck-spacing-large)'
                                }
                            },
                            children: [
                                this.dropdown
                            ]
                        },
                        {
                            tag: 'div',
                            attributes: {
                                class: 'ck-prompt-input-label',
                                style: {
                                    display: 'flex',
                                    width: '100%',
                                    fontWeight: 'bold',
                                    marginBottom: 'var(--ck-spacing-medium)'
                                }
                            },
                            children: [
                                TYPO3.lang['tx_aisuite.module.general.prompt'],
                            ]
                        },
                        {
                            tag: 'div',
                            attributes: {
                                class: 'ck-prompt-input',
                                style: {
                                    display: 'flex',
                                    width: '100%',
                                    marginBottom: 'var(--ck-spacing-large)'
                                }
                            },
                            children: [
                                this.promptInputView,
                            ]
                        },
                        {
                            tag: 'div',
                            attributes: {
                                class: 'ck-prompt-input-label',
                                style: {
                                    display: 'flex',
                                    width: '100%',
                                    fontWeight: 'bold',
                                    marginBottom: 'var(--ck-spacing-medium)'
                                }
                            },
                            children: [
                                TYPO3.lang['AiSuite.textGenerationLibrary'],
                            ]
                        },
                        {
                            tag: 'div',
                            attributes: {
                                class: 'ck-radio-button-group',
                                style: {
                                    display: 'flex',
                                    width: '100%',
                                    marginBottom: '1rem',
                                    position: 'relative'
                                }
                            },
                            children: this.radioButtonGroup
                        },
                        {
                            tag: 'div',
                            attributes: {
                                class: 'ck-save-button',
                                style: {
                                    display: 'flex'
                                }
                            },
                            children: [
                                this.saveButtonView,
                            ]
                        },
                    ],
                },
                {
                    tag: 'div',
                    attributes: {
                        class: 'ck-spinner-wrapper',
                        style: {
                            width: '100%',
                            height: '100%',
                            justifyContent: 'center',
                            alignItems: 'center',
                            display: 'none'
                        }
                    },
                    children: [
                        this.spinner
                    ]
                }
            ]
        });
    }

    render() {
        super.render();
    }

    _createTextarea( label ) {
        const labeledTextarea = new TextareaView();
        labeledTextarea.minRows = 5;
        labeledTextarea.maxRows = 10;
        labeledTextarea.resize = 'vertical';
        return labeledTextarea;
    }

    _createButton( label, icon, className, ariaLabel= '') {
        const button = new ButtonView();
        button.set( {
            label,
            icon,
            tooltip: true,
            withText: true,
            class: className,
            ariaLabel: ariaLabel
        } );

        return button;
    }
}
