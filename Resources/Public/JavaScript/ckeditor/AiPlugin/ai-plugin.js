import * as Core from '@ckeditor/ckeditor5-core';
import AiPluginUI from '@autodudes/ai-suite/ckeditor/AiPlugin/ai-plugin-ui.js';

export class AiPlugin extends Core.Plugin {
    static get requires() {
        return [ AiPluginUI ];
    }
}

