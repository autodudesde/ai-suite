define([
    "TYPO3/CMS/AiSuite/Helper/General",
    "TYPO3/CMS/AiSuite/Helper/Ajax",
    "TYPO3/CMS/AiSuite/Helper/Image/ResponseHandling"
], function(General, Ajax, ResponseHandling) {

    // let intervalId = null;
    function StatusHandling() {
        this.intervalId = null;
    }
    StatusHandling.prototype.fetchStatus = function(data, modal) {
        const self = this;
        return new Promise((resolve, reject) => {
            self.intervalId = setInterval(async () => {
                let statusRes = await Ajax.sendStatusAjaxRequest(data);
                if(General.isUsable(statusRes) === false) {
                    resolve();
                }
                ResponseHandling.handleStatusResponse(statusRes, modal);
                resolve(statusRes);
            }, 2500);
        });
    }

    StatusHandling.prototype.fetchStatusContentElement = function(data, intervalId) {
        const self = this;
        return new Promise((resolve, reject) => {
            self.intervalId = setInterval(async () => {
                let statusRes = await Ajax.sendStatusAjaxRequest(data);
                if (General.isUsable(statusRes) === false) {
                    resolve();
                }
                ResponseHandling.handleStatusContentElementResponse(statusRes);
                resolve(statusRes);
            }, 2500);
        });
    }
    StatusHandling.prototype.stopInterval = function() {
        clearInterval(this.intervalId);
    }
    return new StatusHandling();
});
