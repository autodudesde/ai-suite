import Modal from '@typo3/backend/modal.js';
import Severity from '@typo3/backend/severity.js';

class SortableApp {
    constructor() {
        this.wrap = document.querySelector('.sortable-wrap');
        if (this.wrap === null) {
            return;
        }
        this.wrap.querySelectorAll('.nested-sortable').forEach((list) => this.makeSortable(list));
        this.wrap.querySelectorAll('button.add').forEach((button) => this.bindAdd(button));
        this.wrap.querySelectorAll('button.edit').forEach((button) => this.bindEdit(button));
        this.wrap.querySelectorAll('button.delete').forEach((button) => this.bindDelete(button));

        const toggleButtonVisible = document.querySelector('.toggleButtonVisible');
        if (toggleButtonVisible !== null) {
            toggleButtonVisible.addEventListener('click', (event) => {
                event.preventDefault();
                this.wrap.querySelectorAll('button').forEach((button) => button.classList.toggle('d-none'));
            });
        }
    }

    makeSortable(list) {
        new Sortable(list, {
            group: 'nested',
            animation: 150,
            fallbackOnBody: true,
            swapThreshold: 0.65,
        });
    }

    bindAdd(button) {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            this.askForTitle(TYPO3.lang['aiSuite.module.pageStructure.sortable-input.addTitle'], '', (title) => {
                button.previousElementSibling.appendChild(this.createItem(title, button.innerHTML));
            });
        });
    }

    bindEdit(button) {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            const titleSpan = button.parentElement.querySelector(':scope > .title');
            this.askForTitle(TYPO3.lang['aiSuite.module.pageStructure.sortable-input.editTitle'], titleSpan.dataset.title ?? '', (title) => {
                titleSpan.textContent = title;
                titleSpan.dataset.title = title;
            });
        });
    }

    bindDelete(button) {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            button.parentElement.remove();
        });
    }

    askForTitle(modalTitle, currentTitle, onSubmit) {
        const content = document.createElement('div');
        const label = document.createElement('label');
        label.className = 'form-label';
        label.htmlFor = 'pageStructure-sortable-input';
        label.textContent = TYPO3.lang['aiSuite.module.pageStructure.sortable-input.title'];
        const input = document.createElement('input');
        input.type = 'text';
        input.id = 'pageStructure-sortable-input';
        input.className = 'form-control';
        input.value = currentTitle;
        content.append(label, input);

        const submit = (modal) => {
            const title = input.value.trim();
            if (title === '') {
                input.classList.add('is-invalid');
                input.focus();
                return;
            }
            onSubmit(title);
            modal.hideModal();
        };

        const modal = Modal.advanced({
            title: modalTitle,
            content: content,
            severity: Severity.notice,
            size: Modal.sizes.small,
            buttons: [
                {
                    text: TYPO3.lang['aiSuite.module.modal.abort'],
                    btnClass: 'btn-default',
                    name: 'cancel',
                    trigger: (event, currentModal) => currentModal.hideModal(),
                },
                {
                    text: TYPO3.lang['aiSuite.module.pageStructure.sortable-input.submit'],
                    btnClass: 'btn-primary',
                    name: 'submit',
                    trigger: (event, currentModal) => submit(currentModal),
                },
            ],
        });
        input.addEventListener('input', () => input.classList.remove('is-invalid'));
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                submit(modal);
            }
        });
        modal.addEventListener('typo3-modal-shown', () => input.focus());
    }

    createItem(title, addButtonMarkup) {
        const item = document.createElement('div');
        item.className = 'list-group-item nested';

        const handle = document.createElement('span');
        handle.className = 'handle';
        handle.innerHTML = this.wrap.querySelector('div.handle').innerHTML;

        const titleSpan = document.createElement('span');
        titleSpan.className = 'title';
        titleSpan.textContent = title;
        titleSpan.dataset.title = title;

        const editButton = this.createButton('edit btn btn-default', this.wrap.querySelector('button.edit').innerHTML);
        this.bindEdit(editButton);
        const deleteButton = this.createButton('delete btn btn-default', this.wrap.querySelector('button.delete').innerHTML);
        this.bindDelete(deleteButton);

        const children = document.createElement('div');
        children.className = 'list-group nested-sortable';
        this.makeSortable(children);

        const addButton = this.createButton('add', addButtonMarkup);
        this.bindAdd(addButton);

        item.append(handle, titleSpan, editButton, deleteButton, children, addButton);
        return item;
    }

    createButton(className, markup) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = className;
        button.innerHTML = markup;
        return button;
    }
}

export default new SortableApp();
