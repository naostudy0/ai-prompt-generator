const requestJson = async (fetcher, url, csrfToken, body) => {
    const response = await fetcher(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify(body),
    });
    let result = null;
    try {
        result = await response.json();
    } catch {
        // Reverse proxies and framework error pages may return HTML instead of JSON.
    }
    if (!response.ok) {
        const status = Number.isInteger(response.status) ? `（HTTP ${response.status}）` : '';
        throw new Error(result?.message ?? `ランダム案を作成できませんでした${status}。`);
    }
    if (result === null || typeof result !== 'object' || Array.isArray(result)) {
        throw new Error('ランダム案のサーバー応答形式が不正です。');
    }
    return result;
};

export const initializeRandomIllustrationIdea = ({
    page,
    documentObject,
    fetcher,
    csrfToken,
    captureFixedSelections,
    applyResolved,
    getSavableGroups,
    savePositiveSuggestion,
}) => {
    const dialog = page.querySelector('[data-random-idea-dialog]');
    const openButton = page.querySelector('[data-random-idea-open]');
    if (!dialog || !openButton) {
        return { close: () => {} };
    }
    const description = page.querySelector('[data-random-idea-description]');
    const status = page.querySelector('[data-random-idea-status]');
    const preview = page.querySelector('[data-random-idea-preview]');
    const fixedList = page.querySelector('[data-random-idea-fixed]');
    const anchor = page.querySelector('[data-random-idea-anchor]');
    const suggestions = page.querySelector('[data-random-idea-suggestions]');
    const generate = page.querySelector('[data-random-idea-generate]');
    const resolve = page.querySelector('[data-random-idea-resolve]');
    let captured = null;
    let ideaId = null;
    let token = 0;
    let busy = false;

    const setBusy = (value) => {
        busy = value;
        generate.disabled = value;
        resolve.disabled = value;
    };
    const createIdea = async () => {
        if (busy) {
            return;
        }
        const requestToken = ++token;
        setBusy(true);
        status.textContent = 'イラスト案を考えています…';
        try {
            const result = await requestJson(
                fetcher,
                page.dataset.randomIllustrationIdeasUrl,
                csrfToken,
                { fixedSelections: captured },
            );
            if (requestToken !== token || !dialog.open) {
                return;
            }
            ideaId = result.ideaId;
            description.value = result.description;
            fixedList.replaceChildren(
                ...result.fixedSelectionSummaries.map((item) => {
                    const li = documentObject.createElement('li');
                    li.textContent = item.label;
                    return li;
                }),
            );
            anchor.textContent = result.locationAnchor?.label ?? 'AIが説明に合う場所を補います';
            preview.hidden = false;
            status.textContent = '説明を書き換えてから解析できます。';
        } catch (error) {
            if (requestToken === token) {
                status.textContent = error.message;
            }
        } finally {
            if (requestToken === token) {
                setBusy(false);
            }
        }
    };
    openButton.addEventListener('click', () => {
        captured = captureFixedSelections();
        ideaId = null;
        preview.hidden = true;
        suggestions.replaceChildren();
        status.textContent = '';
        dialog.showModal();
        void createIdea();
    });
    generate.addEventListener('click', createIdea);
    resolve.addEventListener('click', async () => {
        if (busy || !ideaId || description.value.trim() === '') {
            return;
        }
        const requestToken = ++token;
        setBusy(true);
        status.textContent = '編集内容を登録項目へ対応付けています…';
        try {
            const result = await requestJson(
                fetcher,
                page.dataset.randomIllustrationIdeasResolveUrl,
                csrfToken,
                { ideaId, description: description.value, fixedSelections: captured },
            );
            if (requestToken !== token || !dialog.open) {
                return;
            }
            description.value = result.resolvedDescription;
            suggestions.replaceChildren(
                ...result.suggestions.map((item) => {
                    const row = documentObject.createElement('div');
                    const text = documentObject.createElement('p');
                    text.textContent = `${item.polarity === 'positive' ? '追加' : '除外'}: ${item.label} — ${item.tags}`;
                    row.append(text);
                    if (item.polarity === 'positive') {
                        const select = documentObject.createElement('select');
                        select.className = 'text-input';
                        for (const group of getSavableGroups()) {
                            const option = documentObject.createElement('option');
                            option.value = String(group.id);
                            option.textContent = group.name;
                            select.append(option);
                        }
                        const save = documentObject.createElement('button');
                        save.type = 'button';
                        save.className = 'small-button';
                        save.textContent = '通常項目として保存';
                        save.disabled = select.options.length === 0;
                        save.addEventListener('click', async () => {
                            save.disabled = true;
                            try {
                                await savePositiveSuggestion(Number(select.value), item);
                                save.textContent = '保存済み';
                            } catch {
                                save.disabled = false;
                                status.textContent = '候補を保存できませんでした。';
                            }
                        });
                        row.append(select, save);
                    }
                    return row;
                }),
            );
            if (!applyResolved(result)) {
                throw new Error('登録内容が変わったため反映できません。もう一度作成してください。');
            }
            status.textContent = result.adjustments.length
                ? result.adjustments.join(' / ')
                : 'ランダム案を出力へ反映しました。';
        } catch (error) {
            if (requestToken === token) {
                status.textContent = error.message;
            }
        } finally {
            if (requestToken === token) {
                setBusy(false);
            }
        }
    });
    page.querySelector('[data-random-idea-close]').addEventListener('click', () => {
        ++token;
        dialog.close();
    });
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog && !busy) {
            ++token;
            dialog.close();
        }
    });
    return { close: () => dialog.close() };
};
