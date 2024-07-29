define([
    "TYPO3/CMS/AiSuite/Helper/General",
    "TYPO3/CMS/AiSuite/Helper/Ajax",
    "TYPO3/CMS/AiSuite/Helper/Image/ResponseHandling"
], function(General, Ajax, ResponseHandling) {

    let intervalId = null;
    function fetchStatus(data, modal) {
        return new Promise((resolve, reject) => {
            intervalId = setInterval(async () => {
                let statusRes = await Ajax.sendStatusAjaxRequest(data);
                if(General.isUsable(statusRes) === false) {
                    resolve();
                }
                ResponseHandling.handleStatusResponse(statusRes, modal);
                resolve(statusRes);
            }, 2500);
        });
    }

    function fetchStatusContentElement(data, intervalId) {
        return new Promise((resolve, reject) => {
            intervalId = setInterval(async () => {
                let statusRes = await Ajax.sendStatusAjaxRequest(data);
                if (General.isUsable(statusRes) === false) {
                    resolve();
                }
                ResponseHandling.handleStatusContentElementResponse(statusRes);
                resolve(statusRes);
            }, 2500);
        });
    }
    function stopInterval() {
        clearInterval(intervalId);
    }
    return {
        fetchStatus,
        fetchStatusContentElement,
        stopInterval
    };
});
