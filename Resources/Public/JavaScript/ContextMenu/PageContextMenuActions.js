/**
 * Module: TYPO3/CMS/AiSuite/ContextMenu/PageContextMenuActions
 *
 * @exports TYPO3/CMS/AiSuite/ContextMenu/PageContextMenuActions
 */
define(function () {
    'use strict';

    /**
     * @exports TYPO3/CMS/AiSuite/ContextMenu/PageContextMenuActions
     */
    var ContextMenuActions = {};

    /**
     * @param {string} table
     * @param {int} uid of the page
     */
    ContextMenuActions.contextMenuLink = function (table, uid) {
        if (table === 'pages' || table === 'sys_file') {
            top.TYPO3.Backend.ContentContainer.setUrl($(this).data('moduleUrl'));
        }
    };

    return ContextMenuActions;
});
