const isGenerationResult = (value) =>
    value !== null &&
    typeof value === 'object' &&
    typeof value.positivePrompt === 'string' &&
    typeof value.negativePrompt === 'string' &&
    typeof value.model === 'string' &&
    typeof value.promptStyle === 'string' &&
    typeof value.generationTimeMs === 'number';

const responseJson = async (response) => {
    try {
        return await response.json();
    } catch {
        return null;
    }
};

export const initializeIllustrationPromptGenerator = ({
    page,
    documentObject,
    fetcher,
    csrfToken,
    applyPrompt,
}) => {
    const dialog = page.querySelector('[data-illustration-prompt-dialog]');
    const openButton = page.querySelector('[data-illustration-prompt-open]');
    const closeButton = page.querySelector('[data-illustration-prompt-close]');
    const description = page.querySelector('[data-illustration-prompt-description]');
    const style = page.querySelector('[data-illustration-prompt-style]');
    const generateButton = page.querySelector('[data-illustration-prompt-generate]');
    const status = page.querySelector('[data-illustration-prompt-status]');
    const preview = page.querySelector('[data-illustration-prompt-preview]');
    const positive = page.querySelector('[data-illustration-prompt-positive]');
    const negative = page.querySelector('[data-illustration-prompt-negative]');
    const metadata = page.querySelector('[data-illustration-prompt-metadata]');
    const applyButton = page.querySelector('[data-illustration-prompt-apply]');
    let requestController = null;
    let requestToken = 0;
    let pending = false;

    if (
        !documentObject.defaultView ||
        !dialog ||
        !openButton ||
        !closeButton ||
        !description ||
        !style ||
        !generateButton ||
        !status ||
        !preview ||
        !positive ||
        !negative ||
        !metadata ||
        !applyButton
    ) {
        return { abort: () => {} };
    }

    const abort = () => {
        requestToken += 1;
        requestController?.abort();
        requestController = null;
        pending = false;
        generateButton.disabled = false;
        description.disabled = false;
        style.disabled = false;
    };
    const close = () => {
        abort();
        dialog.close();
    };
    openButton.addEventListener('click', () => {
        dialog.showModal();
        description.focus();
    });
    closeButton.addEventListener('click', (event) => {
        event.preventDefault();
        close();
    });
    dialog.addEventListener('cancel', abort);
    dialog.addEventListener('close', abort);

    generateButton.addEventListener('click', async () => {
        if (pending) {
            return;
        }
        const value = description.value.trim();
        if (value.length === 0 || value.length > 2000) {
            status.textContent =
                value.length === 0
                    ? '指示を入力してください。'
                    : '指示は2,000文字以内で入力してください。';
            return;
        }

        pending = true;
        generateButton.disabled = true;
        description.disabled = true;
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
                body: JSON.stringify({ description: value, promptStyle: style.value }),
                signal: requestController.signal,
            });
            const body = await responseJson(response);
            if (token !== requestToken) {
                return;
            }
            if (!response.ok || !isGenerationResult(body)) {
                const fieldMessage = body?.errors?.description?.[0];
                status.textContent =
                    typeof fieldMessage === 'string'
                        ? fieldMessage
                        : `${typeof body?.message === 'string' ? body.message : '生成に失敗しました。'} 外部LLMサービスの接続状態や設定を確認して再試行してください。`;
                return;
            }
            positive.value = body.positivePrompt;
            negative.value = body.negativePrompt;
            metadata.textContent = `${body.model} / ${body.generationTimeMs} ms`;
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
                description.disabled = false;
                style.disabled = false;
            }
        }
    });

    applyButton.addEventListener('click', () => {
        if (preview.hidden || positive.value.trim() === '') {
            status.textContent = 'positiveプロンプトを入力してください。';
            return;
        }
        applyPrompt({ positivePrompt: positive.value, negativePrompt: negative.value });
        close();
    });

    return { abort };
};
