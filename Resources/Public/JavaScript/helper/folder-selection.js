import Modal from "@typo3/backend/modal.js";
import Icons from "@typo3/backend/icons.js";
import {MessageUtility} from "@typo3/backend/utility/message-utility.js";

class FolderSelection {

    constructor() {
        this.closeTimer = null;
    }

    initialize(container) {
        if (container === null || container.dataset.folderSelectionInitialized === '1') {
            return;
        }
        container.dataset.folderSelectionInitialized = '1';

        const self = this;
        container.addEventListener('click', function (ev) {
            const addButton = ev.target.closest('#addFolderBtn');
            if (addButton !== null) {
                ev.preventDefault();
                self.openBrowser(container, addButton.dataset.browserUrl);
                return;
            }
            const removeButton = ev.target.closest('.ai-suite-folder-remove');
            if (removeButton !== null) {
                ev.preventDefault();
                self.removeChip(container, removeButton.dataset.identifier);
            }
        });
    }

    openBrowser(container, url) {
        if (!url) {
            return;
        }
        const self = this;
        const modal = Modal.advanced({
            type: Modal.types.iframe,
            content: url,
            size: Modal.sizes.large,
            title: TYPO3.lang['aiSuite.module.workflowFilelist.selectDirectories'],
        });

        const handlePickedFolder = function (payload) {
            if (!payload
                || payload.actionName !== 'typo3:elementBrowser:elementAdded'
                || payload.fieldName !== 'aiSuiteFolderSelection'
            ) {
                return;
            }
            self.addChip(container, payload.value);
            window.clearTimeout(self.closeTimer);
            self.closeTimer = window.setTimeout(function () {
                modal.hideModal();
            }, 150);
        };
        const handleMessage = function (ev) {
            if (MessageUtility.verifyOrigin(ev.origin)) {
                handlePickedFolder(ev.data);
            }
        };

        window.addEventListener('message', handleMessage);
        modal.addEventListener('typo3:element-browser:message', function (ev) {
            handlePickedFolder(ev.detail);
        });
        modal.addEventListener('typo3-modal-hidden', function () {
            window.removeEventListener('message', handleMessage);
        });
    }

    addChip(container, identifier) {
        if (typeof identifier !== 'string' || identifier === '') {
            return;
        }
        const chips = container.querySelector('#folderChips');
        if (chips === null || chips.querySelector('[data-identifier="' + CSS.escape(identifier) + '"]') !== null) {
            return;
        }

        const namespace = container.dataset.namespace || 'options';
        const chip = document.createElement('span');
        chip.className = 'badge badge-info ai-suite-folder-chip d-inline-flex align-items-center gap-2';
        chip.dataset.identifier = identifier;

        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = namespace + '[directories][]';
        hidden.value = identifier;

        const label = document.createElement('span');
        label.className = 'ai-suite-folder-label';
        label.textContent = identifier;

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'ai-suite-folder-remove btn btn-link btn-sm p-0';
        remove.dataset.identifier = identifier;
        remove.title = TYPO3.lang['aiSuite.module.workflowFilelist.removeDirectory'];

        chip.append(hidden, label, remove);
        chips.append(chip);

        Icons.getIcon('actions-close', Icons.sizes.small).then(function (markup) {
            remove.innerHTML = markup;
        });

        this.markTouched(container);
    }

    removeChip(container, identifier) {
        const chip = container.querySelector('.ai-suite-folder-chip[data-identifier="' + CSS.escape(identifier) + '"]');
        if (chip === null) {
            return;
        }
        chip.remove();
        this.markTouched(container);
    }

    markTouched(container) {
        const marker = container.querySelector('#directoriesTouched');
        if (marker !== null) {
            marker.value = '1';
        }
        const emptyHint = container.querySelector('.ai-suite-folder-empty');
        if (emptyHint !== null) {
            const hasChips = container.querySelectorAll('.ai-suite-folder-chip').length > 0;
            emptyHint.classList.toggle('d-none', hasChips);
            emptyHint.classList.toggle('d-block', !hasChips);
        }
    }
}

export default new FolderSelection();
