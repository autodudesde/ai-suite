import Notification from "@typo3/backend/notification.js";
import General from "@autodudes/ai-suite/helper/general.js";

class Generation {

    addFormSubmitEventListener(promptInputName) {
        let self = this;
        let formsWithSpinner = Array.from(document.querySelectorAll('div[data-module-id="aiSuite"] form.with-spinner'));
        let spinnerOverlay = document.querySelector('div[data-module-id="aiSuite"] .spinner-overlay');

        if (Array.isArray(formsWithSpinner) && General.isUsable(spinnerOverlay)) {
            formsWithSpinner.forEach(function (form, index, arr) {
                form.addEventListener('submit', function (event) {
                    event.preventDefault();
                    let enteredPrompt = document.querySelector('div[data-module-id="aiSuite"] textarea[name="'+promptInputName+'"]').value
                    if (enteredPrompt.length < 5) {
                        Notification.warning(TYPO3.lang['aiSuite.module.modal.enteredPromptTitle'], TYPO3.lang['aiSuite.module.modal.enteredPromptMessage'], 8);
                    }
                    if(enteredPrompt.length > 4) {
                        self.showSpinner();
                        form.submit();
                    }
                });
            });
        }
    }

    showSpinner() {
        document.querySelector('div[data-module-id="aiSuite"] .spinner-overlay').classList.add('active');
        setTimeout(() => {
            document.querySelector('div[data-module-id="aiSuite"] .spinner-overlay').classList.add('darken');
        }, 100);
    }

    showSpinnerModal(message, height = 400) {
        return '<style>.modal-body{padding: 0;}.modal-multi-step-wizard .modal-body .carousel-inner {margin: 0 0 0 -5px;}.spinner-wrapper{width:101%;height:' + height +'px;position:relative;overflow:hidden;}.spinner-overlay{position:absolute;top:0;left:0;width:100%;height:100%;display:flex;justify-content:center;align-content:center;flex-wrap:wrap;background-color:#00000000;color:#fff;font-weight:700;transition:background-color .9s ease-in-out}.spinner-overlay.darken{background-color:rgba(0,0,0,.75)}.spinner,.spinner:after,.spinner:before{text-align:center;opacity:0;width:35px;aspect-ratio:1;box-shadow:0 0 0 3px inset #fff;position:relative;animation:1.5s .5s infinite;animation-name:l7-1,l7-2}.spinner:after,.spinner:before{content:"";position:absolute;left:calc(100% + 5px);animation-delay:1s,0s}.spinner:after{left:-40px;animation-delay:0s,1s}@keyframes l7-1{0%,100%,55%{border-top-left-radius:0;border-bottom-right-radius:0}20%,30%{border-top-left-radius:50%;border-bottom-right-radius:50%}}@keyframes l7-2{0%,100%,55%{border-bottom-left-radius:0;border-top-right-radius:0}20%,30%{border-bottom-left-radius:50%;border-top-right-radius:50%}}.spinner-overlay.darken .spinner,.spinner-overlay.darken .spinner:after,.spinner-overlay.darken .spinner:before{opacity:1}.spinner-overlay.darken .message{position:absolute;top:56%;font-size:.9rem}.spinner-overlay.darken .status{position:absolute;top:62%;font-size:.9rem}</style><div class="spinner-wrapper"><div class="spinner-overlay active darken"><div class="spinner"></div><p class="message">'+message+'</p><p class="status"></p></div></div>'
    }

    cancelGeneration() {
        let cancelButton = document.querySelector('div[data-module-id="aiSuite"] .spinner-overlay .cancel .btn');
        if(General.isUsable(cancelButton)) {
            cancelButton.addEventListener('click', function () {
                history.go(0);
            });
        }
    }
}

export default new Generation();
