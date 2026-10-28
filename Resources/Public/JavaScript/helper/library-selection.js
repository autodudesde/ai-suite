import General from "@autodudes/ai-suite/helper/general.js";

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
