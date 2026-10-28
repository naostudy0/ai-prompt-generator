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
    storage,
    confirmAction = documentObject.defaultView?.confirm.bind(documentObject.defaultView),
    getCharacterLoraApplication = () => null,
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
    const groupName = page.querySelector('[data-favorite-group-name]');
    const groupList = page.querySelector('[data-favorite-group-list]');
    const selectedCount = page.querySelector('[data-favorite-selected-count]');
    const loraSummary = page.querySelector('[data-favorite-lora-summary]');
    const batchButton = page.querySelector('[data-favorite-batch-send]');
    let currentFavorite = null;
    let saving = false;
    let processing = false;
    let favorites = [];
    let results = new Map();
    let checkedKeys;
    let groups;
    try {
        const resolvedStorage = storage ?? documentObject.defaultView?.localStorage;
        storage = resolvedStorage;
        checkedKeys = parseFavoritePromptChecks(
            resolvedStorage?.getItem('favorite-prompt-checks-v1'),
        );
        groups = parseFavoritePromptGroups(resolvedStorage?.getItem('favorite-prompt-groups-v1'));
    } catch {
        checkedKeys = new Set();
        groups = [];
    }

    if (!view || !baseUrl || !(formDialog instanceof view.HTMLDialogElement)) {
        return;
    }

    const close = (dialog) => dialog instanceof view.HTMLDialogElement && dialog.close();
    const writeChecks = () => {
        try {
            storage?.setItem(
                'favorite-prompt-checks-v1',
                serializeFavoritePromptChecks(checkedKeys),
            );
        } catch {
            listStatus.textContent =
                'チェック状態を保存できませんでした。現在の選択はこの画面で保持します。';
        }
    };
    const writeGroups = (nextGroups) => {
        try {
            storage?.setItem(
                'favorite-prompt-groups-v1',
                serializeFavoritePromptGroups(nextGroups),
            );
            groups = nextGroups;
            return true;
        } catch {
            listStatus.textContent = 'グループを保存できませんでした。';
            return false;
        }
    };
    const renderGroups = () => {
        if (!groupList) return;
        groupList.replaceChildren();
        groups.forEach((group, index) => {
            const option = documentObject.createElement('option');
            option.value = String(index);
            option.textContent = group.name;
            groupList.append(option);
        });
    };
    const updateSelection = () => {
        list.querySelectorAll('[data-favorite-check]').forEach((checkbox) => {
            checkbox.checked = checkedKeys.has(checkbox.dataset.favoriteCheck);
            checkbox
                .closest('.favorite-card')
                ?.classList.toggle('favorite-card--selected', checkbox.checked);
        });
        if (selectedCount) selectedCount.textContent = `${checkedKeys.size}件選択`;
        if (batchButton) batchButton.disabled = processing || checkedKeys.size === 0;
        writeChecks();
    };
    const setProcessing = (value) => {
        processing = value;
        listDialog.querySelectorAll('button,input,select').forEach((control) => {
            control.disabled = value;
        });
        updateSelection();
    };
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
            favorites = result.favorites;
            const availableKeys = new Set(
                favorites.map((favorite) => createFavoritePromptKey(favorite.id)),
            );
            const reconciled = resolveFavoritePromptGroup([...checkedKeys], availableKeys);
            checkedKeys = reconciled.selectedKeys;
            listStatus.textContent =
                reconciled.unavailableCount > 0
                    ? `${reconciled.unavailableCount}件の削除済みお気に入りを選択から除外しました。`
                    : result.favorites.length === 0
                      ? 'お気に入りはまだありません。'
                      : '';
            result.favorites.forEach((favorite) => {
                const card = documentObject.createElement('article');
                card.className = 'favorite-card';
                const key = createFavoritePromptKey(favorite.id);
                const selectLabel = documentObject.createElement('label');
                selectLabel.className = 'favorite-card__check';
                const checkbox = documentObject.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.dataset.favoriteCheck = key;
                checkbox.checked = checkedKeys.has(key);
                checkbox.addEventListener('change', () => {
                    checkbox.checked ? checkedKeys.add(key) : checkedKeys.delete(key);
                    updateSelection();
                });
                selectLabel.append(checkbox, documentObject.createTextNode(' 一括送信の対象'));
                card.append(selectLabel);
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
                const batchResult = documentObject.createElement('p');
                batchResult.dataset.favoriteResult = key;
                batchResult.className = 'favorite-card__result';
                batchResult.textContent = results.get(key) ?? '';
                card.append(heading, createdAt, summary, details, actions, batchResult);
                list.append(card);
            });
            updateSelection();
        } catch {
            listStatus.textContent = 'お気に入りを読み込めませんでした。';
            retryButton.hidden = false;
        }
    };

    saveButton?.addEventListener('click', openSave);
    openButton?.addEventListener('click', () => {
        const application = getCharacterLoraApplication();
        if (loraSummary) {
            loraSummary.textContent =
                application?.summary ??
                '人物LoRAが未選択です。送信前に適用する人物LoRAを選択してください。';
        }
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
    page.querySelector('[data-favorite-select-all]')?.addEventListener('click', () => {
        checkedKeys = new Set(favorites.map((favorite) => createFavoritePromptKey(favorite.id)));
        updateSelection();
    });
    page.querySelector('[data-favorite-clear-all]')?.addEventListener('click', () => {
        checkedKeys = new Set();
        updateSelection();
    });
    page.querySelector('[data-favorite-group-save]')?.addEventListener('click', () => {
        try {
            const group = prepareFavoritePromptGroup(groupName?.value ?? '', checkedKeys);
            const index = findFavoritePromptGroupIndex(groups, group.name);
            if (index >= 0 && !confirmAction?.(`「${groups[index].name}」を上書きしますか？`))
                return;
            if (writeGroups(replaceFavoritePromptGroup(groups, group, index))) renderGroups();
        } catch (error) {
            listStatus.textContent =
                error instanceof Error ? error.message : 'グループを保存できませんでした。';
        }
    });
    page.querySelector('[data-favorite-group-load]')?.addEventListener('click', () => {
        const group = groups[Number(groupList?.value ?? -1)];
        if (!group) return;
        const available = new Set(
            favorites.map((favorite) => createFavoritePromptKey(favorite.id)),
        );
        const resolved = resolveFavoritePromptGroup(group.favoriteKeys, available);
        checkedKeys = resolved.selectedKeys;
        updateSelection();
        listStatus.textContent =
            resolved.unavailableCount > 0
                ? `${resolved.unavailableCount}件は削除済みのため選択できませんでした。`
                : `「${group.name}」を呼び出しました。`;
    });
    page.querySelector('[data-favorite-group-delete]')?.addEventListener('click', () => {
        const index = Number(groupList?.value ?? -1);
        if (!groups[index] || !confirmAction?.(`「${groups[index].name}」を削除しますか？`)) return;
        if (writeGroups(removeFavoritePromptGroup(groups, index))) renderGroups();
    });
    batchButton?.addEventListener('click', async () => {
        const application = getCharacterLoraApplication();
        if (!application) {
            listStatus.textContent = '適用する人物LoRAを選択してください。';
            return;
        }
        const selected = favorites.filter((favorite) =>
            checkedKeys.has(createFavoritePromptKey(favorite.id)),
        );
        setProcessing(true);
        const items = [];
        const failures = [];
        try {
            for (const favorite of selected) {
                const key = createFavoritePromptKey(favorite.id);
                try {
                    const detail = await requestJson(fetcher, `${baseUrl}/${favorite.id}`);
                    items.push(
                        prepareFavoriteCharacterLoraQueueItem({
                            favorite: detail,
                            key,
                            ...application,
                        }),
                    );
                } catch (error) {
                    failures.push({
                        key,
                        message: error instanceof Error ? error.message : '準備できませんでした。',
                    });
                }
            }
            if (items.length === 0) {
                failures.forEach(({ key, message }) => results.set(key, `失敗：${message}`));
                listStatus.textContent = `送信できるお気に入りがありません。${failures.length}件を除外しました。`;
                await loadFavorites();
                return;
            }
            if (items.length > 100) {
                listStatus.textContent = '一度に送信できるお気に入りは100件までです。';
                return;
            }
            const summary =
                application.summary ??
                `人物LoRA ID ${application.selection.loraId}・強度${application.selection.strength}`;
            if (
                !confirmAction?.(
                    `${summary}\n${items.length}件を送信し、${failures.length}件を除外します。`,
                )
            )
                return;
            const response = await fetcher(page.dataset.comfyUiPromptBatchUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ items }),
            });
            const body = await response.json();
            if (!response.ok || !Array.isArray(body.results))
                throw new Error('一括送信結果を確認できませんでした。');
            body.results.forEach((result) =>
                results.set(
                    result.candidateKey,
                    result.status === 'accepted'
                        ? `送信済み（ID: ${result.promptId}）`
                        : `失敗：${result.message ?? '送信できませんでした。'}`,
                ),
            );
            const returnedKeys = new Set(body.results.map((result) => result.candidateKey));
            items
                .filter((item) => !returnedKeys.has(item.candidateKey))
                .forEach((item) =>
                    results.set(item.candidateKey, '結果不明：ComfyUIのキューを確認してください。'),
                );
            failures.forEach(({ key, message }) => results.set(key, `失敗：${message}`));
            await loadFavorites();
            const accepted = body.results.filter((result) => result.status === 'accepted').length;
            listStatus.textContent = `${accepted}件をキューへ追加、${body.results.length - accepted + failures.length}件が失敗しました。`;
        } catch (error) {
            listStatus.textContent = `${error instanceof Error ? error.message : '一括送信できませんでした。'} 一部が受理済みの可能性があるため、自動再送せずComfyUIを確認してください。`;
        } finally {
            setProcessing(false);
        }
    });
    renderGroups();
    for (const dialog of [formDialog, listDialog]) {
        dialog?.addEventListener('click', (event) => {
            if (event.target === dialog && !saving && !processing) {
                close(dialog);
            }
        });
    }
};
import { prepareFavoriteCharacterLoraQueueItem } from './favorite-character-lora-application.js';
import {
    createFavoritePromptKey,
    findFavoritePromptGroupIndex,
    parseFavoritePromptChecks,
    parseFavoritePromptGroups,
    prepareFavoritePromptGroup,
    removeFavoritePromptGroup,
    replaceFavoritePromptGroup,
    resolveFavoritePromptGroup,
    serializeFavoritePromptChecks,
    serializeFavoritePromptGroups,
} from './favorite-prompt-groups.js';
