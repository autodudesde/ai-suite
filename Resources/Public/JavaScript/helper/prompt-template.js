class PromptTemplate {

    /**
     * @param {string} nameAttribute
     */
    loadPromptTemplates(nameAttribute) {
        let promptTemplates = document.querySelector('div[data-module-id="aiSuite"] select[name="promptTemplates"]');
        if(promptTemplates !== null) {
            promptTemplates.addEventListener('change', function (event) {
                document.querySelector('div[data-module-id="aiSuite"] textarea[name="' + nameAttribute + '"]').value = event.target.value;
            });
        }
    }

    /**
     * @param {Element} container
     * @param {string} textareaSelector
     */
    bindInContainer(container, textareaSelector) {
        if (!container) {
            return;
        }
        const select = container.querySelector('select[name="promptTemplates"]');
        const textarea = container.querySelector(textareaSelector);
        if (select === null || textarea === null) {
            return;
        }
        const self = this;
        select.addEventListener('change', function (event) {
            textarea.value = event.target.value;
            self.refreshCollapsibleState(container);
        });
    }

    /**
     * Listens on a container that survives the AJAX re-renders of the workflow views, so the
     * select does not have to be re-bound after every view update.
     *
     * @param {Element} container
     * @param {string} textareaSelector
     */
    bindWithDelegation(container, textareaSelector) {
        if (!container) {
            return;
        }
        const self = this;
        container.addEventListener('change', function (ev) {
            if (ev.target && ev.target.nodeName === 'SELECT' && ev.target.name === 'promptTemplates') {
                const textarea = container.querySelector(textareaSelector);
                if (textarea !== null) {
                    textarea.value = ev.target.value;
                    self.refreshCollapsibleState(container);
                }
            }
        });
    }

    /**
     * @param {Element} container
     * @param {string} textareaSelector
     * @returns {string}
     */
    readPrompt(container, textareaSelector) {
        const textarea = container ? container.querySelector(textareaSelector) : null;
        return textarea ? textarea.value.trim() : '';
    }

    /**
     * A collapsed prompt field still replaces the serverside instruction, so a filled one is
     * never left hidden: it opens itself and carries a badge.
     *
     * @param {Element} container
     */
    refreshCollapsibleState(container) {
        if (!container) {
            return;
        }
        const wrapper = container.querySelector('.ai-suite-custom-prompt');
        if (wrapper === null) {
            return;
        }
        const textarea = wrapper.querySelector('textarea');
        const filled = textarea !== null && textarea.value.trim() !== '';
        const badge = wrapper.querySelector('.ai-suite-custom-prompt-active');
        if (badge !== null) {
            badge.classList.toggle('d-none', !filled);
        }
        const collapse = wrapper.querySelector('.collapse');
        const toggle = wrapper.querySelector('.ai-suite-custom-prompt-toggle');
        if (filled && collapse !== null && !collapse.classList.contains('show')) {
            collapse.classList.add('show');
            if (toggle !== null) {
                toggle.classList.remove('collapsed');
                toggle.setAttribute('aria-expanded', 'true');
            }
        }
    }

    /**
     * @param {Element} container
     * @param {string} textareaSelector
     */
    bindCollapsibleState(container, textareaSelector) {
        if (!container) {
            return;
        }
        const self = this;
        container.addEventListener('input', function (ev) {
            if (ev.target && ev.target.matches(textareaSelector)) {
                self.refreshCollapsibleState(container);
            }
        });
    }
}

export default new PromptTemplate();
