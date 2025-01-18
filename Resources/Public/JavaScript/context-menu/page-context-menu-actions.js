/**
 * Module: @autodudes/ai-suite/context-menu/page-context-menu-actions-actions
 *
 * JavaScript to handle the click action of the AI Suite context menu item
 */

class ContextMenuActions {
    contextMenuLink (table, uid, data) {
        top.TYPO3.Backend.ContentContainer.setUrl(data.moduleUrl);
    };
}

export default new ContextMenuActions();
