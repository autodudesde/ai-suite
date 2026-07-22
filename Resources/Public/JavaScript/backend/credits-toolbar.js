import AjaxRequest from '@typo3/core/ajax/ajax-request.js';

const CREDITS_CHANGED_EVENT = 'ai-suite:credits-changed';

const DEBOUNCE_MS = 750;

class CreditsToolbar {
    constructor() {
        this.stateUrl = TYPO3.settings.ajaxUrls['aisuite_credits_state'];
        this.timer = null;
        this.needsResync = false;

        top.document.addEventListener(CREDITS_CHANGED_EVENT, (event) => {
            if (event.detail?.persisted !== true) {
                this.needsResync = true;
            }
            this.scheduleRefresh();
        });
    }

    scheduleRefresh() {
        if (this.timer !== null) {
            clearTimeout(this.timer);
        }
        this.timer = setTimeout(() => {
            this.timer = null;
            this.refresh();
        }, DEBOUNCE_MS);
    }

    async refresh() {
        const resync = this.needsResync;
        this.needsResync = false;

        if (resync && this.stateUrl) {
            try {
                await new AjaxRequest(this.stateUrl).post({});
            } catch {
                return;
            }
        }

        top.TYPO3?.Backend?.Topbar?.refresh();
    }
}

export default new CreditsToolbar();
