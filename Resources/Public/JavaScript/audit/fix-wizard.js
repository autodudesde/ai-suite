import Notification from "@typo3/backend/notification.js";
import Severity from "@typo3/backend/severity.js";
import MultiStepWizard from "@autodudes/ai-suite/helper/multi-step-wizard-patch.js";
import Ajax from "@autodudes/ai-suite/helper/ajax.js";
import Generation from "@autodudes/ai-suite/helper/generation.js";
import LibrarySelection from "@autodudes/ai-suite/helper/library-selection.js";

class FixWizard {
    constructor() {
        this.addEventListeners();
        this.addMoreInfoListeners();
        this.addAdviceListeners();
        this.addCandidatesListeners();
        this.addAnswersListeners();
        this.addGenerateListeners();
        this.addAuthorboxListeners();
    }

    addEventListeners() {
        const self = this;
        document.querySelectorAll('.audit-fix-btn').forEach(function (button) {
            button.addEventListener('click', function (ev) {
                ev.preventDefault();
                self.openWizard([button.dataset.issueId], button.dataset.pageId, button.dataset.auditType);
            });
        });
        document.querySelectorAll('.audit-fix-all-btn').forEach(function (button) {
            button.addEventListener('click', function (ev) {
                ev.preventDefault();
                let issueIds = [];
                try {
                    issueIds = JSON.parse(button.dataset.issueIds || '[]');
                } catch (e) {
                    issueIds = [];
                }
                self.openWizard(issueIds, button.dataset.pageId, button.dataset.auditType);
            });
        });
    }

    addCandidatesListeners() {
        document.querySelectorAll('.audit-candidates-btn').forEach(function (button) {
            button.addEventListener('click', async function (ev) {
                ev.preventDefault();
                button.disabled = true;
                button.textContent = TYPO3.lang['aiSuite.module.audit.candidates.loading'];
                const res = await Ajax.sendAjaxRequest('aisuite_audit_keyword_candidates', {
                    pageId: button.dataset.pageId,
                });
                if (res === null || !res.output) {
                    button.disabled = false;
                    button.textContent = TYPO3.lang['aiSuite.module.audit.candidates.button'];
                    return;
                }
                // the cached view renders the candidates table server-side
                window.location.href = button.dataset.cachedUrl;
            });
        });
    }

    addGenerateListeners() {
        const self = this;
        document.querySelectorAll('.audit-generate-btn').forEach(function (button) {
            button.addEventListener('click', function (ev) {
                ev.preventDefault();
                self.openTargetWizard(button.dataset.pageId, button.dataset.auditType, button.dataset.prompt, button.dataset.action || 'faq');
            });
        });
    }

    openTargetWizard(pageId, auditType, prompt, action) {
        this.addTargetSlide(pageId, auditType, function () { return prompt; }, action);
        MultiStepWizard.show();
    }

    addTargetSlide(pageId, auditType, promptProvider, action) {
        const self = this;
        MultiStepWizard.addSlide('ai-suite-audit-target', TYPO3.lang['aiSuite.module.audit.target.title'], '', Severity.notice, TYPO3.lang['aiSuite.module.audit.target.title'], async function (slide) {
            self.prepareModal();
            slide.html(Generation.showSpinnerModal(TYPO3.lang['aiSuite.module.audit.fix.wizard.loading'], 300));
            const res = await Ajax.sendAjaxRequest('aisuite_audit_content_targets', { pageId: pageId, auditType: auditType, action: action || 'faq' });
            if (res === null || !res.output) {
                MultiStepWizard.dismiss();
                return;
            }
            slide.html(res.output.content);
            const modal = MultiStepWizard.setup.$carousel.closest('.modal').get(0);
            const columnSelect = modal.querySelector('#targetColumn');
            const positionSelect = modal.querySelector('#targetPosition');
            const filterPositions = function () {
                positionSelect.querySelectorAll('option').forEach(function (option) {
                    const matches = option.dataset.colpos === '*' || option.dataset.colpos === columnSelect.value;
                    option.hidden = !matches;
                    option.disabled = !matches;
                });
                positionSelect.value = 'top';
            };
            columnSelect.addEventListener('change', filterPositions);
            filterPositions();
            modal.querySelector('#aiSuiteTargetContinueBtn').addEventListener('click', function () {
                const cType = modal.querySelector('#targetCType').value;
                const uidPid = positionSelect.value === 'top' ? pageId : '-' + positionSelect.value;
                const url = new URL(res.output.recordEditUrl, window.location.origin);
                url.searchParams.set('edit[tt_content][' + uidPid + ']', 'new');
                url.searchParams.set('defVals[tt_content][pid]', pageId);
                url.searchParams.set('defVals[tt_content][colPos]', columnSelect.value);
                url.searchParams.set('defVals[tt_content][sys_language_uid]', '0');
                url.searchParams.set('defVals[tt_content][CType]', cType);
                url.searchParams.set('initialPrompt', promptProvider());
                url.searchParams.set('returnUrl', res.output.returnUrl);
                MultiStepWizard.dismiss();
                window.location.href = url.toString();
            });
        });
    }

    addAuthorboxListeners() {
        const self = this;
        document.querySelectorAll('.audit-authorbox-btn').forEach(function (button) {
            button.addEventListener('click', function (ev) {
                ev.preventDefault();
                self.openAuthorboxWizard(button.dataset.pageId);
            });
        });
    }

    openAuthorboxWizard(pageId) {
        const self = this;
        const facts = {};
        MultiStepWizard.addSlide('ai-suite-audit-authorbox', TYPO3.lang['aiSuite.module.audit.authorbox.title'], '', Severity.notice, TYPO3.lang['aiSuite.module.audit.authorbox.factsHeader'], async function (slide) {
            self.prepareModal();
            slide.html(Generation.showSpinnerModal(TYPO3.lang['aiSuite.module.audit.fix.wizard.loading'], 300));
            const res = await Ajax.sendAjaxRequest('aisuite_audit_authorbox_form', { pageId: pageId });
            if (res === null || !res.output) {
                MultiStepWizard.dismiss();
                return;
            }
            slide.html(res.output.content);
            const modal = MultiStepWizard.setup.$carousel.closest('.modal').get(0);
            modal.querySelector('#aiSuiteAuthorboxContinueBtn').addEventListener('click', async function () {
                facts.name = modal.querySelector('#authorboxName').value.trim();
                facts.role = modal.querySelector('#authorboxRole').value.trim();
                facts.link = modal.querySelector('#authorboxLink').value.trim();
                if (facts.name === '') {
                    Notification.warning(
                        TYPO3.lang['aiSuite.module.audit.authorbox.nameRequired'],
                        TYPO3.lang['aiSuite.module.audit.authorbox.nameRequiredInfo'],
                        5
                    );
                    return;
                }
                if (modal.querySelector('#authorboxSaveAuthor').checked) {
                    // expliziter Opt-in: Name zusaetzlich als Seiten-Autor speichern
                    await Ajax.sendAjaxRequest('aisuite_audit_authorbox_save_author', { pageId: pageId, name: facts.name });
                }
                MultiStepWizard.unlockNextStep().trigger('click');
            });
        });
        this.addTargetSlide(pageId, 'seo', function () {
            let prompt = TYPO3.lang['aiSuite.module.audit.authorbox.promptIntro'];
            prompt += '\n' + TYPO3.lang['aiSuite.module.audit.authorbox.name'] + ': ' + facts.name;
            if (facts.role !== '') {
                prompt += '\n' + TYPO3.lang['aiSuite.module.audit.authorbox.role'] + ': ' + facts.role;
            }
            if (facts.link !== '') {
                prompt += '\n' + TYPO3.lang['aiSuite.module.audit.authorbox.link'] + ': ' + facts.link;
            }
            return prompt;
        }, 'authorbox');
        MultiStepWizard.show();
    }

    addAnswersListeners() {
        document.querySelectorAll('.audit-answers-btn').forEach(function (button) {
            button.addEventListener('click', async function (ev) {
                ev.preventDefault();
                button.disabled = true;
                button.textContent = TYPO3.lang['aiSuite.module.audit.answers.loading'];
                const res = await Ajax.sendAjaxRequest('aisuite_audit_questions_answers', {
                    url: button.dataset.url,
                    questions: button.dataset.questions,
                });
                if (res === null || !res.output || !Array.isArray(res.output.answers)) {
                    button.disabled = false;
                    button.textContent = TYPO3.lang['aiSuite.module.audit.answers.button'];
                    return;
                }
                const container = document.querySelector('#auditAnswers');
                if (container === null) {
                    return;
                }
                container.replaceChildren();
                const plainParts = [];
                res.output.answers.forEach(function (row) {
                    plainParts.push(row.question + '\n' + row.answer);
                    const block = document.createElement('div');
                    block.className = 'mb-2';
                    const question = document.createElement('strong');
                    question.textContent = row.question;
                    const answer = document.createElement('p');
                    answer.className = 'mb-0';
                    answer.textContent = row.answer;
                    block.append(question, answer);
                    container.append(block);
                });
                const copyBtn = document.createElement('button');
                copyBtn.type = 'button';
                copyBtn.className = 'btn btn-default btn-sm';
                copyBtn.textContent = TYPO3.lang['aiSuite.module.audit.answers.copy'];
                copyBtn.addEventListener('click', async function () {
                    try {
                        await navigator.clipboard.writeText(plainParts.join('\n\n'));
                    } catch (e) {
                        return;
                    }
                    copyBtn.textContent = TYPO3.lang['aiSuite.module.audit.answers.copied'];
                    window.setTimeout(function () {
                        copyBtn.textContent = TYPO3.lang['aiSuite.module.audit.answers.copy'];
                    }, 2000);
                });
                container.append(copyBtn);
                button.remove();
            });
        });
    }

    addMoreInfoListeners() {
        document.querySelectorAll('.audit-more-info-btn').forEach(function (button) {
            button.addEventListener('click', function (ev) {
                ev.preventDefault();
                const row = document.querySelector('tr.audit-more-info[data-issue-id="' + CSS.escape(button.dataset.issueId) + '"]');
                if (row !== null) {
                    row.style.display = row.style.display === 'none' ? '' : 'none';
                }
            });
        });
    }

    addAdviceListeners() {
        document.querySelectorAll('.audit-advice-btn').forEach(function (button) {
            button.addEventListener('click', async function (ev) {
                ev.preventDefault();
                button.disabled = true;
                button.textContent = TYPO3.lang['aiSuite.module.audit.fix.advice.loading'];
                const res = await Ajax.sendAjaxRequest('aisuite_audit_advice', {
                    pageId: button.dataset.pageId,
                    auditType: button.dataset.auditType,
                    issueId: button.dataset.issueId,
                    issueMessage: button.dataset.issueMessage,
                    evidence: button.dataset.evidence || '',
                });
                if (res === null || !res.output || !Array.isArray(res.output.advice)) {
                    button.disabled = false;
                    button.textContent = TYPO3.lang['aiSuite.module.audit.fix.advice.button'];
                    return;
                }
                const container = button.closest('.audit-advice');
                const title = document.createElement('p');
                title.className = 'mb-1 fw-bold';
                title.textContent = TYPO3.lang['aiSuite.module.audit.fix.advice.title'];
                const list = document.createElement('ul');
                list.className = 'mb-0';
                res.output.advice.forEach(function (tip) {
                    const item = document.createElement('li');
                    item.textContent = tip;
                    list.append(item);
                });
                button.remove();
                container.append(title, list);
            });
        });
    }

    openWizard(issueIds, pageId, auditType) {
        const self = this;
        MultiStepWizard.setup.settings['fixState'] = {
            pageId: parseInt(pageId, 10),
            auditType: auditType,
            issueIds: issueIds,
            queue: [],
            index: 0,
            fixedIssueIds: [],
            savedCount: 0,
            skippedCount: 0,
            errors: [],
        };
        MultiStepWizard.addSlide('ai-suite-audit-fix-step-1', TYPO3.lang['aiSuite.module.audit.fix.wizard.title'], '', Severity.notice, TYPO3.lang['aiSuite.module.audit.fix.wizard.slideOne'], async function (slide, settings) {
            self.prepareModal();
            const state = settings['fixState'];
            slide.html(Generation.showSpinnerModal(TYPO3.lang['aiSuite.module.audit.fix.wizard.loading'], 400));
            const res = await Ajax.sendAjaxRequest('aisuite_audit_fix_start', {
                pageId: state.pageId,
                auditType: state.auditType,
                issueIds: JSON.stringify(state.issueIds),
            });
            if (res === null || !res.output) {
                MultiStepWizard.dismiss();
                return;
            }
            state.queue = res.output.queue || [];
            state.uuid = res.output.uuid || '';
            state.langIsoCode = res.output.langIsoCode || 'en';
            slide.html(res.output.content);
            const modal = MultiStepWizard.setup.$carousel.closest('.modal');
            LibrarySelection.initialize(modal.get(0), ['.panel-body button#aiSuiteAuditFixStartBtn']);
            modal.find('.panel-body button#aiSuiteAuditFixStartBtn').on('click', function () {
                state.textAiModel = modal.find('.panel-body input[name="libraries[textGenerationLibrary]"]:checked').val() ?? '';
                state.visionAiModel = modal.find('.panel-body input[name="libraries[visionGenerationLibrary]"]:checked').val() ?? '';
                MultiStepWizard.unlockNextStep().trigger('click');
            });
        });
        MultiStepWizard.addSlide('ai-suite-audit-fix-step-2', TYPO3.lang['aiSuite.module.audit.fix.wizard.title'], '', Severity.notice, TYPO3.lang['aiSuite.module.audit.fix.wizard.slideTwo'], async function (slide, settings) {
            self.prepareModal();
            await self.processNext(slide, settings['fixState']);
        });
        MultiStepWizard.show();
    }

    prepareModal() {
        const modalContent = MultiStepWizard.setup.$carousel.closest('.t3js-modal');
        if (modalContent !== null) {
            modalContent.addClass('aisuite-modal');
            modalContent.removeClass('modal-size-default');
            modalContent.addClass('modal-size-large');
        }
        MultiStepWizard.blurCancelStep();
        MultiStepWizard.lockNextStep();
        MultiStepWizard.lockPrevStep();
    }

    async processNext(slide, state) {
        const self = this;
        if (state.index >= state.queue.length) {
            this.renderSummary(slide, state);
            return;
        }
        const item = state.queue[state.index];
        const position = state.index + 1;
        slide.html(Generation.showSpinnerModal(
            TYPO3.lang['aiSuite.module.audit.fix.wizard.generating'] + ' (' + position + '/' + state.queue.length + ')', 400
        ));
        const res = await Ajax.sendAjaxRequest('aisuite_audit_fix_suggestions', {
            pageId: state.pageId,
            kind: item.kind,
            table: item.table,
            uid: item.uid,
            sysFileId: item.sysFileId,
            label: item.label,
            position: position,
            total: state.queue.length,
            fields: JSON.stringify(item.fields),
            textAiModel: state.textAiModel,
            visionAiModel: state.visionAiModel,
            uuid: state.uuid,
            langIsoCode: state.langIsoCode,
        });
        if (res === null || !res.output) {
            state.errors.push(item.label);
            state.index++;
            await this.processNext(slide, state);
            return;
        }
        slide.html(res.output.content);
        const modal = MultiStepWizard.setup.$carousel.closest('.modal');
        const container = modal.get(0);
        container.querySelectorAll('.audit-fix-suggestion').forEach(function (radio) {
            radio.addEventListener('change', function () {
                const input = container.querySelector('.audit-fix-value[data-field-name="' + radio.dataset.fieldName + '"]');
                if (input !== null) {
                    input.value = radio.value;
                }
            });
        });
        container.querySelector('#aiSuiteAuditFixSaveBtn')?.addEventListener('click', async function () {
            const values = {};
            let hasValue = false;
            container.querySelectorAll('.audit-fix-value').forEach(function (input) {
                if (input.value.trim() !== '') {
                    values[input.dataset.fieldName] = input.value.trim();
                    hasValue = true;
                }
            });
            if (!hasValue) {
                Notification.warning(
                    TYPO3.lang['aiSuite.notification.generation.workflow.missingSelection'],
                    TYPO3.lang['aiSuite.notification.generation.suggestions.missingSelectionInfo'],
                    5
                );
                return;
            }
            const applyRes = await Ajax.sendAjaxRequest('aisuite_audit_fix_apply', {
                table: item.table,
                uid: item.uid,
                pageId: state.pageId,
                auditType: state.auditType,
                values: JSON.stringify(values),
                markFixedIssueIds: JSON.stringify(self.lastOfIssueIds(state, state.index)),
            });
            if (applyRes === null) {
                state.errors.push(item.label);
            } else {
                state.savedCount++;
                (applyRes.output?.fixedIssues || []).forEach(function (issueId) {
                    if (!state.fixedIssueIds.includes(issueId)) {
                        state.fixedIssueIds.push(issueId);
                    }
                });
            }
            state.index++;
            await self.processNext(slide, state);
        });
        container.querySelector('#aiSuiteAuditFixSkipBtn')?.addEventListener('click', async function () {
            state.skippedCount++;
            state.index++;
            await self.processNext(slide, state);
        });
    }

    // an issue counts as fixed once its last queue item was saved
    lastOfIssueIds(state, index) {
        const issueIds = state.queue[index].issueIds || [];
        return issueIds.filter(function (issueId) {
            return !state.queue.slice(index + 1).some(function (item) {
                return (item.issueIds || []).includes(issueId);
            });
        });
    }

    renderSummary(slide, state) {
        let html = '<div class="panel panel-default wizard-slide"><div class="panel-body">';
        html += '<h4>' + TYPO3.lang['aiSuite.module.audit.fix.wizard.summary.title'] + '</h4>';
        html += '<p>' + TYPO3.lang['aiSuite.module.audit.fix.wizard.summary.saved'] + ': <strong>' + state.savedCount + '</strong><br>';
        html += TYPO3.lang['aiSuite.module.audit.fix.wizard.summary.skipped'] + ': ' + state.skippedCount + '</p>';
        if (state.errors.length > 0) {
            const errorItems = state.errors.map(function (label) {
                const item = document.createElement('li');
                item.textContent = label;
                return item.outerHTML;
            });
            html += '<div class="alert alert-warning">' + TYPO3.lang['aiSuite.module.audit.fix.wizard.summary.errors']
                + '<ul class="mb-0">' + errorItems.join('') + '</ul></div>';
        }
        html += '<p>' + TYPO3.lang['aiSuite.module.audit.fix.rerunHint'] + '</p>';
        html += '<button type="button" class="btn btn-primary" id="aiSuiteAuditFixCloseBtn">'
            + TYPO3.lang['aiSuite.module.audit.fix.wizard.close'] + '</button>';
        html += '</div></div>';
        slide.html(html);
        this.markFixedInDom(state.fixedIssueIds);
        const modal = MultiStepWizard.setup.$carousel.closest('.modal');
        modal.get(0).querySelector('#aiSuiteAuditFixCloseBtn')?.addEventListener('click', function () {
            MultiStepWizard.dismiss();
        });
    }

    markFixedInDom(issueIds) {
        if (issueIds.length === 0) {
            return;
        }
        issueIds.forEach(function (issueId) {
            document.querySelectorAll('tr[data-issue-id]').forEach(function (row) {
                if (row.dataset.issueId !== issueId) {
                    return;
                }
                row.querySelector('.audit-fix-btn')?.remove();
                if (row.querySelector('.audit-fixed-badge') === null) {
                    const badge = document.createElement('span');
                    badge.className = 'badge badge-success audit-fixed-badge';
                    badge.textContent = TYPO3.lang['aiSuite.module.audit.fix.fixed'];
                    row.lastElementChild.append(badge);
                }
            });
        });
        const hint = document.querySelector('#auditFixRerunHint');
        if (hint !== null) {
            hint.style.display = '';
        }
    }
}

export default new FixWizard();
