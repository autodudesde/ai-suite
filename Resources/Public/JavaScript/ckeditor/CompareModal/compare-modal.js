import Modal from '@typo3/backend/modal.js';

const diffBlock = (fill, extraClass = '') => {
    const block = document.createElement('div');
    block.className = ('diff ' + extraClass).trim();
    const item = document.createElement('div');
    item.className = 'diff-item';
    const result = document.createElement('div');
    result.className = 'diff-item-result';
    fill(result);
    item.append(result);
    block.append(item);
    return block;
};

export function openCompareModal(diffHtml, { onAccept, onDiscard }) {
    const legend = diffBlock((result) => {
        const removed = document.createElement('del');
        removed.textContent = TYPO3.lang['aiSuite.module.general.compare.removed'];
        const added = document.createElement('ins');
        added.textContent = TYPO3.lang['aiSuite.module.general.compare.added'];
        result.append(removed, ' ', added);
    }, 'mb-2');
    const diff = diffBlock((result) => { result.innerHTML = diffHtml; });
    const content = document.createElement('div');
    content.append(legend, diff);

    let accepted = false;
    const modal = Modal.advanced({
        title: TYPO3.lang['aiSuite.module.general.compare.title'],
        content: content,
        size: Modal.sizes.medium,
        buttons: [
            {
                text: TYPO3.lang['aiSuite.module.general.compare.discard'],
                btnClass: 'btn-default',
                name: 'discard',
                trigger: (event, modal) => modal.hideModal(),
            },
            {
                text: TYPO3.lang['aiSuite.module.general.compare.accept'],
                btnClass: 'btn-primary',
                name: 'accept',
                active: true,
                trigger: (event, modal) => {
                    accepted = true;
                    onAccept();
                    modal.hideModal();
                },
            },
        ],
    });
    modal.addEventListener('typo3-modal-hidden', () => {
        if (!accepted) {
            onDiscard();
        }
    }, { once: true });
    return modal;
}
