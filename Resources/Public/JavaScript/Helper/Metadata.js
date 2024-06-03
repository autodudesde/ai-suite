define([], function() {
    /**
     * @param {string} output
     * @returns {HTMLDivElement}
     */
    function getSelectionOptions(output) {
        let selection = document.querySelector('.ai-suite-suggestions');
        if (selection) {
            selection.remove();
        }
        selection = document.createElement('div');
        selection.innerHTML = output;
        selection.classList.add('ai-suite-suggestions');
        return selection;
    }

    /**
     * @param {string} model
     * @param {int} modelId
     * @param {string} fieldName
     * @param {object} selectedSuggestion
     * @param {boolean} addToAdditionalFields
     * @param {function} addSelectionToAdditionalFields
     */
    function insertSelectedSuggestions(model, modelId, fieldName, selectedSuggestion, addToAdditionalFields = false, addSelectionToAdditionalFields = null) {
        if (document.querySelector('input[data-formengine-input-name="data[' + model + '][' + modelId + '][' + fieldName + ']"]')) {
            document.querySelector('input[data-formengine-input-name="data[' + model + '][' + modelId + '][' + fieldName + ']"]').value = selectedSuggestion.value;
            document.querySelector('input[name="data[' + model + '][' + modelId + '][' + fieldName + ']"]').value = selectedSuggestion.value;
            if (addToAdditionalFields) {
                addSelectionToAdditionalFields(modelId, fieldName, selectedSuggestion.value);
            }
        } else {
            document.querySelector('textarea[data-formengine-input-name="data[' + model + '][' + modelId + '][' + fieldName + ']"]').value = selectedSuggestion.value;
            document.querySelector('textarea[name="data[' + model + '][' + modelId + '][' + fieldName + ']"]').value = selectedSuggestion.value;
            if (addToAdditionalFields) {
                addSelectionToAdditionalFields(modelId, fieldName, selectedSuggestion.value);
            }
        }
    }

    /**
     * @param {object} selection
     */
    function addRemoveButtonListener(selection) {
        if (document.getElementById('suggestionBtnRemove')) {
            document.getElementById('suggestionBtnRemove').addEventListener('click', function (ev) {
                ev.preventDefault();
                selection.remove();
            });
        }
    }

    return {
        getSelectionOptions: getSelectionOptions,
        insertSelectedSuggestions: insertSelectedSuggestions,
        addRemoveButtonListener: addRemoveButtonListener
    };
});

