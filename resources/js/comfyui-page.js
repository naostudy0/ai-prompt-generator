export const responseErrorMessage = (body, fallback) => {
    if (body && typeof body === 'object') {
        if (body.errors && typeof body.errors === 'object') {
            const message = Object.values(body.errors)
                .flat()
                .find((item) => typeof item === 'string');
            if (message) {
                return message;
            }
        }
        if (typeof body.message === 'string' && body.message !== '') {
            return body.message;
        }
    }

    return fallback;
};

export const initializeComfyUiPage = ({
    page,
    documentObject,
    fetcher,
    csrfToken,
    getModelFamilyId,
    getLoraId = () => null,
    notify,
}) => {
    const view = documentObject.defaultView;
    const sendButton = page.querySelector('[data-comfyui-send]');
    const status = page.querySelector('[data-comfyui-send-status]');
    if (!view || !(sendButton instanceof view.HTMLButtonElement)) {
        return;
    }
    sendButton.addEventListener('click', async () => {
        sendButton.disabled = true;
        if (status instanceof view.HTMLElement) {
            status.textContent = 'ComfyUIへ送信しています。';
        }
        try {
            const output = (polarity) =>
                page.querySelector(`[data-output="${polarity}"] [data-output-content]`)?.value ??
                '';
            const response = await fetcher(page.dataset.comfyUiPromptsUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    modelFamilyId: getModelFamilyId(),
                    loraId: getLoraId(),
                    positive: output('positive'),
                    negative: output('negative'),
                }),
            });
            const body = await response.json();
            if (!response.ok || typeof body.promptId !== 'string') {
                throw new Error(responseErrorMessage(body, 'ComfyUIへ送信できませんでした。'));
            }
            if (status instanceof view.HTMLElement) {
                status.textContent = `キューへ追加しました（ID: ${body.promptId}）。`;
            }
            notify('ComfyUIのキューへ追加しました。');
        } catch (error) {
            if (status instanceof view.HTMLElement) {
                status.textContent =
                    error instanceof Error ? error.message : 'ComfyUIへ送信できませんでした。';
            }
        } finally {
            sendButton.disabled = false;
        }
    });
};

export const initializeComfyUiWorkflowPage = ({ page, documentObject, fetcher, csrfToken }) => {
    const view = documentObject.defaultView;
    const familySelect = page.querySelector('[data-family-list]');
    const file = page.querySelector('[data-comfyui-workflow-file]');
    const status = page.querySelector('[data-comfyui-workflow-status]');
    const save = page.querySelector('[data-comfyui-workflow-save]');
    const remove = page.querySelector('[data-comfyui-workflow-delete]');
    if (!view || !(familySelect instanceof view.HTMLSelectElement)) {
        return { refresh: async () => {} };
    }
    const mappingFields = (role) => ({
        nodeId: page.querySelector(`[data-comfyui-${role}-node]`),
        inputName: page.querySelector(`[data-comfyui-${role}-input]`),
    });
    const listRoles = ['checkpoint', 'sampler', 'scheduler', 'outputFilenamePrefix'];
    const mappingRows = (role) => [
        ...page.querySelectorAll(
            `[data-comfyui-mapping-list="${role}"] [data-comfyui-mapping-row]`,
        ),
    ];
    const renderMappingRows = (role, mappings = []) => {
        const container = page.querySelector(
            `[data-comfyui-mapping-list="${role}"] [data-comfyui-mapping-rows]`,
        );
        if (!(container instanceof view.HTMLElement)) {
            return;
        }
        const values = mappings.length === 0 && role !== 'outputFilenamePrefix' ? [{}] : mappings;
        container.replaceChildren(
            ...values.map((mapping) => {
                const row = documentObject.createElement('div');
                row.className = 'workflow-mapping-entry';
                row.setAttribute('data-comfyui-mapping-row', '');
                const nodeLabel = documentObject.createElement('label');
                nodeLabel.textContent = 'ノードID ';
                const node = documentObject.createElement('input');
                node.className = 'text-input';
                node.dataset.mappingNode = '';
                node.value = mapping.nodeId ?? '';
                nodeLabel.append(node);
                const inputLabel = documentObject.createElement('label');
                inputLabel.textContent = '入力名 ';
                const input = documentObject.createElement('input');
                input.className = 'text-input';
                input.dataset.mappingInput = '';
                input.value = mapping.inputName ?? '';
                inputLabel.append(input);
                const removeRow = documentObject.createElement('button');
                removeRow.type = 'button';
                removeRow.className = 'small-button';
                removeRow.textContent = '削除';
                removeRow.addEventListener('click', () => {
                    row.remove();
                    if (mappingRows(role).length === 0 && role !== 'outputFilenamePrefix') {
                        renderMappingRows(role);
                    }
                });
                row.append(nodeLabel, inputLabel, removeRow);
                return row;
            }),
        );
    };
    for (const role of listRoles) {
        renderMappingRows(role);
        page.querySelector(`[data-comfyui-mapping-add="${role}"]`)?.addEventListener(
            'click',
            () => {
                if (role === 'outputFilenamePrefix' && mappingRows(role).length > 0) {
                    return;
                }
                const current = mappingRows(role).map((row) => ({
                    nodeId: row.querySelector('[data-mapping-node]')?.value ?? '',
                    inputName: row.querySelector('[data-mapping-input]')?.value ?? '',
                }));
                renderMappingRows(role, [...current, {}]);
            },
        );
    }
    const workflowUrl = () =>
        `${page.dataset.modelFamiliesUrl}/${familySelect.value}/comfyui-workflow`;
    const setStatus = (message) => {
        if (status instanceof view.HTMLElement) {
            status.textContent = message;
        }
    };
    const refresh = async () => {
        try {
            const response = await fetcher(workflowUrl(), {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) {
                throw new Error();
            }
            const body = await response.json();
            for (const role of ['positive', 'negative', 'seed']) {
                const fields = mappingFields(role);
                if (fields.nodeId instanceof view.HTMLInputElement) {
                    fields.nodeId.value = body.mappings?.[role]?.nodeId ?? '';
                }
                if (fields.inputName instanceof view.HTMLInputElement) {
                    fields.inputName.value = body.mappings?.[role]?.inputName ?? '';
                }
            }
            for (const role of listRoles) {
                renderMappingRows(role, body.mappings?.[role] ?? []);
            }
            setStatus(
                body.configured ? `設定済み：${body.fileName}` : 'ワークフローは未設定です。',
            );
            if (remove instanceof view.HTMLButtonElement) {
                remove.disabled = !body.configured;
            }
        } catch {
            setStatus('ワークフロー設定を取得できませんでした。');
        }
    };
    file?.addEventListener('change', async () => {
        const selected = file.files?.[0];
        if (!selected) {
            return;
        }
        try {
            const workflow = JSON.parse(await selected.text());
            if (!workflow || typeof workflow !== 'object' || Array.isArray(workflow)) {
                throw new Error();
            }
            setStatus('JSONを読み取りました。入力位置を指定して保存してください。');
        } catch {
            setStatus('JSONを読み取れません。');
        }
    });
    save?.addEventListener('click', async () => {
        const selected = file?.files?.[0];
        if (!selected) {
            setStatus('JSONファイルを選択してください。');
            return;
        }
        const form = new FormData();
        form.append('workflow', selected);
        for (const role of ['positive', 'negative', 'seed']) {
            const fields = mappingFields(role);
            form.append(`${role}NodeId`, fields.nodeId?.value ?? '');
            form.append(`${role}InputName`, fields.inputName?.value ?? '');
        }
        for (const role of listRoles) {
            mappingRows(role).forEach((row, index) => {
                form.append(
                    `${role}Mappings[${index}][nodeId]`,
                    row.querySelector('[data-mapping-node]')?.value ?? '',
                );
                form.append(
                    `${role}Mappings[${index}][inputName]`,
                    row.querySelector('[data-mapping-input]')?.value ?? '',
                );
            });
        }
        form.append('_method', 'PUT');
        save.disabled = true;
        try {
            const response = await fetcher(workflowUrl(), {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: form,
            });
            const body = await response.json();
            if (!response.ok) {
                throw new Error(responseErrorMessage(body, 'ワークフローを保存できませんでした。'));
            }
            await refresh();
        } catch (error) {
            setStatus(error instanceof Error ? error.message : '保存できませんでした。');
        } finally {
            save.disabled = false;
        }
    });
    remove?.addEventListener('click', async () => {
        remove.disabled = true;
        try {
            const response = await fetcher(workflowUrl(), {
                method: 'DELETE',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
            });
            if (!response.ok) {
                throw new Error();
            }
            await refresh();
        } catch {
            setStatus('削除できませんでした。');
            remove.disabled = false;
        }
    });
    familySelect.addEventListener('change', () => void refresh());
    return { refresh };
};

export const initializeGenerationSettingOptionsPage = ({
    page,
    documentObject,
    fetcher,
    csrfToken,
    onChanged = () => {},
}) => {
    const view = documentObject.defaultView;
    const family = page.querySelector('[data-family-list]');
    const list = page.querySelector('[data-generation-setting-list]');
    const kind = page.querySelector('[data-generation-setting-kind]');
    const value = page.querySelector('[data-generation-setting-value]');
    const status = page.querySelector('[data-generation-setting-status]');
    const update = page.querySelector('[data-generation-setting-update]');
    const remove = page.querySelector('[data-generation-setting-delete]');
    if (
        !view ||
        !(family instanceof view.HTMLSelectElement) ||
        !(list instanceof view.HTMLSelectElement)
    ) {
        return { refresh: async () => {} };
    }
    let options = [];
    const setStatus = (message) => {
        status.textContent = message;
    };
    const refresh = async () => {
        if (!family.value) {
            return;
        }
        const response = await fetcher(
            `${page.dataset.modelFamiliesUrl}/${family.value}/generation-setting-options`,
            { headers: { Accept: 'application/json' } },
        );
        if (!response.ok) {
            setStatus('生成設定を取得できませんでした。');
            return;
        }
        options = (await response.json()).options;
        list.replaceChildren(
            ...options.map((item) => {
                const option = documentObject.createElement('option');
                option.value = String(item.id);
                option.textContent = `${item.kind}：${item.value}`;
                return option;
            }),
        );
        list.selectedIndex = -1;
        update.disabled = true;
        remove.disabled = true;
        kind.disabled = false;
        setStatus('');
    };
    const save = async (id = null) => {
        try {
            const response = await fetcher(
                id === null
                    ? `${page.dataset.modelFamiliesUrl}/${family.value}/generation-setting-options`
                    : `${page.dataset.generationSettingOptionsUrl}/${id}`,
                {
                    method: id === null ? 'POST' : 'PUT',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        modelFamilyId: Number(family.value),
                        kind: kind.value,
                        value: value.value,
                    }),
                },
            );
            const body = await response.json();
            if (!response.ok) {
                setStatus(responseErrorMessage(body, '生成設定を保存できませんでした。'));
                return;
            }
            value.value = '';
            await refresh();
            setStatus(id === null ? '生成設定を登録しました。' : '生成設定を更新しました。');
            onChanged();
        } catch {
            setStatus('通信に失敗しました。ページを再読み込みしてから、もう一度お試しください。');
        }
    };
    list.addEventListener('change', () => {
        const selected = options.find((option) => option.id === Number(list.value));
        if (!selected) {
            return;
        }
        kind.value = selected.kind;
        kind.disabled = true;
        value.value = selected.value;
        update.disabled = false;
        remove.disabled = false;
    });
    page.querySelector('[data-generation-setting-add]')?.addEventListener('click', () => {
        kind.disabled = false;
        void save();
    });
    update?.addEventListener('click', () => void save(Number(list.value)));
    remove?.addEventListener('click', async () => {
        const response = await fetcher(
            `${page.dataset.generationSettingOptionsUrl}/${list.value}`,
            {
                method: 'DELETE',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
            },
        );
        if (!response.ok) {
            const body = await response.json();
            setStatus(body.message ?? '生成設定を削除できませんでした。');
            return;
        }
        value.value = '';
        await refresh();
        onChanged();
    });
    family.addEventListener('change', () => {
        value.value = '';
        void refresh();
    });
    return { refresh };
};
