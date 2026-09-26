export const initializeComfyUiPage = ({
    page,
    documentObject,
    fetcher,
    csrfToken,
    getModelFamilyId,
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
                    positive: output('positive'),
                    negative: output('negative'),
                }),
            });
            const body = await response.json();
            if (!response.ok || typeof body.promptId !== 'string') {
                throw new Error(body.message ?? 'ComfyUIへ送信できませんでした。');
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
                throw new Error(body.errors?.workflow?.[0] ?? '保存できませんでした。');
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
