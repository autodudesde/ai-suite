import DocumentService from "@typo3/core/document-service.js";
import Notification from "@typo3/backend/notification.js";
class TranslationReloadPage {
    constructor(configuration = {}) {
        this.configuration = configuration;
        this.initialize();
    }

    initialize() {
        DocumentService.ready().then(() => {
            this.clickRefreshButton();
        });
    }

    clickRefreshButton() {
        const self = this;
        const refreshButton = document.querySelector('a span[data-identifier="actions-refresh"]')?.closest('a');
        if (refreshButton) {
            setTimeout(() => {
                if(self.configuration.success) {
                    Notification.success(self.configuration.notificationTitle, self.configuration.notificationMessage);
                } else {
                    Notification.error(self.configuration.notificationTitle, self.configuration.notificationMessage);
                }
                refreshButton.click();
            }, 100);
        }
    }
}

export default function(configuration) {
    return new TranslationReloadPage(configuration);
};
