class WorkflowCommandOptions {
    constructor(container) {
        this.options = JSON.parse(container.dataset.aiSuiteWorkflowCommandOptions);
        this.fields = {};
        ['type', 'model', 'column'].forEach((name) => {
            const input = document.getElementById('options_' + name + '_values');
            if (input === null) {
                return;
            }
            input.classList.remove('t3js-clearable');
            const select = document.createElement('select');
            select.className = 'form-select';
            select.dataset.aiSuiteWorkflowOption = name;
            input.after(select);
            this.fields[name] = {
                input: input,
                select: select,
                checkbox: document.getElementById('options_' + name),
                value: input.value,
            };
            input.addEventListener('input', () => {
                this.fields[name].value = input.value;
            });
            select.addEventListener('change', () => {
                this.fields[name].value = select.value;
                this.syncCheckbox(name);
                if (name === 'type') {
                    this.renderDependentFields(true);
                }
            });
        });

        if (this.fields.type === undefined) {
            return;
        }
        this.fill('type', this.options.types);
        this.renderDependentFields(false);
    }

    renderDependentFields(typeChanged) {
        const type = this.fields.type.value;
        this.fill('model', type === '' ? {} : this.options.models[type] ?? {}, typeChanged);
        this.fill('column', type === '' ? {} : this.options.columns[type] ?? {}, typeChanged);
    }

    fill(name, choices, dropUnknownValue = false) {
        const field = this.fields[name];
        if (field === undefined) {
            return;
        }
        if (dropUnknownValue && choices !== null && !Object.hasOwn(choices, field.value)) {
            field.value = '';
            field.input.value = '';
            this.syncCheckbox(name);
        }
        if (choices === null) {
            this.useInput(field, true);

            return;
        }
        this.useInput(field, false);

        field.select.replaceChildren(new Option('–', ''));
        Object.entries(choices).forEach(([value, label]) => {
            field.select.add(new Option(label + ' (' + value + ')', value));
        });
        if (field.value !== '' && !Object.hasOwn(choices, field.value)) {
            field.select.add(new Option(field.value, field.value));
        }
        field.select.value = field.value;
        field.select.disabled = Object.keys(choices).length === 0 && field.value === '';
    }

    useInput(field, useInput) {
        (field.input.closest('.form-control-clearable-wrapper') ?? field.input).hidden = !useInput;
        field.input.disabled = !useInput;
        field.select.hidden = useInput;
        field.select.disabled = useInput;
        field.select.name = useInput ? '' : field.input.name;
    }

    syncCheckbox(name) {
        const checkbox = this.fields[name].checkbox;
        if (checkbox !== null) {
            checkbox.checked = this.fields[name].value !== '';
        }
    }
}

const container = document.querySelector('[data-ai-suite-workflow-command-options]');
if (container !== null) {
    new WorkflowCommandOptions(container);
}
