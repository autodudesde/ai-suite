import { View } from '@ckeditor/ckeditor5-ui';


export default class SpinnerView extends View {
    constructor( locale , message) {
        super( locale );

        this.setTemplate( {
            tag: 'div',
            attributes: {
                class: "spinner-wrapper",
            },
            children: [
                {
                    tag: 'div',
                    attributes: {
                        class: "spinner-overlay active darken",
                    },
                    children: [
                        {
                            tag: 'div',
                            attributes: {
                                class: "spinner",
                            }
                        },
                        {
                            tag: 'p',
                            attributes: {
                                class: "message",
                            },
                            children: [
                                message
                            ]
                        },
                    ],
                }
            ],
        } );
    }
}
