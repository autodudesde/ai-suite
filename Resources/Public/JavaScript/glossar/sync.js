import General from "@autodudes/ai-suite/helper/general.js";
import Ajax from "@autodudes/ai-suite/helper/ajax.js";
import Notification from "@typo3/backend/notification.js"

class SyncGlossary {
    constructor() {
        this.init();
    }
    init() {
        if(General.isUsable(document.querySelector('.t3js-ai-suite-sync-glossary-btn'))) {
            document.querySelector('.t3js-ai-suite-sync-glossary-btn').addEventListener("click", async function (ev) {
                ev.preventDefault();
                Notification.info(TYPO3.lang['AiSuite.notification.deeplSyncStarted'], '', 8);
                let response = await Ajax.sendAjaxRequest('aisuite_glossary_synchronize', {pid: ev.target.getAttribute('data-pid')});
                if(General.isUsable(response) && response.success){
                    if(response.success === true){
                        Notification.success(TYPO3.lang['AiSuite.notification.deeplSyncSuccessful']);
                    } else {
                        Notification.error(TYPO3.lang['AiSuite.notification.generation.deeplSyncFailed']);
                    }
                }
            });
        }
    }
}
export default new SyncGlossary();
