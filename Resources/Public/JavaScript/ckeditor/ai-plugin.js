import * as Core from '@ckeditor/ckeditor5-core';
import AiPluginUI from '@autodudes/ai-suite/ckeditor/ai-plugin-ui.js';

export default class AiPlugin extends Core.Plugin {
    static get requires() {
        return [ AiPluginUI ];
    }
}

