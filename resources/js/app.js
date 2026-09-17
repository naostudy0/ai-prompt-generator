import {
    createPromptOutputs,
    requestDefaultPrompts,
    requestSaveDefaultPrompt,
    writePromptToClipboard,
} from './default-prompts.js';

export const initializePromptPreparationPage = ({
    documentObject = document,
    fetcher = fetch,
    clipboard = navigator.clipboard,
    schedule,
} = {}) => {
    const page = documentObject.querySelector('[data-prompt-preparation]');
    const view = documentObject.defaultView;

    if (!view || !(page instanceof view.HTMLElement)) {
        return;
    }

    const { HTMLButtonElement, HTMLElement, HTMLTextAreaElement } = view;
    const scheduleTask = schedule ?? view.setTimeout.bind(view);
    const csrfToken = documentObject
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');
    const loadState = page.querySelector('.load-state');
    const loadStatus = page.querySelector('[data-load-status]');
    const retryButton = page.querySelector('[data-retry]');
    const displayButton = page.querySelector('[data-display]');
    const promptElements = [...page.querySelectorAll('[data-default-prompt]')];
    const outputElements = [...page.querySelectorAll('[data-output]')];
    const outputSection = page.querySelector('[data-output-section]');
    const toast = page.querySelector('[data-toast]');
    const savedPrompts = { positive: '', negative: '' };
    const selectedPrompts = { positive: true, negative: true };
    let promptsLoaded = false;
    let editingPrompt = false;
    let toastTimer;

    const isPolarity = (value) => value === 'positive' || value === 'negative';

    const setLoadStatus = (message, failed = false) => {
        if (loadStatus instanceof HTMLElement) {
            loadStatus.textContent = message;
            loadStatus.dataset.state = failed ? 'error' : 'ready';
        }

        if (retryButton instanceof HTMLButtonElement) {
            retryButton.hidden = !failed;
        }

        if (loadState instanceof HTMLElement) {
            loadState.hidden = message === '' && !failed;
        }
    };

    const showToast = (message) => {
        if (!(toast instanceof HTMLElement)) {
            return;
        }

        if (toastTimer !== undefined) {
            view.clearTimeout(toastTimer);
        }

        toast.textContent = message;
        toast.hidden = false;
        toastTimer = scheduleTask(() => {
            toast.hidden = true;
        }, 2400);
    };

    const setInputActionsDisabled = (disabled) => {
        promptElements.forEach((promptElement) => {
            const editButton = promptElement.querySelector('[data-edit]');
            const selectButton = promptElement.querySelector('[data-select]');

            if (editButton instanceof HTMLButtonElement) {
                editButton.disabled = disabled;
            }

            if (selectButton instanceof HTMLButtonElement) {
                selectButton.disabled = disabled;
            }
        });

        if (displayButton instanceof HTMLButtonElement) {
            displayButton.disabled = disabled;
        }
    };

    const getOutputElement = (polarity) =>
        outputElements.find((element) => element.dataset.output === polarity);

    const updateSelection = (promptElement, polarity) => {
        const selectButton = promptElement.querySelector('[data-select]');
        const marker = promptElement.querySelector('[data-selection-marker]');
        const selected = selectedPrompts[polarity];

        if (selectButton instanceof HTMLButtonElement) {
            selectButton.setAttribute('aria-pressed', selected ? 'true' : 'false');
        }

        if (marker instanceof HTMLElement) {
            marker.hidden = !selected;
        }
    };

    const updateCopyAvailability = (outputElement) => {
        const content = outputElement.querySelector('[data-output-content]');
        const copyButton = outputElement.querySelector('[data-copy]');

        if (content instanceof HTMLTextAreaElement && copyButton instanceof HTMLButtonElement) {
            copyButton.disabled = content.value === '';
        }
    };

    const closeEditor = (promptElement) => {
        const editor = promptElement.querySelector('[data-editor]');
        const status = promptElement.querySelector('[data-editor-status]');
        const saveButton = promptElement.querySelector('[data-save]');
        const cancelButton = promptElement.querySelector('[data-cancel]');
        const editButton = promptElement.querySelector('[data-edit]');

        if (editor instanceof HTMLElement) {
            editor.hidden = true;
        }

        if (status instanceof HTMLElement) {
            status.textContent = '';
        }

        if (saveButton instanceof HTMLButtonElement) {
            saveButton.disabled = false;
        }

        if (cancelButton instanceof HTMLButtonElement) {
            cancelButton.disabled = false;
        }

        editingPrompt = false;
        setInputActionsDisabled(!promptsLoaded);

        if (editButton instanceof HTMLButtonElement) {
            editButton.focus();
        }
    };

    const openEditor = (promptElement) => {
        const polarity = promptElement.dataset.defaultPrompt;
        const editor = promptElement.querySelector('[data-editor]');
        const content = promptElement.querySelector('[data-editor-content]');

        if (
            !isPolarity(polarity) ||
            !(editor instanceof HTMLElement) ||
            !(content instanceof HTMLTextAreaElement)
        ) {
            return;
        }

        editingPrompt = true;
        setInputActionsDisabled(true);
        editor.hidden = false;
        content.value = savedPrompts[polarity];
        content.focus();
    };

    const loadDefaultPrompts = async () => {
        const url = page.dataset.defaultPromptsUrl;

        if (!url) {
            setLoadStatus('読み込み先が設定されていません。', true);
            return;
        }

        promptsLoaded = false;
        setInputActionsDisabled(true);
        setLoadStatus('デフォルト文面を読み込んでいます。');

        try {
            const prompts = await requestDefaultPrompts(fetcher, url);

            savedPrompts.positive = prompts.positive;
            savedPrompts.negative = prompts.negative;
            promptsLoaded = true;
            setInputActionsDisabled(editingPrompt);
            setLoadStatus('');
        } catch {
            setLoadStatus('デフォルト文面を読み込めませんでした。再度お試しください。', true);
        }
    };

    const saveDefaultPrompt = async (promptElement) => {
        const polarity = promptElement.dataset.defaultPrompt;
        const url = promptElement.dataset.updateUrl;
        const content = promptElement.querySelector('[data-editor-content]');
        const status = promptElement.querySelector('[data-editor-status]');
        const saveButton = promptElement.querySelector('[data-save]');
        const cancelButton = promptElement.querySelector('[data-cancel]');

        if (
            !isPolarity(polarity) ||
            !url ||
            !csrfToken ||
            !(content instanceof HTMLTextAreaElement) ||
            !(status instanceof HTMLElement) ||
            !(saveButton instanceof HTMLButtonElement) ||
            !(cancelButton instanceof HTMLButtonElement)
        ) {
            return;
        }

        saveButton.disabled = true;
        cancelButton.disabled = true;
        status.textContent = '保存しています。';
        status.dataset.state = 'pending';

        try {
            const result = await requestSaveDefaultPrompt(
                fetcher,
                url,
                csrfToken,
                polarity,
                content.value,
            );

            savedPrompts[polarity] = result.content;
            content.value = result.content;
            status.textContent = result.formatSucceeded
                ? '保存しました。'
                : '特殊記法を保護するため、入力した文面をそのまま保存しました。';
            status.dataset.state = 'success';

            scheduleTask(() => closeEditor(promptElement), 900);
        } catch {
            status.textContent =
                '保存できませんでした。入力内容を保持しています。再度お試しください。';
            status.dataset.state = 'error';
            saveButton.disabled = false;
            cancelButton.disabled = false;
        }
    };

    const displayPrompts = () => {
        const outputs = createPromptOutputs(savedPrompts, selectedPrompts);

        for (const polarity of ['positive', 'negative']) {
            const outputElement = getOutputElement(polarity);
            const content = outputElement?.querySelector('[data-output-content]');
            const status = outputElement?.querySelector('[data-copy-status]');

            if (content instanceof HTMLTextAreaElement) {
                content.value = outputs[polarity];
            }

            if (status instanceof HTMLElement) {
                status.textContent = '';
            }

            if (outputElement instanceof HTMLElement) {
                updateCopyAvailability(outputElement);
            }
        }

        outputSection?.scrollIntoView?.({ behavior: 'smooth', block: 'start' });
    };

    const copyOutput = async (outputElement) => {
        const content = outputElement.querySelector('[data-output-content]');
        const status = outputElement.querySelector('[data-copy-status]');
        const copyButton = outputElement.querySelector('[data-copy]');

        if (
            !(content instanceof HTMLTextAreaElement) ||
            !(status instanceof HTMLElement) ||
            !(copyButton instanceof HTMLButtonElement)
        ) {
            return;
        }

        copyButton.disabled = true;
        status.textContent = 'コピーしています。';
        status.dataset.state = 'pending';

        try {
            await writePromptToClipboard(clipboard.writeText.bind(clipboard), content.value);
            status.textContent = '';
            status.dataset.state = 'success';
            showToast(`${outputElement.dataset.output}をコピーしました。`);
        } catch {
            status.textContent = 'コピーできませんでした。文面を選択して手動でコピーしてください。';
            status.dataset.state = 'error';
            content.focus();
            content.select();
        } finally {
            updateCopyAvailability(outputElement);
        }
    };

    promptElements.forEach((promptElement) => {
        const polarity = promptElement.dataset.defaultPrompt;

        if (!isPolarity(polarity)) {
            return;
        }

        promptElement.querySelector('[data-select]')?.addEventListener('click', () => {
            selectedPrompts[polarity] = !selectedPrompts[polarity];
            updateSelection(promptElement, polarity);
        });
        promptElement.querySelector('[data-edit]')?.addEventListener('click', () => {
            openEditor(promptElement);
        });
        promptElement.querySelector('[data-cancel]')?.addEventListener('click', () => {
            closeEditor(promptElement);
        });
        promptElement.querySelector('[data-save]')?.addEventListener('click', () => {
            saveDefaultPrompt(promptElement);
        });
    });

    outputElements.forEach((outputElement) => {
        outputElement.querySelector('[data-output-content]')?.addEventListener('input', () => {
            const status = outputElement.querySelector('[data-copy-status]');

            if (status instanceof HTMLElement) {
                status.textContent = '';
            }

            updateCopyAvailability(outputElement);
        });
        outputElement.querySelector('[data-copy]')?.addEventListener('click', () => {
            copyOutput(outputElement);
        });
    });

    retryButton?.addEventListener('click', loadDefaultPrompts);
    displayButton?.addEventListener('click', displayPrompts);
    void loadDefaultPrompts();
};

if (typeof document !== 'undefined') {
    initializePromptPreparationPage();
}
