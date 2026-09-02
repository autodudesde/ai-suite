import General from "@autodudes/ai-suite/helper/general.js";
import Ajax from "@autodudes/ai-suite/helper/ajax.js";

/**
 * Seitenbaum-Audit: Kostenvoranschlag VOR dem ersten Credit, dann sequenziell
 * Seite für Seite. Läuft das Guthaben mittendrin leer, stoppt der Batch vor
 * der nächsten Seite — alles Fertige bleibt gecacht, „Fortsetzen" macht nur
 * mit den restlichen Seiten weiter.
 */
class BatchAudit {
    constructor() {
        this.pages = [];
        this.index = 0;
        this.running = false;
        this.distribution = { high: 0, medium: 0, low: 0 };
        const planBtn = document.querySelector('div[data-module-id="aiSuite"] #auditBatchPlanBtn');
        if (!General.isUsable(planBtn)) {
            return;
        }
        planBtn.addEventListener('click', () => this.plan());
        document.querySelector('div[data-module-id="aiSuite"] #auditBatchStartBtn')
            ?.addEventListener('click', () => this.start());
        document.querySelector('div[data-module-id="aiSuite"] #auditBatchStopBtn')
            ?.addEventListener('click', () => { this.running = false; });
    }

    el(id) {
        return document.querySelector('div[data-module-id="aiSuite"] #' + id);
    }

    ll(key, args = {}) {
        let value = TYPO3.lang['aiSuite.module.audit.batch.' + key] || key;
        for (const [token, replacement] of Object.entries(args)) {
            value = value.replaceAll('{' + token + '}', String(replacement));
        }
        return value;
    }

    settings() {
        return {
            pageId: parseInt(this.el('aiSuiteAuditPageId')?.value || '0', 10),
            auditType: this.el('auditBatchType')?.value || 'seo',
            depth: parseInt(this.el('auditBatchDepth')?.value || '0', 10),
            languageUid: parseInt(this.el('aiSuiteAuditLanguage')?.value || '0', 10) || 0,
        };
    }

    async plan() {
        const settings = this.settings();
        const summary = this.el('auditBatchSummary');
        if (settings.pageId <= 0) {
            summary.style.display = '';
            summary.textContent = this.ll('noPage');
            return;
        }
        summary.style.display = '';
        summary.textContent = '…';
        const response = await Ajax.sendAjaxRequest('aisuite_audit_batch_plan', settings);
        const output = response?.output;
        if (!output || !Array.isArray(output.pages)) {
            summary.textContent = response?.error || this.ll('planFailed');
            return;
        }
        this.pages = output.pages;
        this.index = 0;
        this.el('auditBatchResults').replaceChildren();
        this.el('auditBatchProgress').style.display = 'none';

        const affordable = output.affordableCount;
        let text = this.ll('estimate', {
            pages: output.pages.length,
            price: output.pricePerPage,
            total: output.totalPrice,
        });
        if (output.available !== null) {
            text += ' ' + this.ll('available', { available: output.available });
        }
        const startBtn = this.el('auditBatchStartBtn');
        if (affordable !== null && affordable < output.pages.length) {
            text += ' ' + this.ll('notEnough', { affordable: affordable });
            startBtn.textContent = this.ll('startPartial', { affordable: Math.min(affordable, output.pages.length) });
        } else {
            startBtn.textContent = this.ll('start', { pages: output.pages.length });
        }
        summary.textContent = text;
        this.el('auditBatchActions').style.display = output.pages.length > 0 && (affordable === null || affordable > 0) ? '' : 'none';
    }

    async start() {
        if (this.running || this.pages.length === 0) {
            return;
        }
        const settings = this.settings();
        this.running = true;
        this.el('auditBatchStartBtn').style.display = 'none';
        this.el('auditBatchStopBtn').style.display = '';
        this.el('auditBatchProgress').style.display = '';

        while (this.running && this.index < this.pages.length) {
            const page = this.pages[this.index];
            this.updateStatus(this.ll('running', { current: this.index + 1, total: this.pages.length, title: page.title }));
            const response = await Ajax.sendAjaxRequest('aisuite_audit_batch_run_one', {
                pageId: page.uid,
                auditType: settings.auditType,
                languageUid: settings.languageUid,
            });
            const output = response?.output;
            if (output?.ok) {
                this.index++;
                this.distribution[output.range] = (this.distribution[output.range] || 0) + 1;
                this.appendResult(page, output);
                this.updateBar();
                continue;
            }
            this.running = false;
            if (output?.creditsExhausted) {
                // sauberer Teilabbruch: Fertiges bleibt, Fortsetzen zieht den Rest nach
                this.updateStatus(this.ll('exhausted', { done: this.index, total: this.pages.length }));
                this.el('auditBatchStartBtn').textContent = this.ll('resume', { remaining: this.pages.length - this.index });
            } else {
                this.updateStatus((output?.message || this.ll('planFailed')) + ' — ' + this.ll('stopped', { done: this.index, total: this.pages.length }));
                this.el('auditBatchStartBtn').textContent = this.ll('resume', { remaining: this.pages.length - this.index });
            }
        }

        this.el('auditBatchStopBtn').style.display = 'none';
        this.el('auditBatchStartBtn').style.display = '';
        if (this.index >= this.pages.length) {
            this.running = false;
            this.updateStatus(this.ll('done', { total: this.pages.length }) + ' ' + this.ll('distribution', {
                high: this.distribution.high, medium: this.distribution.medium, low: this.distribution.low,
            }));
            this.el('auditBatchActions').style.display = 'none';
        }
    }

    updateBar() {
        const bar = this.el('auditBatchProgressBar');
        bar.style.width = Math.round((this.index / this.pages.length) * 100) + '%';
    }

    updateStatus(text) {
        this.el('auditBatchStatus').textContent = text;
    }

    appendResult(page, output) {
        const colors = { high: '#1e8e3e', medium: '#b06000', low: '#c5221f' };
        const item = document.createElement('li');
        const badge = document.createElement('span');
        badge.textContent = String(output.score);
        badge.style.cssText = 'display:inline-flex;align-items:center;justify-content:center;width:2em;height:2em;margin-right:0.5em;border-radius:50%;border:2px solid ' + (colors[output.range] || '#999') + ';color:' + (colors[output.range] || '#999') + ';font-weight:700;';
        item.append(badge, document.createTextNode(page.title + ' [' + page.uid + ']'));
        this.el('auditBatchResults').append(item);
    }
}

export default new BatchAudit();
