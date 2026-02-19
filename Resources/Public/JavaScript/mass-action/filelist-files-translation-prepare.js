import General from "@autodudes/ai-suite/helper/general.js";
import Generation from "@autodudes/ai-suite/helper/generation.js";
import Ajax from '@autodudes/ai-suite/helper/ajax.js';
import Notification from "@typo3/backend/notification.js";
import InfoWindow from "@typo3/backend/info-window.js";
import GlobalInstructions from "@autodudes/ai-suite/helper/global-instructions.js";

class FilelistFilesTranslationPrepare {

    constructor() {
        this.filesTranslationPrepareFormEventListener();
        Generation.cancelGeneration();
        this.fileSelectionEventDelegation();
        GlobalInstructions.metadataTooltipEventDelegation();
        this.initGlossaries().then();
    }

    async initGlossaries() {
        await this.glossarySelectionEventDelegation();
        await this.handleTextAiModelChange('ChatGPT');
    }

    filesTranslationPrepareFormEventListener() {
        const self = this;
        let filesForm = document.querySelector('form[name="filesTranslationPrepareExecute"]');
        filesForm.addEventListener('submit', async function(ev) {
            ev.preventDefault();
            await self.updateContent();
        });
    }

    fileSelectionEventDelegation() {
        const self = this;

        document.querySelectorAll('#resultsToExecute').forEach(function(element) {
            element.addEventListener('click', async function(ev) {
                if(ev && ev.target) {
                    if(ev.target.nodeName === 'BUTTON' && ev.target.type === 'submit' && ev.target.id === 'filesTranslationExecuteFormSubmitBtn') {
                        ev.preventDefault();
                        let checkboxes = document.querySelectorAll('input[name^="file-selection"]');
                        let selectedFiles = {};
                        checkboxes.forEach(function(checkbox) {
                            if(checkbox.checked) {
                                let inputValues = document.querySelectorAll('input[name^="filesSourceContent[' + checkbox.value + ']"], textarea[name^="filesSourceContent[' + checkbox.value + ']"]');
                                inputValues.forEach(function(input) {
                                    let column = input.name.replace('filesSourceContent[' + checkbox.value + '][', '').replace(']', '');
                                    let obj = {};
                                    obj[column] = input.value;
                                    obj['mode'] = input.dataset.mode;
                                    selectedFiles[checkbox.value] = { ...selectedFiles[checkbox.value], ...obj };
                                });
                            }
                        });

                        if(Object.keys(selectedFiles).length === 0) {
                            Notification.warning(TYPO3.lang['AiSuite.notification.generation.massAction.missingSelection'], TYPO3.lang['AiSuite.notification.generation.massAction.missingFiles']);
                        } else {
                            Generation.showSpinner();
                            const glossarySelect = document.getElementById('glossarySelect');
                            const selectedGlossary = glossarySelect ? glossarySelect.value : '';

                            const baseFormData = {
                                parentUuid: document.querySelector('input#parentUuid').value,
                                column: document.querySelector('select#column').value,
                                sourceLanguage: document.querySelector('select#sourceLanguage').value,
                                targetLanguage: document.querySelector('select#targetLanguage').value,
                                textAiModel: document.querySelector('.text-generation-library input[type="radio"]:checked').value,
                                glossary: selectedGlossary
                            };

                            if (!baseFormData.parentUuid || !baseFormData.column || !baseFormData.sourceLanguage || !baseFormData.targetLanguage || !baseFormData.textAiModel) {
                                Notification.error(TYPO3.lang['AiSuite.error.invalidFormData']);
                                Generation.hideSpinner();
                                return;
                            }

                            await self.processBatches(selectedFiles, baseFormData);
                            Generation.hideSpinner();
                            await self.updateContent();
                            Notification.success(TYPO3.lang['AiSuite.notification.generation.massAction.success'], TYPO3.lang['AiSuite.notification.generation.massAction.successDescription']);
                            selectedFiles = null;
                        }
                    }
                    if(ev.target.nodeName === 'INPUT' && ev.target.type === 'checkbox' && ev.target.id === 'toggleFileTranslationSelection') {
                        let checkboxes = document.querySelectorAll('input[name^="file-selection"]');
                        checkboxes.forEach(function(checkbox) {
                            checkbox.checked = ev.target.checked;
                        });
                        self.calculateRequestAmount();
                    }
                    if(ev.target.nodeName === 'INPUT' && ev.target.classList.contains('file-metadata-field')) {
                        if(ev.target.closest('.list-group-item').querySelector('input[name^="file-selection"]')) {
                            ev.target.closest('.list-group-item').querySelector('input[name^="file-selection"]').checked = true;
                        }
                        self.calculateRequestAmount();
                    }
                    if(ev.target.nodeName === 'INPUT' && ev.target.type === 'checkbox' && ev.target.name && ev.target.name.includes('file-selection')) {
                        self.calculateRequestAmount();
                    }
                    if(ev.target.nodeName === 'DIV' && ev.target.classList.contains('file-meta-content-info')) {
                        const table = ev.target.dataset.table;
                        const uid = ev.target.dataset.uid;
                        InfoWindow.showItem(table, uid);
                    }
                }
            });
        });
    }

    async glossarySelectionEventDelegation() {
        const self = this;
        document.addEventListener('change', async function(ev) {
            if (ev.target.type === 'radio' && ev.target.name === 'libraries[textGenerationLibrary]') {
                await self.handleTextAiModelChange(ev.target.value);
            }
        });
    }

    async handleTextAiModelChange(textAiModel) {
        const glossarySelection = document.getElementById('glossarySelection');
        if(glossarySelection === null) {
            return;
        }
        glossarySelection.style.display = 'none';

        if (textAiModel === 'GoogleTranslate') {
            return;
        }

        const sourceLanguageSelect = document.getElementById('sourceLanguage');
        const targetLanguageSelect = document.getElementById('targetLanguage');

        if (!sourceLanguageSelect?.value || !targetLanguageSelect?.value) {
            this.updateGlossarySelect([]);
            return;
        }

        try {
            const formData = new FormData();
            formData.append('sourceLanguageId', sourceLanguageSelect.value);
            formData.append('targetLanguageId', targetLanguageSelect.value);
            formData.append('textAiModel', textAiModel);

            const response = await Ajax.sendAjaxRequest('aisuite_glossary_fetch_file_translation', formData);
            if (response && response.glossaries) {
                this.updateGlossarySelect(response.glossaries);
            } else {
                this.updateGlossarySelect([]);
            }
        } catch (error) {
            console.error('Error fetching glossaries:', error);
            this.updateGlossarySelect([]);
        }
    }

    updateGlossarySelect(glossaries) {
        const glossarySelection = document.getElementById('glossarySelection');

        const existingSelect = glossarySelection.querySelector('select');
        if (existingSelect) {
            existingSelect.remove();
        }

        if (Object.keys(glossaries).length === 0) {
            return;
        }

        const select = document.createElement('select');
        select.name = 'options[glossary]';
        select.className = 'form-select mb-4';
        select.id = 'glossarySelect';

        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = TYPO3.lang['AiSuite.generation.massAction.selectGlossary'];
        select.appendChild(defaultOption);

        Object.entries(glossaries).forEach(([key, value]) => {
            const option = document.createElement('option');
            option.value = key;
            option.textContent = value;
            select.appendChild(option);
        });

        glossarySelection.appendChild(select);
        glossarySelection.style.display = 'block';
    }

    async sendFilesToExecute(formData, selectedFiles, handledFiles, maxRetries = 2, delay = 1000) {
        try {
            if (!formData.has('massActionFilesTranslationExecute[files]')) {
                throw new Error(TYPO3.lang['AiSuite.error.invalidFormData']);
            }

            let lastError;
            for (let attempt = 1; attempt <= maxRetries; attempt++) {
                try {
                    let res = await Ajax.sendAjaxRequest('aisuite_massaction_filelist_files_translate_execute', formData);

                    if (!General.isUsable(res)) {
                        throw new Error(TYPO3.lang['AiSuite.error.invalidServerResponse']);
                    }

                    if (res.output?.failedFiles?.length > 0) {
                        Notification.error(
                            TYPO3.lang['AiSuite.notification.generation.error'],
                            TYPO3.lang['AiSuite.notification.generation.failedFiles'] + res.output.failedFiles.join(', ')
                        );
                    }

                    let statusElement = document.querySelector('.module-body .spinner-overlay .status');
                    if (statusElement !== null && selectedFiles && handledFiles) {
                        statusElement.innerHTML = `${res.output.message}${Object.keys(handledFiles).length} / ${Object.keys(selectedFiles).length}`;
                    }

                    return res;
                } catch (error) {
                    lastError = error;
                    console.warn(`Request attempt ${attempt} failed:`, error);
                    if (attempt < maxRetries) {
                        await new Promise(resolve => setTimeout(resolve, delay * attempt));
                    }
                }
            }
            throw lastError;

        } catch (error) {
            console.error('sendFilesToExecute failed after all retries:', error.message);
            Notification.error(
                TYPO3.lang['AiSuite.notification.generation.error'],
                TYPO3.lang['AiSuite.notification.generation.requestError']
            );
            throw error;
        }
    }

    createFormData(baseFormData, currentFiles) {
        let formData = new FormData();
        formData.append('massActionFilesTranslationExecute[parentUuid]', baseFormData.parentUuid);
        formData.append('massActionFilesTranslationExecute[column]', baseFormData.column);
        formData.append('massActionFilesTranslationExecute[sourceLanguage]', baseFormData.sourceLanguage);
        formData.append('massActionFilesTranslationExecute[targetLanguage]', baseFormData.targetLanguage);
        formData.append('massActionFilesTranslationExecute[textAiModel]', baseFormData.textAiModel);
        formData.append('massActionFilesTranslationExecute[glossary]', baseFormData.glossary || '');
        formData.append('massActionFilesTranslationExecute[files]', JSON.stringify(currentFiles));
        return formData;
    }

    async processBatches(selectedFiles, baseFormData) {
        const batchSize = 5;
        const delayBetweenBatches = 500;

        const fileKeys = Object.keys(selectedFiles);
        let handledFiles = {};

        for (let i = 0; i < fileKeys.length; i += batchSize) {
            const batchKeys = fileKeys.slice(i, i + batchSize);
            const currentFiles = {};

            batchKeys.forEach(key => {
                currentFiles[key] = selectedFiles[key];
            });

            try {
                const formData = this.createFormData(baseFormData, currentFiles);
                await this.sendFilesToExecute(formData, selectedFiles, handledFiles);
                handledFiles = { ...handledFiles, ...currentFiles };

                let statusElement = document.querySelector('.module-body .spinner-overlay .status');
                if (statusElement !== null) {
                    statusElement.innerHTML = `${Object.keys(handledFiles).length} / ${Object.keys(selectedFiles).length}`;
                }

                if (i + batchSize < fileKeys.length) {
                    await new Promise(resolve => setTimeout(resolve, delayBetweenBatches));
                }
            } catch (error) {
                Notification.warning(
                    TYPO3.lang['AiSuite.notification.generation.error'],
                    TYPO3.lang['AiSuite.massAction.batchFailed'].replace('{0}', Math.floor(i/batchSize) + 1)
                );
            }
        }

        return handledFiles;
    }

    async updateContent() {
        Generation.showSpinner();
        let filesForm = document.querySelector('form[name="filesTranslationPrepareExecute"]');
        const formData = new FormData(filesForm);
        let res = await Ajax.sendAjaxRequest('aisuite_massaction_filelist_files_translate_update_view', formData);
        if (General.isUsable(res)) {
            if(General.isUsable(res.output) && !General.isUsable(res.output.content)) {
                document.querySelector('#resultsToExecute').innerHTML = res.output;
            } else {
                document.querySelector('#resultsToExecute').innerHTML = res.output.content;
            }
        } else {
            Notification.error(TYPO3.lang['AiSuite.notification.generation.error'], TYPO3.lang['AiSuite.notification.generation.requestError']);
        }
        await this.handleTextAiModelChange('ChatGPT');
        Generation.hideSpinner();
    }

    hasValidFieldContent(listItem, fieldClass, checkVisibility = true) {
        const field = listItem.querySelector(`.filelist-input.${fieldClass}`);
        if (!field) return false;

        if (checkVisibility && field.classList.contains('d-none')) {
            return false;
        }

        const readonlyField = field.querySelector('.file-metadata-field-readonly');
        return readonlyField && readonlyField.value.trim() !== '';
    }

    countFieldsForListItem(listItem, column) {
        let count = 0;

        if (column === 'all') {
            if (this.hasValidFieldContent(listItem, 'title')) {
                count++;
            }
            if (this.hasValidFieldContent(listItem, 'alternative')) {
                count++;
            }
            if (this.hasValidFieldContent(listItem, 'description')) {
                count++;
            }
        } else if (column === 'title') {
            if (this.hasValidFieldContent(listItem, 'title', false)) {
                count++;
            }
        } else if (column === 'alternative') {
            if (this.hasValidFieldContent(listItem, 'alternative', false)) {
                count++;
            }
        } else if (column === 'description') {
            if (this.hasValidFieldContent(listItem, 'description', false)) {
                count++;
            }
        }

        return count;
    }

    calculateRequestAmount() {
        let calculatedRequests = 0;
        document.querySelectorAll('.library').forEach(function (library) {
            let amountField = library.querySelector('.request-amount span');
            if(library.style.display !== 'none' && amountField !== null) {
                let modelId = library.querySelector('input[type="radio"]:checked').id;
                let amount = parseInt(library.querySelector('label[for="' + modelId +'"] .request-amount span').textContent);
                calculatedRequests += amount;
            }
        });

        const column = document.querySelector('form[name="filesTranslationPrepareExecute"] #column').value;
        let selectedFiles = 0;
        const self = this;

        document.querySelectorAll('input[name^="file-selection"]').forEach(function(checkbox) {
            if(checkbox.checked) {
                const listItem = checkbox.closest('.list-group-item');
                if (listItem) {
                    selectedFiles += self.countFieldsForListItem(listItem, column);
                }
            }
        });

        calculatedRequests *= selectedFiles;
        let marker = TYPO3.lang['aiSuite.module.multipleCredits'];
        if(calculatedRequests === 1) {
            marker = TYPO3.lang['aiSuite.module.oneCredit'];
        }
        document.querySelector('div[data-module-id="aiSuite"] .calculated-requests').textContent = '(' + calculatedRequests + ' ' + marker + ')';
    }
}
export default new FilelistFilesTranslationPrepare();
