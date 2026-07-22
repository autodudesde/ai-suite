import General from "@autodudes/ai-suite/helper/general.js";

/**
 * Guards submit controls that trigger an AI generation request.
 *
 * A library section that is visible (its field type was selected) must offer a selectable model.
 * Without one - no library available at all, or all models are paid-only while no paid requests
 * are left - no model would be submitted and the server request would be sent without a
 * generation library, so the controls stay disabled.
 */
class LibrarySelection {
    hasMissingSelectableModel(root) {
        return Array.from(root.querySelectorAll('.library'))
            .filter(function (library) {
                return library.style.display !== 'none';
            })
            .some(function (library) {
                return library.querySelector('input[type="radio"]:not(:disabled)') === null;
            });
    }

    update(root, controls) {
        if (!General.isUsable(root) || controls.length === 0) {
            return;
        }
        const missingSelectableModel = this.hasMissingSelectableModel(root);
        controls.forEach(function (control) {
            if (control.tagName === 'BUTTON' || control.tagName === 'INPUT') {
                control.disabled = missingSelectableModel;
            } else {
                control.classList.toggle('disabled', missingSelectableModel);
                control.setAttribute('aria-disabled', missingSelectableModel ? 'true' : 'false');
                control.style.pointerEvents = missingSelectableModel ? 'none' : '';
            }
            control.style.opacity = missingSelectableModel ? '0.75' : '';
            control.title = missingSelectableModel ? TYPO3.lang['aiSuite.error.noGenerationLibrarySelected'] : '';
        });
    }

    /**
     * Resolves the submit controls inside root, applies the initial state and keeps it in sync
     * with model changes. Call this again whenever root was re-rendered.
     *
     * @param root element containing the library sections and the submit controls
     * @param submitSelectors selectors of the controls that trigger a generation request
     */
    initialize(root, submitSelectors) {
        if (!General.isUsable(root)) {
            return;
        }
        const self = this;
        const controls = submitSelectors.reduce(function (resolved, selector) {
            return resolved.concat(Array.from(root.querySelectorAll(selector)));
        }, []);
        if (controls.length === 0) {
            return;
        }
        root.querySelectorAll('.library input[type="radio"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                self.update(root, controls);
            });
        });
        this.update(root, controls);
    }
}
export default new LibrarySelection();
