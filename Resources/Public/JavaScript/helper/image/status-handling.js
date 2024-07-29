import ResponseHandling from "@autodudes/ai-suite/helper/image/response-handling.js";
import Ajax from "@autodudes/ai-suite/helper/ajax.js";
import General from "@autodudes/ai-suite/helper/general.js";

class StatusHandling {
    fetchStatus(data, modal, self) {
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

    fetchStatusContentElement(data, self) {
        self.intervalId = setInterval(async () => {
            let statusRes = await Ajax.sendStatusAjaxRequest(data);
            if(General.isUsable(statusRes)) {
                ResponseHandling.handleStatusContentElementResponse(statusRes);
            }
        }, 2500);
    }
}

export default new StatusHandling();
