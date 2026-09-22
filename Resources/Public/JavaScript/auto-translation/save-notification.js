import DocumentService from "@typo3/core/document-service.js";
import Notification from "@typo3/backend/notification.js";

const SAVE_CONTROLS = [
    'button[name^="_save"]',
    'a[data-name^="_save"]',
    'button[name="CMD"][value^="save"]',
    'a[data-name="CMD"][data-value^="save"]',
].join(',');

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
                this.notify();
            });
            // TYPO3 12 saves via jQuery trigger('submit'), which fires no native event
            document.addEventListener('click', (event) => {
                const target = event.target instanceof Element ? event.target : null;
                if (target !== null && target.closest(SAVE_CONTROLS) !== null) {
                    this.notify();
                }
            }, true);
        });
    }

    notify() {
        if (this.notified) {
            return;
        }
        this.notified = true;
        Notification.info(
            TYPO3.lang['aiSuite.autoTranslation.direct.saveHintTitle'],
            TYPO3.lang['aiSuite.autoTranslation.direct.saveHintMessage'],
            5
        );
    }
}

export default new AutoTranslationSaveNotification();
