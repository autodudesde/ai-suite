import { View, TextareaView, ButtonView, createDropdown, addListToDropdown, ViewModel } from '@ckeditor/ckeditor5-ui';
import { Collection } from '@ckeditor/ckeditor5-utils';
import RadioView from "@autodudes/ai-suite/ckeditor/RadioView/radio-view.js";
import SpinnerView from "@autodudes/ai-suite/ckeditor/SpinnerView/spinner-view.js";
import GlobalInstructions from "@autodudes/ai-suite/helper/global-instructions.js";

export default class ModalView extends View {
    constructor(editorLocale, libraries, promptTemplates, globalInstructions) {
        super(editorLocale);
        const iconCancel = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><g fill="currentColor"><path d="M11.9 5.5 9.4 8l2.5 2.5c.2.2.2.5 0 .7l-.7.7c-.2.2-.5.2-.7 0L8 9.4l-2.5 2.5c-.2.2-.5.2-.7 0l-.7-.7c-.2-.2-.2-.5 0-.7L6.6 8 4.1 5.5c-.2-.2-.2-.5 0-.7l.7-.7c.2-.2.5-.2.7 0L8 6.6l2.5-2.5c.2-.2.5-.2.7 0l.7.7c.2.2.2.5 0 .7z"/></g></svg>';
        this.cancelButtonView = this._createButton( '', iconCancel, 'ck-button-cancel' );

        this.globalInstructions = globalInstructions;
        const iconEye = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><g fill="currentColor"><path d="M8.07 3C4.112 3 1 5.286 1 8s2.97 5 7 5c3.889 0 7-2.286 7-4.93C15 5.285 11.889 3.142 8.212 3h-.141Zm-.025 1.127c.141 0 .423.141.423.282s-.14.282-.423.282c-.845 0-1.69.704-1.69 1.55 0 .14-.141.282-.423.282-.282 0-.423-.141-.423-.282.141-1.127 1.268-2.114 2.536-2.114ZM2 8.03c0-1.298 1.017-2.591 2.647-3.312-.296.432-.296 1.01-.296 1.587 0 2.02 1.63 3.606 3.703 3.606 2.074 0 3.704-1.587 3.704-3.606 0-.577-.148-1.01-.296-1.443C12.943 5.582 14 6.875 14 8.029c-.148 2.02-2.841 3.924-6 3.971-3.36-.047-6-1.95-6-3.97Z"/></g></svg>';
        this.globalInstructionsButtonView = this._createButton(TYPO3.lang['aiSuite.globalInstructions.show_global_instructions'], iconEye, 'ck-button-global-instructions' );
        this.globalInstructionsButtonView.set({
            tooltip: TYPO3.lang['aiSuite.globalInstructions.tooltip']
        });
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
        let promptTemplateDisplay = 'flex';
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
            promptTemplateDisplay = 'none';
            this.dropdown = '';
        }
        const iconPencil = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><g fill="currentColor"><path d="m9.293 3.293-8 8A.997.997 0 0 0 1 12v3h3c.265 0 .52-.105.707-.293l8-8-3.414-3.414zM8.999 5l.5.5-5 5-.5-.5 5-5zM4 14H3v-1H2v-1l1-1 2 2-1 1zM13.707 5.707l1.354-1.354a.5.5 0 0 0 0-.707L12.354.939a.5.5 0 0 0-.707 0l-1.354 1.354 3.414 3.414z"/></g></svg>';
        this.saveButtonView = this._createButton(TYPO3.lang['tx_aisuite.module.PageContent.submit'], iconPencil, 'ck-button-save');
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
                            maxWidth: '700px'
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
                                    width: '100%'
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
                                    display: promptTemplateDisplay,
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
                                    display: promptTemplateDisplay,
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
                                    //fontWeight: 'bold',
                                    marginBottom: 'var(--ck-spacing-medium)'
                                }
                            },
                            children: [
                                {
                                    tag: 'span',
                                    attributes: {
                                        style: {
                                            fontWeight: 'bold',
                                        }
                                    },
                                    children: [
                                        TYPO3.lang['tx_aisuite.module.general.prompt']
                                    ]
                                },
                                this.globalInstructions ? this.globalInstructionsButtonView : null,
                            ].filter(child => child !== null)
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
                                    position: 'relative',
                                    flexWrap: 'wrap'
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

        if (this.globalInstructions) {
            GlobalInstructions.initializeTooltipForElement(
                this.globalInstructionsButtonView.element,
                this.globalInstructions,
                () => this.promptInputView.element.value
            );
        }
    }

    _createTextarea() {
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
