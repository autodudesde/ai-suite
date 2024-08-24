import { View } from '@ckeditor/ckeditor5-ui';


export default class RadioView extends View {
    constructor( locale , label, value, name, credits, checked = false) {
        super( locale );
        let title = label;
        if(credits !== undefined && credits !== null) {
            title = label + ' (' + credits + ' ' + TYPO3.lang['aiSuite.module.oneCredit'] + ')';
            if (credits > 1) {
                title = label + ' (' + credits + ' ' + TYPO3.lang['aiSuite.module.multipleCredits'] + ')';
            }
        }

        this.setTemplate( {
            tag: 'label',
            attributes: {
                class: "d-flex flex-row-reverse align-items-center",
            },
            children: [
                title,
                {
                    tag: 'input',
                    attributes: {
                        type: "radio",
                        value: value,
                        checked: checked,
                        name: name
                    },
                },
                {
                    tag: 'span',
                    attributes: {
                        class: "mx-1 checkmark",
                    }
                },
            ],
        } );
    }
}
