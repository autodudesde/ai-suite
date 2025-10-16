import General from "@autodudes/ai-suite/helper/general.js";
import Ajax from "@autodudes/ai-suite/helper/ajax.js";

class GlobalInstructions {

    async fetchGlobalInstructions(data, modal = null) {
        const self = this;
        let res = await Ajax.sendAjaxRequest('aisuite_globalinstruction_preview', data);
        const context = modal ? modal : document;
        let globalInstructionWrapper = context.querySelector('.global-instruction-wrapper');
        if (General.isUsable(res) && General.isUsable(globalInstructionWrapper)) {
            globalInstructionWrapper.innerHTML = res.output;
            const tooltipButtons = context.querySelectorAll('.global-instruction-tooltip');
            tooltipButtons.forEach(button => {
                const globalInstructions = button.dataset.globalInstructions;
                const promptContentFunction = () => self.createPromptContent(button, context);
                self.initializeTooltipForElement(button, globalInstructions, promptContentFunction, context);
            });
        } else {
            console.error('Error');
        }
    }

    async fetchGlobalInstructionsMultiStepWizard(data, modal) {
        const self = this;
        let res = await Ajax.sendAjaxRequest('aisuite_globalinstruction_preview', data);
        let globalInstructionWrapper = modal.find('.global-instruction-wrapper');
        if (General.isUsable(res) && globalInstructionWrapper.length > 0) {
            globalInstructionWrapper.html(res.output);
            const tooltipButtons = modal.find('.global-instruction-tooltip');
            tooltipButtons.each(function(index, button) {
                const globalInstructions = button.dataset.globalInstructions;
                const promptContentFunction = () => self.createPromptContentMultiStepWizard(button, modal);
                self.initializeTooltipForElementMultiStepWizard(button, globalInstructions, promptContentFunction, modal);
            });
        } else {
            console.error('Error');
        }
    }


    initializeTooltipForElement(element, globalInstructions, getPromptContent, context = document) {
        let hideTimeout;

        element.addEventListener('mouseenter', () => {
            clearTimeout(hideTimeout);
            const promptContent = typeof getPromptContent === 'function' ? getPromptContent(element) : getPromptContent;
            this.showGlobalInstructionTooltip(globalInstructions, promptContent, element, () => {
                const tooltip = context.querySelector('.ai-suite-global-instruction-tooltip');
                if (tooltip) {
                    tooltip.addEventListener('mouseenter', () => {
                        clearTimeout(hideTimeout);
                    });
                    tooltip.addEventListener('mouseleave', () => {
                        hideTimeout = setTimeout(() => {
                            this.hideGlobalInstructionTooltip(context);
                        }, 100);
                    });
                }
            }, context);
        });

        element.addEventListener('mouseleave', () => {
            hideTimeout = setTimeout(() => {
                this.hideGlobalInstructionTooltip(context);
            }, 100);
        });
    }

    initializeTooltipForElementMultiStepWizard(element, globalInstructions, getPromptContent, modal) {
        let hideTimeout;

        element.addEventListener('mouseenter', () => {
            clearTimeout(hideTimeout);
            const promptContent = typeof getPromptContent === 'function' ? getPromptContent(element) : getPromptContent;
            this.showGlobalInstructionTooltipMultiStepWizard(globalInstructions, promptContent, element, () => {
                const tooltip = modal.find('.ai-suite-global-instruction-tooltip');
                if (tooltip.length > 0) {
                    tooltip.on('mouseenter', () => {
                        clearTimeout(hideTimeout);
                    });
                    tooltip.on('mouseleave', () => {
                        hideTimeout = setTimeout(() => {
                            this.hideGlobalInstructionTooltipMultiStepWizard(modal);
                        }, 100);
                    });
                }
            }, modal);
        });

        element.addEventListener('mouseleave', () => {
            hideTimeout = setTimeout(() => {
                this.hideGlobalInstructionTooltipMultiStepWizard(modal);
            }, 100);
        });
    }

    hideGlobalInstructionTooltip(context = document) {
        const existingTooltip = context.querySelector('.ai-suite-global-instruction-tooltip');
        if (existingTooltip) {
            existingTooltip.remove();
        }
    }

    hideGlobalInstructionTooltipMultiStepWizard(modal) {
        const existingTooltip = modal.find('.ai-suite-global-instruction-tooltip');
        if (existingTooltip.length > 0) {
            existingTooltip.remove();
        }
    }

    showGlobalInstructionTooltip(globalInstructions, textareaContent, buttonElement = null, callback = null, context = document) {
        this.hideGlobalInstructionTooltip(context);

        const tooltip = this.createTooltip(buttonElement);
        tooltip.innerHTML = this.generateTooltipContent(globalInstructions, textareaContent);

        const targetElement = context === document ? document.body : context;
        targetElement.appendChild(tooltip);
        callback?.();
    }

    showGlobalInstructionTooltipMultiStepWizard(globalInstructions, textareaContent, buttonElement = null, callback = null, modal) {
        this.hideGlobalInstructionTooltipMultiStepWizard(modal);

        const tooltip = this.createTooltip(buttonElement);
        tooltip.innerHTML = this.generateTooltipContent(globalInstructions, textareaContent);

        modal.append(tooltip);
        callback?.();
    }

    createTooltip(buttonElement) {
        const tooltip = document.createElement('div');
        tooltip.className = 'ai-suite-global-instruction-tooltip';

        const positioning = this.calculateTooltipPosition(buttonElement);
        tooltip.style.cssText = `
            ${positioning}
            background: white;
            border: 1px solid #ccc;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border-radius: 4px;
            padding: 20px;
            overflow-y: auto;
            z-index: 10000;
        `;

        return tooltip;
    }

    calculateTooltipPosition(buttonElement) {
        if (!buttonElement) {
            return `position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%);`;
        }

        const { left, top, width, height } = buttonElement.getBoundingClientRect();
        const tooltipWidth = 500;
        const tooltipMaxHeight = 400;
        const margin = 10;

        let tooltipLeft = left + width + margin;
        let tooltipTop = top;

        if (tooltipLeft + tooltipWidth > window.innerWidth) {
            tooltipLeft = left - tooltipWidth - margin;
        }
        if (tooltipTop + tooltipMaxHeight > window.innerHeight) {
            tooltipTop = window.innerHeight - tooltipMaxHeight - margin;
        }

        tooltipLeft = Math.max(margin, tooltipLeft);
        tooltipTop = Math.max(margin, tooltipTop);

        return `position: fixed; top: ${tooltipTop}px; left: ${tooltipLeft}px; width: ${tooltipWidth}px; max-height: ${tooltipMaxHeight}px;`;
    }

    generateTooltipContent(globalInstructions, textareaContent) {
        const sections = [
            '<div class="form-group">',
            '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">',
            '<h3 style="margin: 0;">Global Instructions Preview</h3>',
            '</div>'
        ];

        if (globalInstructions) {
            sections.push('<h4>Global Instructions:</h4>');
            sections.push(this.createBlock(globalInstructions, true));
        }

        if (textareaContent) {
            sections.push('<h4>Current Prompt:</h4>');
            sections.push(this.createBlock(textareaContent, false));
        }

        sections.push('</div>');
        return sections.join('');
    }

    createBlock(content, hasMarginBottom) {
        const marginStyle = hasMarginBottom ? ' margin-bottom: 15px;' : '';
        return `<div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 12px;${marginStyle}"><pre style="white-space: pre-wrap; word-wrap: break-word; margin: 0; font-family: monospace;">${this.escapeHtml(content)}</pre></div>`;
    }

    initializeAllTooltips() {
        const self = this;
        const tooltipButtons = document.querySelectorAll('.global-instruction-tooltip');
        tooltipButtons.forEach(button => {
            const globalInstructions = button.dataset.globalInstructions;
            const promptContentFunction = () => self.createPromptContent(button);
            self.initializeTooltipForElement(button, globalInstructions, promptContentFunction);
        });
    }
    createPromptContent(button, context = document) {
        const formGroup = button.closest('.form-group');
        const textarea = formGroup ? formGroup.querySelector('textarea') : context.querySelector('textarea');
        return textarea ? textarea.value : '';
    }

    createPromptContentMultiStepWizard(button, modal) {
        const formGroup = button.closest('.form-group');
        let textarea;

        if (formGroup) {
            textarea = formGroup.querySelector('textarea');
        } else {
            textarea = modal.find('textarea')[0];
        }

        return textarea ? textarea.value : '';
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    metadataTooltipEventDelegation() {
        const self = this;
        const ajaxContainer = document.querySelector('#resultsToExecute');
        if (ajaxContainer){
            ajaxContainer.addEventListener('mouseenter', function(ev) {
                if(ev && ev.target && ev.target.classList && ev.target.classList.contains('global-instruction-tooltip')) {
                    const globalInstructions = ev.target.dataset.globalInstructions;
                    const promptContentFunction = () => self.createPromptContent(ev.target, ajaxContainer);
                    self.initializeTooltipForElement(ev.target, globalInstructions, promptContentFunction, ajaxContainer);
                }
            }, true);

            ajaxContainer.addEventListener('mouseleave', function(ev) {
                if(ev && ev.target && ev.target.classList && ev.target.classList.contains('global-instruction-tooltip')) {
                    setTimeout(() => {
                        self.hideGlobalInstructionTooltip(ajaxContainer);
                    }, 100);
                }
            }, true);
        }
    }
}

export default new GlobalInstructions();
