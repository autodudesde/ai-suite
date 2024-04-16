import ResponseHandling from "@autodudes/ai-suite/helper/image/response-handling.js";
import Ajax from "@autodudes/ai-suite/helper/ajax.js";

class StatusHandling {
    fetchStatus(data, modal, self) {
        return new Promise((resolve, reject) => {
            self.intervalId = setInterval(async () => {
                let statusRes = await Ajax.sendStatusAjaxRequest(data);
                ResponseHandling.handleStatusResponse(statusRes, modal);
                resolve(statusRes);
            }, 2000);
        });
    }

    fetchStatusContentElement(data, self) {
        self.intervalId = setInterval(async () => {
            let statusRes = await Ajax.sendStatusAjaxRequest(data);
            ResponseHandling.handleStatusContentElementResponse(statusRes);
        }, 2000);
    }
}

export default new StatusHandling();
