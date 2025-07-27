/**
 * Module: @autodudes/ai-suite/context-menu/page-context-menu-actions-actions
 */

import PageLocalization from '@autodudes/ai-suite/translation/page-localization.js';

class ContextMenuActions {
    contextMenuLink (table, uid, data) {
        top.TYPO3.Backend.ContentContainer.setUrl(data.moduleUrl);
    }

    translateWholePage(table, uid, data) {
        const pageId = parseInt(uid);
        PageLocalization.showWholePageTranslationWizard(pageId);
    }
}

export default new ContextMenuActions();
