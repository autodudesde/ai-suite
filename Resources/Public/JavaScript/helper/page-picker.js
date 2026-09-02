import Ajax from "@autodudes/ai-suite/helper/ajax.js";
import Modal from "@typo3/backend/modal.js";
import { MessageUtility } from "@typo3/backend/utility/message-utility.js";

/**
 * Gemeinsamer Seiten-Picker (Partial Libs/PagePicker.html): öffnet den
 * Element-Browser, schreibt die gewählte uid ins Hidden-Feld und holt den
 * Seitentitel für die Anzeige. Optionaler Admin-Haken „neue Root-Seite"
 * setzt den Wert auf -1 und sperrt die Auswahl.
 */
class PagePicker {
    initAll(onPicked = null) {
        for (const container of document.querySelectorAll('div[data-module-id="aiSuite"] .ai-suite-page-picker')) {
            this.init(container, onPicked);
        }
    }

    init(container, onPicked = null) {
        const button = container.querySelector('.ai-suite-page-picker-btn');
        const hidden = container.querySelector('.ai-suite-page-picker-value');
        const title = container.querySelector('.ai-suite-page-picker-title');
        const root = container.querySelector('.ai-suite-page-picker-root');
        if (!button || !hidden) {
            return;
        }
        button.addEventListener('click', (ev) => {
            ev.preventDefault();
            this.openBrowser(button.dataset.browserUrl, (pageId) => {
                hidden.value = String(pageId);
                if (title) {
                    // returnJson: JsonResponse ist nach resolve() bereits geparst
                    Ajax.sendAjaxRequest('aisuite_page_info', { pageId: pageId }, true).then((response) => {
                        title.value = response?.output?.title || '[' + pageId + ']';
                    });
                }
                if (onPicked) {
                    onPicked(pageId, container);
                }
            });
        });
        if (root) {
            const applyRootState = (asRoot) => {
                button.disabled = asRoot;
                if (title) {
                    title.disabled = asRoot;
                }
                if (asRoot) {
                    root.dataset.previousValue = hidden.value !== '-1' ? hidden.value : '';
                    hidden.value = '-1';
                } else {
                    hidden.value = root.dataset.previousValue || '';
                }
            };
            if (hidden.value === '-1') {
                root.checked = true;
                applyRootState(true);
            }
            root.addEventListener('change', () => {
                applyRootState(root.checked);
                if (onPicked) {
                    onPicked(parseInt(hidden.value || '0', 10), container);
                }
            });
        }
    }

    openBrowser(url, onPicked) {
        if (!url) {
            return;
        }
        let closeTimer = null;
        const modal = Modal.advanced({
            type: Modal.types.iframe,
            content: url,
            size: Modal.sizes.large,
            title: TYPO3.lang['aiSuite.module.audit.selectPage'],
        });

        const handlePickedPage = function (payload) {
            if (!payload || payload.actionName !== 'typo3:elementBrowser:elementAdded') {
                return;
            }
            if (payload.fieldName && payload.fieldName !== 'aiSuitePagePicker') {
                return;
            }
            // db mode liefert Werte wie "pages_123"
            const match = /(\d+)$/.exec(String(payload.value || ''));
            if (match === null) {
                return;
            }
            onPicked(parseInt(match[1], 10));
            window.clearTimeout(closeTimer);
            closeTimer = window.setTimeout(function () {
                modal.hideModal();
            }, 150);
        };
        const handleMessage = function (ev) {
            if (MessageUtility.verifyOrigin(ev.origin)) {
                handlePickedPage(ev.data);
            }
        };

        window.addEventListener('message', handleMessage);
        modal.addEventListener('typo3:element-browser:message', function (ev) {
            handlePickedPage(ev.detail);
        });
        modal.addEventListener('typo3-modal-hidden', function () {
            window.removeEventListener('message', handleMessage);
        });
    }
}

export default new PagePicker();
