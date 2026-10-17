export const PROMPT_CLIPS_STORAGE_KEY = 'ai-prompt-generator.prompt-clips.v1';

const isPromptClip = (value) =>
    typeof value === 'object' &&
    value !== null &&
    typeof value.id === 'string' &&
    value.id !== '' &&
    typeof value.name === 'string' &&
    value.name !== '' &&
    typeof value.positivePrompt === 'string' &&
    typeof value.negativePrompt === 'string' &&
    (value.variantSource === undefined ||
        (typeof value.variantSource === 'object' && value.variantSource !== null));

export const parsePromptClips = (value) => {
    const parsed = JSON.parse(value);

    if (!Array.isArray(parsed) || !parsed.every(isPromptClip)) {
        throw new Error('保存されたクリップの形式が不正です。');
    }

    return parsed;
};

export const initializePromptClips = ({
    page,
    documentObject,
    storage,
    getOutputs,
    restoreOutputs,
    createId = () => globalThis.crypto.randomUUID(),
}) => {
    const view = documentObject.defaultView;
    const section = page.querySelector('[data-prompt-clips]');

    if (!view || !(section instanceof view.HTMLElement)) {
        return;
    }

    const form = section.querySelector('[data-prompt-clip-form]');
    const nameInput = section.querySelector('[data-prompt-clip-name]');
    const list = section.querySelector('[data-prompt-clip-list]');
    const empty = section.querySelector('[data-prompt-clip-empty]');
    const status = section.querySelector('[data-prompt-clip-status]');
    let clips = [];

    const setStatus = (message, failed = false) => {
        if (!(status instanceof view.HTMLElement)) {
            return;
        }

        status.textContent = message;
        status.dataset.state = failed ? 'error' : 'ready';
    };

    const suggestName = () => {
        if (nameInput instanceof view.HTMLInputElement) {
            nameInput.value = `クリップ ${clips.length + 1}`;
        }
    };

    const persist = (nextClips) => {
        if (!storage) {
            throw new Error('一時保存を利用できません。');
        }

        storage.setItem(PROMPT_CLIPS_STORAGE_KEY, JSON.stringify(nextClips));
    };

    const render = () => {
        if (!(list instanceof view.HTMLElement)) {
            return;
        }

        list.replaceChildren(
            ...clips.map((clip) => {
                const item = documentObject.createElement('li');
                item.className = 'prompt-clip';

                const name = documentObject.createElement('span');
                name.className = 'prompt-clip__name';
                name.textContent = clip.name;

                const actions = documentObject.createElement('span');
                actions.className = 'prompt-clip__actions';

                const restoreButton = documentObject.createElement('button');
                restoreButton.type = 'button';
                restoreButton.className = 'prompt-clip__restore';
                restoreButton.textContent = '呼び出す';
                restoreButton.addEventListener('click', () => {
                    restoreOutputs(clip);
                    setStatus(`「${clip.name}」を呼び出しました。`);
                });

                const deleteButton = documentObject.createElement('button');
                deleteButton.type = 'button';
                deleteButton.className = 'prompt-clip__delete';
                deleteButton.textContent = '削除';
                deleteButton.setAttribute('aria-label', `${clip.name}を削除`);
                deleteButton.addEventListener('click', () => {
                    const nextClips = clips.filter((candidate) => candidate.id !== clip.id);
                    try {
                        persist(nextClips);
                        clips = nextClips;
                        render();
                        suggestName();
                        setStatus(`「${clip.name}」を削除しました。`);
                    } catch {
                        setStatus('クリップを削除できませんでした。', true);
                    }
                });

                actions.append(restoreButton, deleteButton);
                item.append(name, actions);
                return item;
            }),
        );

        if (empty instanceof view.HTMLElement) {
            empty.hidden = clips.length > 0;
        }
    };

    try {
        const saved = storage?.getItem(PROMPT_CLIPS_STORAGE_KEY);
        clips = saved === null || saved === undefined ? [] : parsePromptClips(saved);
    } catch {
        clips = [];
        setStatus('保存済みのクリップを読み込めませんでした。', true);
    }

    render();
    suggestName();

    form?.addEventListener('submit', (event) => {
        event.preventDefault();

        if (!(nameInput instanceof view.HTMLInputElement)) {
            return;
        }

        const name = nameInput.value.trim();
        const outputs = getOutputs();

        if (name === '' || name.length > 100) {
            setStatus('名前は1文字以上100文字以内で入力してください。', true);
            return;
        }
        if (outputs.positive === '' && outputs.negative === '') {
            setStatus('positiveまたはnegativeの出力が必要です。', true);
            return;
        }

        try {
            const nextClips = [
                ...clips,
                {
                    id: createId(),
                    name,
                    positivePrompt: outputs.positive,
                    negativePrompt: outputs.negative,
                    ...(outputs.variantSource ? { variantSource: outputs.variantSource } : {}),
                },
            ];
            persist(nextClips);
            clips = nextClips;
            render();
            suggestName();
            setStatus(`「${name}」を保存しました。`);
        } catch {
            setStatus('クリップを保存できませんでした。', true);
        }
    });
};
