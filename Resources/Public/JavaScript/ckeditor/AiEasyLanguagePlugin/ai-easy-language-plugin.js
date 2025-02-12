import * as Core from '@ckeditor/ckeditor5-core';
import AiEasyLanguagePluginUi from '@autodudes/ai-suite/ckeditor/AiEasyLanguagePlugin/ai-easy-language-plugin-ui.js';

export default class AiEasyLanguagePlugin extends Core.Plugin {
    static get requires() {
        return [ AiEasyLanguagePluginUi ];
    }
}

