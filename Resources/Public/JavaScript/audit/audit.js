import Generation from "@autodudes/ai-suite/helper/generation.js";
import General from "@autodudes/ai-suite/helper/general.js";
import Ajax from "@autodudes/ai-suite/helper/ajax.js";
import Modal from "@typo3/backend/modal.js";
import { MessageUtility } from "@typo3/backend/utility/message-utility.js";

class Audit {
    constructor() {
        Generation.cancelGeneration();
        this.closeTimer = null;
        this.addFormSubmitEventListener();
        this.addAuditTypeToggle();
        this.addPageSelection();
        this.addLanguageSelection();
    }

    addFormSubmitEventListener() {
        const form = document.querySelector('div[data-module-id="aiSuite"] form.with-spinner');
        const spinnerOverlay = document.querySelector('div[data-module-id="aiSuite"] .spinner-overlay');
        if (General.isUsable(form) && General.isUsable(spinnerOverlay)) {
            form.addEventListener('submit', () => {
                this.applySpinnerMessage(spinnerOverlay);
                Generation.showSpinner();
            });
        }
    }

    applySpinnerMessage(spinnerOverlay) {
        const typeSelect = document.querySelector('div[data-module-id="aiSuite"] select[name="auditType"]');
        const message = spinnerOverlay.querySelector('.message');
        if (!General.isUsable(typeSelect) || !General.isUsable(message)) {
            return;
        }
        const text = TYPO3.lang['aiSuite.module.audit.spinner.' + typeSelect.value]
            || TYPO3.lang['aiSuite.module.audit.spinner'];
        if (text) {
            message.textContent = text;
        }
    }

    addAuditTypeToggle() {
        const typeSelect = document.querySelector('div[data-module-id="aiSuite"] select[name="auditType"]');
        const keywordGroup = document.querySelector('div[data-module-id="aiSuite"] .audit-keyword-group');
        if (General.isUsable(typeSelect) && General.isUsable(keywordGroup)) {
            const creditsHint = document.querySelector('div[data-module-id="aiSuite"] #auditCreditsHint');
            const marketGroup = document.querySelector('div[data-module-id="aiSuite"] .audit-market-group');
            const modelGroup = document.querySelector('div[data-module-id="aiSuite"] .audit-model-group');
            const update = () => {
                keywordGroup.style.display = ['seo', 'questions', 'cluster'].includes(typeSelect.value) ? '' : 'none';
                if (General.isUsable(marketGroup)) {
                    marketGroup.style.display = ['seo', 'questions', 'gap', 'cluster', 'competitors'].includes(typeSelect.value) ? '' : 'none';
                }
                if (General.isUsable(modelGroup)) {
                    modelGroup.style.display = ['questions', 'gap', 'cluster', 'competitors'].includes(typeSelect.value) ? '' : 'none';
                }
                if (General.isUsable(creditsHint)) {
                    creditsHint.textContent = TYPO3.lang['aiSuite.module.audit.creditsHint.' + typeSelect.value] || '';
                }
            };
            typeSelect.addEventListener('change', update);
            update();
        }
    }

    addPageSelection() {
        const button = document.querySelector('div[data-module-id="aiSuite"] #selectPageBtn');
        if (!General.isUsable(button)) {
            return;
        }
        const self = this;
        button.addEventListener('click', function (ev) {
            ev.preventDefault();
            self.openBrowser(button.dataset.browserUrl);
        });
    }

    openBrowser(url) {
        if (!url) {
            return;
        }
        const self = this;
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
            if (payload.fieldName && payload.fieldName !== 'aiSuiteAuditPageSelection') {
                return;
            }
            // db mode delivers values like "pages_123"
            const match = /(\d+)$/.exec(String(payload.value || ''));
            if (match === null) {
                return;
            }
            self.applyPickedPage(parseInt(match[1], 10));
            window.clearTimeout(self.closeTimer);
            self.closeTimer = window.setTimeout(function () {
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

    applyPickedPage(pageId, languageUid = 0) {
        const pageIdInput = document.querySelector('div[data-module-id="aiSuite"] #aiSuiteAuditPageId');
        if (General.isUsable(pageIdInput)) {
            pageIdInput.value = String(pageId);
        }
        Ajax.sendAjaxRequest('aisuite_audit_last_audits', { pageId: pageId, languageUid: languageUid }).then((response) => {
            if (!response || !response.output) {
                return;
            }
            this.renderSelection(response.output);
        });
    }

    addLanguageSelection() {
        const languageSelect = document.querySelector('div[data-module-id="aiSuite"] #aiSuiteAuditLanguage');
        if (!General.isUsable(languageSelect)) {
            return;
        }
        languageSelect.addEventListener('change', () => {
            const pageIdInput = document.querySelector('div[data-module-id="aiSuite"] #aiSuiteAuditPageId');
            const pageId = parseInt(pageIdInput?.value || '0', 10);
            if (pageId > 0) {
                this.applyPickedPage(pageId, parseInt(languageSelect.value, 10) || 0);
            }
        });
    }

    renderLanguages(languages) {
        const group = document.querySelector('div[data-module-id="aiSuite"] #auditLanguageGroup');
        const select = document.querySelector('div[data-module-id="aiSuite"] #aiSuiteAuditLanguage');
        if (!General.isUsable(group) || !General.isUsable(select)) {
            return;
        }
        const current = select.value;
        select.replaceChildren();
        for (const language of languages || []) {
            const option = document.createElement('option');
            option.value = String(language.uid);
            option.textContent = language.title;
            select.append(option);
        }
        if ([...select.options].some((option) => option.value === current)) {
            select.value = current;
        }
        group.style.display = (languages || []).length > 1 ? '' : 'none';
    }

    renderSelection(output) {
        const titleInput = document.querySelector('div[data-module-id="aiSuite"] #aiSuiteAuditPageTitle');
        if (General.isUsable(titleInput)) {
            titleInput.value = output.pageTitle || '';
        }
        const keywordInput = document.querySelector('div[data-module-id="aiSuite"] #aiSuiteAuditKeyword');
        if (General.isUsable(keywordInput) && output.keyword) {
            keywordInput.value = output.keyword;
        }
        const marketSelect = document.querySelector('div[data-module-id="aiSuite"] #aiSuiteAuditMarket');
        if (General.isUsable(marketSelect) && output.suggestedMarket) {
            marketSelect.value = output.suggestedMarket;
        }
        this.renderLanguages(output.languages);
        const hints = document.querySelector('div[data-module-id="aiSuite"] #auditLastAudits');
        if (!General.isUsable(hints)) {
            return;
        }
        hints.replaceChildren();
        const audits = output.audits || {};
        for (const type of ['seo', 'a11y', 'questions', 'gap', 'cluster', 'competitors']) {
            if (!audits[type]) {
                continue;
            }
            const line = document.createElement('div');
            const label = document.createTextNode(
                TYPO3.lang['aiSuite.module.audit.lastAudit.' + type] + ': ' + audits[type].date + ' — '
            );
            const link = document.createElement('a');
            link.href = audits[type].viewUrl;
            link.textContent = TYPO3.lang['aiSuite.module.audit.lastAudit.view'];
            line.append(label, link);
            hints.append(line);
        }
    }
}

export default new Audit();
