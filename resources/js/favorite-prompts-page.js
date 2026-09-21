const requestJson = async (fetcher, url, options = {}) => {
    const response = await fetcher(url, {
        headers: { Accept: 'application/json', ...(options.headers ?? {}) },
        ...options,
    });
    if (!response.ok) {
        throw new Error('Request failed.');
    }
    return response.status === 204 ? null : response.json();
};

const appendSummary = (documentObject, target, groups) => {
    target.replaceChildren();
    groups.forEach((group) => {
        const section = documentObject.createElement('section');
        const heading = documentObject.createElement('h3');
        heading.textContent = group.label;
        const values = documentObject.createElement('p');
        values.textContent = group.items
            .map((item) => [item.label, item.meta, ...item.details].filter(Boolean).join('・'))
            .join('、');
        section.append(heading, values);
        target.append(section);
    });
};

export const initializeFavoritePromptsPage = ({
    page,
    documentObject,
    fetcher,
    csrfToken,
    capture,
    restore,
    notify,
}) => {
    const view = documentObject.defaultView;
    const baseUrl = page.dataset.favoritePromptsUrl;
    const saveButton = page.querySelector('[data-favorite-save]');
    const openButton = page.querySelector('[data-favorite-open]');
    const formDialog = page.querySelector('[data-favorite-form-dialog]');
    const form = page.querySelector('[data-favorite-form]');
    const formTitle = page.querySelector('[data-favorite-form-title]');
    const nameInput = page.querySelector('[data-favorite-name]');
    const imageInput = page.querySelector('[data-favorite-image]');
    const removeField = page.querySelector('[data-favorite-remove-field]');
    const removeImage = page.querySelector('[data-favorite-remove-image]');
    const preview = page.querySelector('[data-favorite-selection-preview]');
    const formStatus = page.querySelector('[data-favorite-form-status]');
    const saveCopyButton = page.querySelector('[data-favorite-save-copy]');
    const listDialog = page.querySelector('[data-favorite-list-dialog]');
    const list = page.querySelector('[data-favorite-list]');
    const listStatus = page.querySelector('[data-favorite-list-status]');
    const retryButton = page.querySelector('[data-favorite-list-retry]');
    let currentFavorite = null;
    let saving = false;

    if (!view || !baseUrl || !(formDialog instanceof view.HTMLDialogElement)) {
        return;
    }

    const close = (dialog) => dialog instanceof view.HTMLDialogElement && dialog.close();
    const openSave = () => {
        const state = capture();
        if (state.positivePrompt === '' && state.negativePrompt === '') {
            notify('保存するプロンプトを作成してください。');
            return;
        }
        form.reset();
        nameInput.value = currentFavorite?.name ?? '';
        formTitle.textContent = currentFavorite === null ? 'お気に入りへ保存' : 'お気に入りを更新';
        saveCopyButton.hidden = currentFavorite === null;
        removeField.hidden =
            currentFavorite?.imageUrl === null || currentFavorite?.imageUrl === undefined;
        formStatus.textContent = '';
        appendSummary(documentObject, preview, state.selectionSummary);
        formDialog.showModal();
    };

    const save = async (asCopy) => {
        if (saving) {
            return;
        }
        const state = capture();
        const data = new FormData();
        data.append('name', nameInput.value);
        data.append('positivePrompt', state.positivePrompt);
        data.append('negativePrompt', state.negativePrompt);
        data.append('selectionSnapshot', JSON.stringify(state.selectionSnapshot));
        data.append('selectionSummary', JSON.stringify(state.selectionSummary));
        if (imageInput.files[0] !== undefined) {
            data.append('image', imageInput.files[0]);
        }
        if (removeImage.checked) {
            data.append('removeImage', '1');
        }
        const updateId = asCopy ? null : currentFavorite?.id;
        if (updateId !== undefined && updateId !== null) {
            data.append('_method', 'PUT');
        }
        saving = true;
        formStatus.textContent = '保存しています。';
        try {
            const result = await requestJson(
                fetcher,
                updateId === null || updateId === undefined ? baseUrl : `${baseUrl}/${updateId}`,
                { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken }, body: data },
            );
            currentFavorite = result;
            close(formDialog);
            notify(asCopy ? '別のお気に入りとして保存しました。' : 'お気に入りを保存しました。');
        } catch {
            formStatus.textContent = '保存できませんでした。入力内容を確認してください。';
        } finally {
            saving = false;
        }
    };

    const loadFavorites = async () => {
        list.replaceChildren();
        listStatus.textContent = '読み込んでいます。';
        retryButton.hidden = true;
        try {
            const result = await requestJson(fetcher, baseUrl);
            listStatus.textContent =
                result.favorites.length === 0 ? 'お気に入りはまだありません。' : '';
            result.favorites.forEach((favorite) => {
                const card = documentObject.createElement('article');
                card.className = 'favorite-card';
                if (favorite.imageUrl !== null) {
                    const image = documentObject.createElement('img');
                    image.src = favorite.imageUrl;
                    image.alt = '';
                    image.loading = 'lazy';
                    card.append(image);
                } else {
                    const placeholder = documentObject.createElement('div');
                    placeholder.className = 'favorite-card__placeholder';
                    placeholder.textContent = '画像未登録';
                    card.append(placeholder);
                }
                const heading = documentObject.createElement('h3');
                heading.textContent = favorite.displayName;
                const summary = documentObject.createElement('div');
                summary.className = 'favorite-card__summary';
                appendSummary(documentObject, summary, favorite.selectionSummary);
                const actions = documentObject.createElement('div');
                actions.className = 'editor-actions';
                const useButton = documentObject.createElement('button');
                useButton.type = 'button';
                useButton.className = 'primary-button';
                useButton.textContent = '呼び出して編集';
                useButton.addEventListener('click', async () => {
                    try {
                        const detail = await requestJson(fetcher, `${baseUrl}/${favorite.id}`);
                        const result = restore(detail);
                        const unavailableLabels = result.unavailableLabels ?? [];
                        currentFavorite = detail;
                        close(listDialog);
                        notify(
                            result.complete && result.matches
                                ? 'お気に入りを呼び出しました。'
                                : `選択項目または登録内容が変わったため、一部を選択状態へ反映できませんでした。${unavailableLabels.length > 0 ? `反映できなかった項目：${unavailableLabels.join('、')}。` : ''}保存済みのプロンプトはそのまま表示しています。`,
                        );
                    } catch {
                        listStatus.textContent = 'お気に入りを呼び出せませんでした。';
                    }
                });
                const deleteButton = documentObject.createElement('button');
                deleteButton.type = 'button';
                deleteButton.className = 'danger-button';
                deleteButton.textContent = '削除';
                deleteButton.addEventListener('click', async () => {
                    try {
                        await requestJson(fetcher, `${baseUrl}/${favorite.id}`, {
                            method: 'DELETE',
                            headers: { 'X-CSRF-TOKEN': csrfToken },
                        });
                        if (currentFavorite?.id === favorite.id) {
                            currentFavorite = null;
                        }
                        await loadFavorites();
                    } catch {
                        listStatus.textContent = 'お気に入りを削除できませんでした。';
                    }
                });
                actions.append(useButton, deleteButton);
                const createdAt = documentObject.createElement('time');
                createdAt.textContent = new Intl.DateTimeFormat('ja-JP', {
                    dateStyle: 'medium',
                    timeStyle: 'short',
                }).format(new Date(favorite.createdAt));
                const details = documentObject.createElement('details');
                const detailsSummary = documentObject.createElement('summary');
                detailsSummary.textContent = 'プロンプトの詳細';
                const promptText = documentObject.createElement('pre');
                promptText.textContent = '読み込み中です。';
                details.addEventListener('toggle', async () => {
                    if (!details.open || details.dataset.loaded === 'true') {
                        return;
                    }
                    try {
                        const detail = await requestJson(fetcher, `${baseUrl}/${favorite.id}`);
                        promptText.textContent = `positive\n${detail.positivePrompt}\n\nnegative\n${detail.negativePrompt}`;
                        details.dataset.loaded = 'true';
                    } catch {
                        promptText.textContent = '詳細を読み込めませんでした。';
                    }
                });
                details.append(detailsSummary, promptText);
                card.append(heading, createdAt, summary, details, actions);
                list.append(card);
            });
        } catch {
            listStatus.textContent = 'お気に入りを読み込めませんでした。';
            retryButton.hidden = false;
        }
    };

    saveButton?.addEventListener('click', openSave);
    openButton?.addEventListener('click', () => {
        listDialog.showModal();
        void loadFavorites();
    });
    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        void save(false);
    });
    saveCopyButton?.addEventListener('click', () => void save(true));
    page.querySelector('[data-favorite-form-cancel]')?.addEventListener('click', () =>
        close(formDialog),
    );
    page.querySelector('[data-favorite-list-close]')?.addEventListener('click', () =>
        close(listDialog),
    );
    retryButton?.addEventListener('click', () => void loadFavorites());
    for (const dialog of [formDialog, listDialog]) {
        dialog?.addEventListener('click', (event) => {
            if (event.target === dialog && !saving) {
                close(dialog);
            }
        });
    }
};
