CKEDITOR.plugins.add('aisuite_aiplugin', {
    icons: 'aisuite',
    init: function(editor) {
        const pluginInstance = this;

        pluginInstance.languageCode = TYPO3.settings.aiSuite ? TYPO3.settings.aiSuite.rteLanguageCode : 'en';
        pluginInstance.promptTemplates = [];

        editor.ui.addButton('AiSuite', {
            label: 'AI Suite',
            command: 'openAiSuiteDialog',
            toolbar: 'insert',
            icon: this.path + 'icons/aisuite.svg',
            showLabel: true
        });

        editor.addCommand('openAiSuiteDialog', new CKEDITOR.dialogCommand('aisuiteDialog'));
        const self = this;
        CKEDITOR.dialog.add('aisuiteDialog', function(editor) {
            return {
                title: TYPO3.lang['tx_aisuite.module.general.adjustContentWithAi'] || 'AI Suite',
                minWidth: 600,
                minHeight: 300,
                contents: [
                    {
                        id: 'tab-main',
                        label: 'Main',
                        elements: [
                            {
                                type: 'html',
                                html: '<div id="ck-main-content">' +
                                      '<div id="ck-prompt-template-section" style="margin-bottom: 15px;">' +
                                      '<label style="font-weight: bold; display: block; margin-bottom: 8px;">' +
                                      (TYPO3.lang['tx_aisuite.module.general.promptTemplates'] || 'Prompt Templates') +
                                      '</label>' +
                                      '<div id="ck-prompt-template-dropdown"></div>' +
                                      '</div>' +
                                      '</div>'
                            },
                            {
                                type: 'textarea',
                                id: 'prompt',
                                label: TYPO3.lang['tx_aisuite.module.general.prompt'] || 'Prompt',
                                rows: 5,
                                validate: CKEDITOR.dialog.validate.notEmpty(TYPO3.lang['aiSuite.module.modal.enteredPromptMessage'] || 'Please enter a prompt with at least 10 characters.'),
                            },
                            {
                                type: 'html',
                                html: '<div id="ck-text-model-section">' +
                                      '<label style="font-weight: bold; display: block; margin-bottom: 8px;">' +
                                      (TYPO3.lang['AiSuite.textGenerationLibrary'] || 'Text Generation Library') +
                                      '</label>' +
                                      '<div id="ck-radio-button-group" style="display: flex;"></div>' +
                                      '</div>'
                            }
                        ]
                    }
                ],
                buttons: [
                    {
                        type: 'button',
                        id: 'ok',
                        label: TYPO3.lang['tx_aisuite.module.general.generate'] || 'Generate',
                        'class': 'cke_dialog_ui_button_ok',
                        onClick: function(evt) {
                            const dialog = this.getDialog();
                            dialog.fire('ok', {hide: false});
                            return false;
                        }
                    }
                ],
                onShow: async function() {
                    const dialog = this;
                    const dialogElement = dialog.getElement().$;

                    await self._reinitializeDialog(dialog);

                    const selection = editor.getSelection();
                    if (selection) {
                        const selectedElement = selection.getSelectedElement();
                        pluginInstance.selectedContent = selectedElement ? selectedElement.getHtml() : selection.getSelectedText();
                    }

                    const dialogBody = dialogElement.querySelector('.cke_dialog_body');
                    if (dialogBody && !dialogElement.querySelector('#ck-spinner-wrapper')) {
                        const spinnerHtml = '<div id="ck-spinner-wrapper" style="display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255, 255, 255, 0.9); z-index: 1000;" class="ck-spinner-wrapper">' +
                                          '<div class="spinner-wrapper" style="display: flex; align-items: center; justify-content: center; height: 100%; flex-direction: column;">' +
                                          '<div class="spinner-overlay darken">' +
                                          '<div class="spinner"></div>' +
                                          '<div class="message" style="display: flex; flex-direction: column;align-items: center; justify-content: center;margin-top:10px;">' +
                                          (TYPO3.lang['tx_aisuite.module.general.contentGenerationInProcess'] || 'Generating content...') +
                                          '<button id="ck-close-dialog-btn" style="margin-top: 15px; padding: 8px 16px; background: #F09C42; color: white; border: none; border-radius: 2px; cursor: pointer; font-size: 14px;">' +
                                          (TYPO3.lang['tx_aisuite.module.general.close'] || 'Close') +
                                          '</button>' +
                                          '</div>' +
                                          '</div>' +
                                          '</div>' +
                                          '</div>';
                        dialogBody.insertAdjacentHTML('beforeend', spinnerHtml);

                        // Add event listener for close button
                        const closeButton = dialogElement.querySelector('#ck-close-dialog-btn');
                        if (closeButton) {
                            closeButton.addEventListener('click', function() {
                                dialog.hide();
                            });
                        }
                    }

                    const okButton = dialogElement.querySelector('.cke_dialog_ui_button_ok');
                    if (okButton) {
                        okButton.style.backgroundColor = '#F09C42';
                        okButton.style.color = 'white';
                        okButton.style.border = '1px solid #F09C42';
                        okButton.style.borderRadius = '4px';
                        okButton.style.padding = '6px 12px';
                        const pencilIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="#ffffff" style="margin-right: 6px; vertical-align: middle;"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>';
                        okButton.innerHTML = pencilIcon + TYPO3.lang['tx_aisuite.module.PageContent.submit'];
                    }
                },
                onOk: async function () {
                    const dialog = this;
                    const prompt = dialog.getValueOf('tab-main', 'prompt');

                    if (!prompt || prompt.trim() === '') {
                        alert(TYPO3.lang['tx_aisuite.module.general.noContentSelected'] || 'Please enter a prompt');
                        return false;
                    }

                    const dialogElement = dialog.getElement().$;
                    const spinnerWrapper = dialogElement.querySelector('#ck-spinner-wrapper');
                    if (spinnerWrapper) {
                        spinnerWrapper.style.display = 'block';
                    }

                    const textModel = pluginInstance._getSelectedTextModel(dialogElement);

                    const postData = {
                        prompt: prompt,
                        textModel: textModel,
                        selectedContent: pluginInstance.selectedContent,
                        wholeContent: editor.getData(),
                        languageCode: pluginInstance.languageCode,
                        uuid: pluginInstance.uuid
                    };

                    await pluginInstance._sendAiRequest(postData, editor, dialog);
                    return false;
                }
            };
        });
    },

    _fetchRteContent: function() {
        return fetch(TYPO3.settings.ajaxUrls['aisuite_ckeditor_libraries'], {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({})
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(function(responseBody) {
            if (responseBody.error) {
                console.error('Error fetching RTE content:', responseBody.error);
                return null;
            } else {
                return responseBody;
            }
        })
        .catch(function(error) {
            console.error('Error fetching RTE content:', error);
            return null;
        });
    },

    _setupPromptTemplatesDropdown: function(dialogElement) {
        const dropdownContainer = dialogElement.querySelector('#ck-prompt-template-dropdown');
        const promptTemplateSection = dialogElement.querySelector('#ck-prompt-template-section');

        if (!dropdownContainer) return;

        if (this.promptTemplates.length === 0) {
            if (promptTemplateSection) {
                promptTemplateSection.style.display = 'none';
            }
            return;
        }

        if (promptTemplateSection) {
            promptTemplateSection.style.display = 'block';
        }

        const select = document.createElement('select');
        select.style.width = '100%';
        select.style.padding = '5px';
        select.style.border = '1px solid black';
        select.style.borderRadius = '2px';

        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = TYPO3.lang['tx_aisuite.module.general.selectPromptTemplate'] || 'Select Prompt Template';
        select.appendChild(defaultOption);

        this.promptTemplates.forEach(function(template) {
            const option = document.createElement('option');
            option.value = template.prompt;
            option.textContent = template.name;
            select.appendChild(option);
        });

        select.addEventListener('change', function() {
            if (this.value) {
                const dialog = CKEDITOR.dialog.getCurrent();
                if (dialog) {
                    dialog.setValueOf('tab-main', 'prompt', this.value);
                }
            }
        });

        dropdownContainer.appendChild(select);
    },

    _setupTextModelRadioButtons: function(dialogElement) {
        const radioContainer = dialogElement.querySelector('#ck-radio-button-group');
        if (!radioContainer || this.libraries.length === 0) return;

        let isFirst = true;
        this.libraries.forEach(function(library) {
            const radioWrapper = document.createElement('div');
            radioWrapper.style.marginRight = '15px';
            radioWrapper.style.marginBottom = '8px';
            radioWrapper.style.display = 'flex';
            radioWrapper.style.justifyContent = 'center';
            radioWrapper.style.alignItems = 'center';

            const radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = 'textModel';
            radio.value = library.model_identifier;
            radio.id = 'textModel_' + library.model_identifier;
            if (isFirst) {
                radio.checked = true;
                isFirst = false;
            }

            const label = document.createElement('label');
            label.htmlFor = radio.id;
            label.textContent = library.name;
            label.style.marginLeft = '5px';
            label.style.cursor = 'pointer';

            radioWrapper.appendChild(radio);
            radioWrapper.appendChild(label);
            radioContainer.appendChild(radioWrapper);
        });
    },

    _getSelectedTextModel: function(dialogElement) {
        const radios = dialogElement.querySelectorAll('input[name="textModel"]');
        for (let i = 0; i < radios.length; i++) {
            if (radios[i].checked) {
                return radios[i].value;
            }
        }
        return '';
    },

    _sendAiRequest: function(postData, editor, dialog) {
        const formData = new FormData();
        Object.keys(postData).forEach(key => {
            formData.append(key, postData[key]);
        });

        fetch(TYPO3.settings.ajaxUrls['aisuite_ckeditor_request'], {
            method: 'POST',
            headers: {
                'Accept': 'application/json'
            },
            body: formData
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(function(responseBody) {
            if (responseBody.error) {
                console.error('Error generating content:', responseBody.error);
                alert('Error generating content. Please try again.');

                const dialogElement = dialog.getElement().$;
                const spinnerWrapper = dialogElement.querySelector('#ck-spinner-wrapper');

                if (spinnerWrapper) {
                   spinnerWrapper.style.display = 'none';
                }
            } else if (responseBody && responseBody.output) {
                editor.insertHtml(responseBody.output);
                dialog.hide();
            } else {
                alert('Error generating content. Please try again.');

                const dialogElement = dialog.getElement().$;
                const spinnerWrapper = dialogElement.querySelector('#ck-spinner-wrapper');

                if (spinnerWrapper) {
                    spinnerWrapper.style.display = 'none';
                }
            }
        })
        .catch(function(error) {
            console.error('AJAX request failed:', error);
            alert('Error generating content. Please try again.');

            const dialogElement = dialog.getElement().$;
            const spinnerWrapper = dialogElement.querySelector('#ck-spinner-wrapper');

            if (spinnerWrapper) {
                spinnerWrapper.style.display = 'none';
            }
        });
    },

    _reinitializeDialog: async function (dialog) {
        const pluginInstance = this;

        pluginInstance.libraries = [];
        pluginInstance.promptTemplates = [];
        pluginInstance.uuid = '';
        pluginInstance.selectedContent = '';

        const data = await pluginInstance._fetchRteContent();
        if (data && data.output) {
            pluginInstance.uuid = data.output.uuid;
            pluginInstance.promptTemplates = data.output.promptTemplates;
            pluginInstance.libraries = data.output.libraries;
        }

        dialog.setValueOf('tab-main', 'prompt', '');

        const dialogElement = dialog.getElement().$;
        const spinnerWrapper = dialogElement.querySelector('#ck-spinner-wrapper');

        if (spinnerWrapper) {
            spinnerWrapper.style.display = 'none';
        }

        const promptDropdownContainer = dialogElement.querySelector('#ck-prompt-template-dropdown');
        if (promptDropdownContainer) {
            promptDropdownContainer.innerHTML = '';
            pluginInstance._setupPromptTemplatesDropdown(dialogElement);
        }

        const radioContainer = dialogElement.querySelector('#ck-radio-button-group');
        if (radioContainer) {
            radioContainer.innerHTML = '';
            pluginInstance._setupTextModelRadioButtons(dialogElement);
        }
    }
});
