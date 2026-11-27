import { View } from '@ckeditor/ckeditor5-ui';


export default class RadioView extends View {
    constructor( locale , label, value, name, credits, checked = false) {
        super( locale );
        let creditsLabel = '';
        if(credits !== undefined && credits !== null) {
            creditsLabel = credits + ' ' + TYPO3.lang[credits > 1 ? 'aiSuite.module.multipleCredits' : 'aiSuite.module.oneCredit'];
        }

        this.setTemplate( {
            tag: 'label',
            attributes: {
                class: 'ck-ai-radio',
            },
            children: [
                {
                    tag: 'input',
                    attributes: {
                        type: 'radio',
                        class: 'ck-ai-radio__input',
                        value: value,
                        checked: checked,
                        name: name
                    },
                },
                {
                    tag: 'span',
                    attributes: {
                        class: 'ck-ai-radio__mark',
                        'aria-hidden': 'true',
                    }
                },
                {
                    tag: 'span',
                    attributes: {
                        class: 'ck-ai-radio__text',
                    },
                    children: [
                        {
                            tag: 'span',
                            attributes: {
                                class: 'ck-ai-radio__label',
                            },
                            children: [ label ]
                        },
                        creditsLabel !== '' ? {
                            tag: 'span',
                            attributes: {
                                class: 'ck-ai-radio__credits',
                            },
                            children: [ '(' + creditsLabel + ')' ]
                        } : null,
                    ].filter(child => child !== null)
                },
            ],
        } );
    }
}
