/**
 * Module: @autodudes/ai-suite/context-menu/page-context-menu-actions-actions
 */

class ContextMenuActions {
    contextMenuLink (table, uid, data) {
        if (data.action === 'translateWholePage') {
            if (top.TYPO3 && top.TYPO3.Backend) {
                top.TYPO3.Backend.aiSuiteWholePageTranslationWizard = {
                    pageId: uid,
                    shouldOpen: true
                };
            }
        }
        top.TYPO3.Backend.ContentContainer.setUrl(data.moduleUrl);
    }
}

export default new ContextMenuActions();
