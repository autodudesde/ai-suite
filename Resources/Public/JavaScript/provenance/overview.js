import Modal from '@typo3/backend/modal.js';
import Severity from '@typo3/backend/severity.js';

class ProvenanceOverview {
    constructor() {
        this.form = document.querySelector('form[data-provenance-review-form]');
        if (this.form === null) {
            return;
        }

        this.bindSelectAll();
        this.bindConfirmation();
    }

    bindSelectAll() {
        const toggle = this.form.querySelector('input[data-provenance-toggle-all]');
        if (toggle === null) {
            return;
        }

        toggle.addEventListener('change', () => {
            this.checkboxes().forEach((checkbox) => {
                checkbox.checked = toggle.checked;
            });
        });
    }

    bindConfirmation() {
        this.form.addEventListener('submit', (event) => {
            if (this.form.dataset.provenanceConfirmed === '1') {
                return;
            }

            event.preventDefault();

            const selected = this.checkboxes().filter((checkbox) => checkbox.checked).length;
            if (selected === 0) {
                return;
            }

            const modal = Modal.confirm(
                TYPO3.lang['aiSuite.provenance.overview.markSelected'],
                TYPO3.lang['aiSuite.provenance.overview.confirmReview'],
                Severity.warning,
                [
                    {text: TYPO3.lang['aiSuite.module.modal.abort'], active: true, btnClass: 'btn-default', name: 'cancel'},
                    {text: TYPO3.lang['aiSuite.provenance.overview.markSelected'], btnClass: 'btn-warning', name: 'confirm'},
                ]
            );

            modal.addEventListener('button.clicked', (buttonEvent) => {
                if (buttonEvent.target.name === 'confirm') {
                    this.form.dataset.provenanceConfirmed = '1';
                    this.form.submit();
                }
                modal.hideModal();
            });
        });
    }

    checkboxes() {
        return Array.from(this.form.querySelectorAll('input[name="records[]"]'));
    }
}

export default new ProvenanceOverview();
