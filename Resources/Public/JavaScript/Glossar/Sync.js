define([
    "TYPO3/CMS/AiSuite/Helper/General",
    "TYPO3/CMS/Backend/Notification",
    "TYPO3/CMS/AiSuite/Helper/Ajax"
], function(General, Notification, Ajax) {

    init();

    function init() {
        if(General.isUsable(document.querySelector('.t3js-ai-suite-sync-glossary-btn'))) {
            document.querySelector('.t3js-ai-suite-sync-glossary-btn').addEventListener("click", async function (ev) {
                ev.preventDefault();
                Notification.info(TYPO3.lang['AiSuite.notification.deeplSyncStarted'], '', 8);
                let response = await Ajax.sendAjaxRequest('aisuite_glossary_synchronize', {pid: ev.target.getAttribute('data-pid')});
                if(General.isUsable(response)) {
                    if(response.success === true) {
                        Notification.success(TYPO3.lang['AiSuite.notification.deeplSyncSuccessful']);
                    } else {
                        Notification.error(TYPO3.lang['AiSuite.notification.deeplSyncFailed']);
                    }
                }
            });
        }
    }
});
