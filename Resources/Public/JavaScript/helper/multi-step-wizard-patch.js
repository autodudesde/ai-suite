import Modal from "@typo3/backend/modal.js";
import MultiStepWizard from "@typo3/backend/multi-step-wizard.js";

if (!MultiStepWizard._aiSuiteInitializeEventsPatched) {
    MultiStepWizard._aiSuiteInitializeEventsPatched = true;
    const origInitializeEvents = MultiStepWizard.initializeEvents;
    MultiStepWizard.initializeEvents = function () {
        if (!Modal.currentModal && this.setup && this.setup.$carousel) {
            const carouselEl = this.setup.$carousel.get(0);
            const modalEl = carouselEl ? carouselEl.closest('typo3-backend-modal') : null;
            if (modalEl) {
                Modal.currentModal = modalEl;
                if (Array.isArray(Modal.instances) && !Modal.instances.includes(modalEl)) {
                    Modal.instances.push(modalEl);
                }
            }
        }
        return origInitializeEvents.apply(this, arguments);
    };
}

export default MultiStepWizard;
