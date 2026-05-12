import AjaxRequest from '@typo3/core/ajax/ajax-request.js';

const STORAGE_KEY = 'aisuite_flash_message';

class CliOverviewDashboard {
    constructor() {
        this.rerunUrl = TYPO3.settings.ajaxUrls['aisuite_cli_overview_rerun'];
        this.updateStatusUrl = TYPO3.settings.ajaxUrls['aisuite_cli_overview_update_status'];
        this.initialize();
    }

    initialize() {
        const container = document.getElementById('aisuite-cli-overview');
        if (!container) {
            return;
        }

        this.restoreFlashMessage();

        const rerunAllBtn = document.getElementById('rerunAllBtn');
        const typeFilter = document.getElementById('cliOverviewTypeFilter');
        const scopeButtons = container.querySelectorAll('.rerun-scope-btn');

        if (rerunAllBtn) {
            rerunAllBtn.addEventListener('click', () => {
                this.rerunTasks('all', rerunAllBtn);
            });
        }

        scopeButtons.forEach((btn) => {
            btn.addEventListener('click', () => {
                this.rerunTasks(btn.dataset.type, btn);
            });
        });

        const updateStatusBtn = document.getElementById('updateStatusBtn');
        if (updateStatusBtn) {
            updateStatusBtn.addEventListener('click', () => {
                this.updateTaskStatus(updateStatusBtn);
            });
        }

        if (typeFilter) {
            typeFilter.addEventListener('change', () => {
                this.filterTaskGroups(typeFilter.value);
            });

            this.filterTaskGroups(typeFilter.value);
        }
    }

    restoreFlashMessage() {
        const stored = sessionStorage.getItem(STORAGE_KEY);
        if (!stored) {
            return;
        }
        sessionStorage.removeItem(STORAGE_KEY);
        try {
            const data = JSON.parse(stored);
            const resultContainer = document.getElementById('rerunResultMessage');
            this.showResult(resultContainer, data);
        } catch (e) {
            // ignore malformed data
        }
    }

    storeFlashMessage(data) {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify(data));
    }

    filterTaskGroups(selectedType) {
        const scopeToTypeMap = {
            'page': 'page',
            'page-translation': 'pageTranslate',
            'fileReference': 'fileReferences',
            'fileMetadata': 'fileMetadata',
            'metadata': 'fileMetadataTranslation',
        };

        const typeToScopeMap = {};
        for (const [scope, type] of Object.entries(scopeToTypeMap)) {
            typeToScopeMap[type] = scope;
        }

        const groups = document.querySelectorAll('.cli-overview-task-group');
        groups.forEach((group) => {
            if (selectedType === 'all') {
                group.style.display = '';
            } else {
                const groupScope = group.dataset.scope;
                const expectedScope = typeToScopeMap[selectedType];
                group.style.display = (groupScope === expectedScope) ? '' : 'none';
            }
        });
    }

    rerunTasks(type, triggerElement) {
        const resultContainer = document.getElementById('rerunResultMessage');
        const originalText = triggerElement.innerHTML;

        triggerElement.disabled = true;
        triggerElement.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Processing...';

        if (resultContainer) {
            resultContainer.style.display = 'none';
            resultContainer.className = 'mt-3 alert';
            resultContainer.innerHTML = '';
        }

        new AjaxRequest(this.rerunUrl)
            .post({ type: type })
            .then(async (response) => {
                const data = await response.resolve();
                if (data.success) {
                    this.storeFlashMessage(data);
                    window.location.reload();
                } else {
                    this.showResult(resultContainer, data);
                }
            })
            .catch(() => {
                this.showResult(resultContainer, { success: false, message: 'An unexpected error occurred.' });
            })
            .finally(() => {
                triggerElement.disabled = false;
                triggerElement.innerHTML = originalText;
            });
    }

    updateTaskStatus(triggerElement) {
        const resultContainer = document.getElementById('rerunResultMessage');
        const originalText = triggerElement.innerHTML;

        triggerElement.disabled = true;
        triggerElement.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Updating...';

        if (resultContainer) {
            resultContainer.style.display = 'none';
            resultContainer.className = 'mt-3 alert';
            resultContainer.innerHTML = '';
        }

        new AjaxRequest(this.updateStatusUrl)
            .post({})
            .then(async (response) => {
                const data = await response.resolve();
                if (data.success) {
                    this.storeFlashMessage(data);
                    window.location.reload();
                } else {
                    this.showResult(resultContainer, data);
                }
            })
            .catch(() => {
                this.showResult(resultContainer, { success: false, message: 'An unexpected error occurred.' });
            })
            .finally(() => {
                triggerElement.disabled = false;
                triggerElement.innerHTML = originalText;
            });
    }

    showResult(container, data) {
        if (!container) {
            return;
        }
        container.className = 'mt-3 alert ' + (data.success ? 'alert-success' : 'alert-danger');
        container.innerHTML = data.message || '';
        container.style.display = 'block';
    }
}

export default new CliOverviewDashboard();
