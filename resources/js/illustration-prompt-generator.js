const isReusableTagGroup = (value) =>
    value !== null &&
    typeof value === 'object' &&
    typeof value.label === 'string' &&
    typeof value.tags === 'string';

const isGenerationResult = (value) =>
    value !== null &&
    typeof value === 'object' &&
    typeof value.positivePrompt === 'string' &&
    typeof value.negativePrompt === 'string' &&
    typeof value.model === 'string' &&
    typeof value.promptStyle === 'string' &&
    typeof value.generationTimeMs === 'number' &&
    Array.isArray(value.reusableTagGroups) &&
    value.reusableTagGroups.length >= 1 &&
    value.reusableTagGroups.length <= 10 &&
    value.reusableTagGroups.every(isReusableTagGroup);

const responseJson = async (response) => {
    try {
        return await response.json();
    } catch {
        return null;
    }
};

const clickedOutsideDialog = (event, dialog) => {
    if (event.target !== dialog) {
        return false;
    }
    const bounds = dialog.getBoundingClientRect();
    return (
        event.clientX < bounds.left ||
        event.clientX > bounds.right ||
        event.clientY < bounds.top ||
        event.clientY > bounds.bottom
    );
};

export const initializeIllustrationPromptGenerator = ({
    page,
    documentObject,
    fetcher,
    csrfToken,
    getDefaultPrompts = () => ({ positive: '', negative: '' }),
    reloadPromptOptions = async () => {},
    applyPrompt,
}) => {
    const dialog = page.querySelector('[data-illustration-prompt-dialog]');
    const openButton = page.querySelector('[data-illustration-prompt-open]');
    const closeButton = page.querySelector('[data-illustration-prompt-close]');
    const inputMode = page.querySelector('[data-illustration-prompt-input-mode]');
    const freeInput = page.querySelector('[data-illustration-prompt-free-input]');
    const templateInput = page.querySelector('[data-illustration-prompt-template-input]');
    const templateFields = [...page.querySelectorAll('[data-illustration-prompt-template]')];
    const description = page.querySelector('[data-illustration-prompt-description]');
    const style = page.querySelector('[data-illustration-prompt-style]');
    const generateButton = page.querySelector('[data-illustration-prompt-generate]');
    const status = page.querySelector('[data-illustration-prompt-status]');
    const preview = page.querySelector('[data-illustration-prompt-preview]');
    const positive = page.querySelector('[data-illustration-prompt-positive]');
    const negative = page.querySelector('[data-illustration-prompt-negative]');
    const includeDefaults = page.querySelector('[data-illustration-prompt-include-defaults]');
    const defaultsPreview = page.querySelector('[data-illustration-prompt-defaults]');
    const defaultPositive = page.querySelector('[data-illustration-prompt-default-positive]');
    const defaultNegative = page.querySelector('[data-illustration-prompt-default-negative]');
    const metadata = page.querySelector('[data-illustration-prompt-metadata]');
    const applyButton = page.querySelector('[data-illustration-prompt-apply]');
    const tagGroupsSection = page.querySelector('[data-illustration-prompt-tag-groups]');
    const tagGroupsList = page.querySelector('[data-illustration-prompt-tag-group-list]');
    const saveTagGroupsButton = page.querySelector('[data-illustration-prompt-tag-group-save]');
    const tagGroupsStatus = page.querySelector('[data-illustration-prompt-tag-group-status]');
    let requestController = null;
    let requestToken = 0;
    let pending = false;
    let tagGroups = [];
    let tagGroupsSavePending = false;

    if (
        !documentObject.defaultView ||
        !dialog ||
        !openButton ||
        !closeButton ||
        !inputMode ||
        !freeInput ||
        !templateInput ||
        templateFields.length !== 6 ||
        !description ||
        !style ||
        !generateButton ||
        !status ||
        !preview ||
        !positive ||
        !negative ||
        !includeDefaults ||
        !defaultsPreview ||
        !defaultPositive ||
        !defaultNegative ||
        !metadata ||
        !applyButton ||
        !tagGroupsSection ||
        !tagGroupsList ||
        !saveTagGroupsButton ||
        !tagGroupsStatus
    ) {
        return { abort: () => {} };
    }

    const selectedTagGroups = () =>
        [...tagGroupsList.querySelectorAll('input[type="checkbox"]')]
            .filter((input) => input.checked)
            .map((input) => tagGroups[Number(input.dataset.index)])
            .filter(Boolean);

    const updateSaveTagGroupsButton = () => {
        saveTagGroupsButton.disabled = tagGroupsSavePending || selectedTagGroups().length === 0;
    };

    const renderTagGroups = (candidates) => {
        tagGroups = candidates;
        tagGroupsList.replaceChildren(
            ...candidates.map((candidate, index) => {
                const label = documentObject.createElement('label');
                label.className = 'illustration-prompt-dialog__tag-group-item';
                label.dataset.illustrationPromptTagGroupItem = '';
                const checkbox = documentObject.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.dataset.index = String(index);
                checkbox.addEventListener('change', updateSaveTagGroupsButton);
                const text = documentObject.createElement('span');
                const name = documentObject.createElement('strong');
                name.textContent = candidate.label;
                const tags = documentObject.createElement('span');
                tags.className = 'muted-text';
                tags.textContent = candidate.tags;
                text.append(name, tags);
                label.append(checkbox, text);
                return label;
            }),
        );
        tagGroupsStatus.textContent = '';
        tagGroupsSection.hidden = candidates.length === 0;
        updateSaveTagGroupsButton();
    };

    const updateDefaultsPreview = () => {
        const defaults = getDefaultPrompts();
        defaultPositive.value = defaults.positive;
        defaultNegative.value = defaults.negative;
        defaultsPreview.hidden =
            !includeDefaults.checked || (defaults.positive === '' && defaults.negative === '');
    };

    const abort = () => {
        requestToken += 1;
        requestController?.abort();
        requestController = null;
        pending = false;
        generateButton.disabled = false;
        inputMode.disabled = false;
        description.disabled = false;
        for (const field of templateFields) {
            field.disabled = false;
        }
        style.disabled = false;
    };
    const updateInputMode = () => {
        const usesTemplate = inputMode.value === 'template';
        freeInput.hidden = usesTemplate;
        templateInput.hidden = !usesTemplate;
    };
    const close = () => {
        abort();
        dialog.close();
    };
    openButton.addEventListener('click', () => {
        dialog.showModal();
        const target = inputMode.value === 'template' ? templateFields[0] : description;
        target.focus();
    });
    closeButton.addEventListener('click', (event) => {
        event.preventDefault();
        close();
    });
    dialog.addEventListener('click', (event) => {
        if (clickedOutsideDialog(event, dialog)) {
            close();
        }
    });
    dialog.addEventListener('cancel', abort);
    dialog.addEventListener('close', abort);
    inputMode.addEventListener('change', updateInputMode);
    includeDefaults.addEventListener('change', updateDefaultsPreview);
    updateInputMode();

    generateButton.addEventListener('click', async () => {
        if (pending) {
            return;
        }
        const usesTemplate = inputMode.value === 'template';
        const value = description.value.trim();
        const template = Object.fromEntries(
            templateFields
                .map((field) => [field.dataset.illustrationPromptTemplate, field.value.trim()])
                .filter(([, fieldValue]) => fieldValue !== ''),
        );
        if (usesTemplate && Object.keys(template).length === 0) {
            status.textContent = 'テンプレートの少なくとも1項目を入力してください。';
            return;
        }
        if (!usesTemplate && (value.length === 0 || value.length > 2000)) {
            status.textContent =
                value.length === 0
                    ? '指示を入力してください。'
                    : '指示は2,000文字以内で入力してください。';
            return;
        }

        pending = true;
        generateButton.disabled = true;
        inputMode.disabled = true;
        description.disabled = true;
        for (const field of templateFields) {
            field.disabled = true;
        }
        style.disabled = true;
        preview.hidden = true;
        status.textContent = 'プロンプトを生成しています。';
        requestController = new AbortController();
        const token = ++requestToken;
        try {
            const response = await fetcher(page.dataset.illustrationPromptGenerationUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(
                    usesTemplate
                        ? { inputMode: 'template', template, promptStyle: style.value }
                        : { description: value, promptStyle: style.value },
                ),
                signal: requestController.signal,
            });
            const body = await responseJson(response);
            if (token !== requestToken) {
                return;
            }
            if (!response.ok || !isGenerationResult(body)) {
                const fieldMessage = body?.errors?.description?.[0] ?? body?.errors?.template?.[0];
                status.textContent =
                    typeof fieldMessage === 'string'
                        ? fieldMessage
                        : `${typeof body?.message === 'string' ? body.message : '生成に失敗しました。'} 外部LLMサービスの接続状態や設定を確認して再試行してください。`;
                return;
            }
            positive.value = body.positivePrompt;
            negative.value = body.negativePrompt;
            metadata.textContent = `${body.model} / ${body.generationTimeMs} ms`;
            renderTagGroups(body.reusableTagGroups);
            updateDefaultsPreview();
            preview.hidden = false;
            status.textContent = '生成結果を確認し、必要なら編集してから反映してください。';
        } catch (error) {
            if (token === requestToken && error?.name !== 'AbortError') {
                status.textContent = '生成に失敗しました。入力内容を保ったまま再試行できます。';
            }
        } finally {
            if (token === requestToken) {
                pending = false;
                requestController = null;
                generateButton.disabled = false;
                inputMode.disabled = false;
                description.disabled = false;
                for (const field of templateFields) {
                    field.disabled = false;
                }
                style.disabled = false;
            }
        }
    });

    saveTagGroupsButton.addEventListener('click', async () => {
        if (tagGroupsSavePending) {
            return;
        }
        const groups = selectedTagGroups();
        if (groups.length === 0) {
            return;
        }
        tagGroupsSavePending = true;
        updateSaveTagGroupsButton();
        tagGroupsStatus.textContent = '候補を保存しています。';
        try {
            const response = await fetcher(page.dataset.illustrationPromptTagGroupsUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ groups }),
            });
            const body = await responseJson(response);
            if (
                !response.ok ||
                typeof body?.savedCount !== 'number' ||
                typeof body?.duplicateCount !== 'number'
            ) {
                throw new Error('save failed');
            }
            for (const input of tagGroupsList.querySelectorAll('input[type="checkbox"]:checked')) {
                input.checked = false;
            }
            const result = `${body.savedCount}件保存、${body.duplicateCount}件重複`;
            try {
                await reloadPromptOptions();
                tagGroupsStatus.textContent = `${result}でした。`;
            } catch {
                tagGroupsStatus.textContent = `${result}でした。保存済みですが一覧の更新に失敗しました。画面を再読み込みしてください。`;
            }
        } catch {
            tagGroupsStatus.textContent =
                '候補を保存できませんでした。選択を保ったまま再試行できます。';
        } finally {
            tagGroupsSavePending = false;
            updateSaveTagGroupsButton();
        }
    });

    applyButton.addEventListener('click', () => {
        if (preview.hidden || positive.value.trim() === '') {
            status.textContent = 'positiveプロンプトを入力してください。';
            return;
        }
        applyPrompt({
            positivePrompt: positive.value,
            negativePrompt: negative.value,
            includeDefaultPrompts: includeDefaults.checked,
        });
        close();
    });

    return { abort };
};
