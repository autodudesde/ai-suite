import DocumentService from "@typo3/core/document-service.js";
import Notification from "@typo3/backend/notification.js";

/**
 * Shows an info notification right when a content element is saved while automatic
 * "direct" translation on save is active — the translation itself runs synchronously
 * server-side, so this gives the editor immediate feedback that it has started.
 *
 * The module is only loaded by the backend for tt_content source-language edit forms
 * when the feature is enabled and the processing mode is "direct".
 */
class AutoTranslationSaveNotification {
    constructor() {
        this.notified = false;
        this.initialize();
    }

    initialize() {
        DocumentService.ready().then(() => {
            const form = document.querySelector('form[name="editform"]');
            if (form === null) {
                return;
            }
            form.addEventListener('submit', () => {
                if (this.notified) {
                    return;
                }
                this.notified = true;
                Notification.info(
                    TYPO3.lang['aiSuite.autoTranslation.direct.startTitle'],
                    TYPO3.lang['aiSuite.autoTranslation.direct.startMessage'],
                    8
                );
            });
        });
    }
}

export default new AutoTranslationSaveNotification();
