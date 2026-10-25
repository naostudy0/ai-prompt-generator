const requiredResult = (value) => {
    if (
        !value ||
        typeof value !== 'object' ||
        typeof value.positivePrompt !== 'string' ||
        typeof value.negativePrompt !== 'string' ||
        !value.modelFamily ||
        !Number.isInteger(value.modelFamily.id) ||
        typeof value.modelFamily.name !== 'string' ||
        !value.selectionSnapshot ||
        (!Array.isArray(value.review) &&
            (!value.review ||
                typeof value.review !== 'object' ||
                !Object.values(value.review).every(Array.isArray))) ||
        !Array.isArray(value.optionGroups)
    ) {
        throw new Error('解析結果の形式が正しくありません。');
    }

    return value;
};

const reviewItems = (review) =>
    (Array.isArray(review) ? review : Object.values(review).flat()).filter(
        (item) => item && typeof item === 'object' && typeof item.kind === 'string',
    );

const errorMessage = async (response) => {
    try {
        const body = await response.json();
        return typeof body?.message === 'string' && body.message !== ''
            ? body.message
            : '画像を解析できませんでした。';
    } catch {
        return '画像を解析できませんでした。';
    }
};

export const initializeComfyUiImageRestoration = ({
    page,
    documentObject,
    fetcher,
    csrfToken = '',
    confirmAction = () => true,
    captureCurrentState = () => ({ hasOutput: false }),
    applyRestoration = () => {},
    reloadPromptOptions = async () => {},
} = {}) => {
    const dialog = page?.querySelector('[data-comfyui-image-restoration-dialog]');
    const openButton = page?.querySelector('[data-comfyui-image-restoration-open]');
    if (!dialog || !openButton) {
        return null;
    }

    const closeButton = dialog.querySelector('[data-comfyui-image-restoration-close]');
    const form = dialog.querySelector('[data-comfyui-image-restoration-form]');
    const fileInput = dialog.querySelector('[data-comfyui-image-restoration-file]');
    const analyzeButton = dialog.querySelector('[data-comfyui-image-restoration-analyze]');
    const status = dialog.querySelector('[data-comfyui-image-restoration-status]');
    const preview = dialog.querySelector('[data-comfyui-image-restoration-preview]');
    const family = dialog.querySelector('[data-comfyui-image-restoration-family]');
    const positive = dialog.querySelector('[data-comfyui-image-restoration-positive]');
    const negative = dialog.querySelector('[data-comfyui-image-restoration-negative]');
    const summary = dialog.querySelector('[data-comfyui-image-restoration-summary]');
    const reviewList = dialog.querySelector('[data-comfyui-image-restoration-review]');
    const applyButton = dialog.querySelector('[data-comfyui-image-restoration-apply]');
    const saveSection = dialog.querySelector('[data-comfyui-image-restoration-save-section]');
    const unmatched = dialog.querySelector('[data-comfyui-image-restoration-unmatched]');
    const nameInput = dialog.querySelector('[data-comfyui-image-restoration-name]');
    const groupSelect = dialog.querySelector('[data-comfyui-image-restoration-group]');
    const saveButton = dialog.querySelector('[data-comfyui-image-restoration-save]');
    const saveStatus = dialog.querySelector('[data-comfyui-image-restoration-save-status]');
    let pending = false;
    let savePending = false;
    let requestVersion = 0;
    let analysis = null;
    let returnFocus = null;

    const clear = () => {
        analysis = null;
        fileInput.value = '';
        status.textContent = '';
        preview.hidden = true;
        positive.value = '';
        negative.value = '';
        reviewList.replaceChildren();
        unmatched?.replaceChildren();
        if (saveSection) {
            saveSection.hidden = true;
        }
    };

    const close = () => {
        requestVersion += 1;
        pending = false;
        analyzeButton.disabled = false;
        clear();
        dialog.close();
        returnFocus?.focus();
    };

    openButton.addEventListener('click', () => {
        returnFocus = documentObject.activeElement;
        dialog.showModal();
        fileInput.focus();
    });
    closeButton?.addEventListener('click', close);
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        close();
    });

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const image = fileInput.files?.[0];
        if (pending || !image) {
            if (!image) {
                status.textContent = 'PNG画像を選択してください。';
            }
            return;
        }
        pending = true;
        const version = ++requestVersion;
        analyzeButton.disabled = true;
        status.textContent = '画像を解析中です。';
        preview.hidden = true;
        try {
            const body = new documentObject.defaultView.FormData();
            body.append('image', image);
            const response = await fetcher(page.dataset.comfyUiImageAnalysisUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body,
            });
            if (version !== requestVersion) {
                return;
            }
            if (!response.ok) {
                throw new Error(await errorMessage(response));
            }
            analysis = requiredResult(await response.json());
            family.textContent = `系統: ${analysis.modelFamily.name}`;
            positive.value = analysis.positivePrompt;
            negative.value = analysis.negativePrompt;
            const reviews = reviewItems(analysis.review);
            summary.textContent = `確認が必要な項目: ${reviews.length}件`;
            reviewList.replaceChildren(
                ...reviews.map((item) => {
                    const element = documentObject.createElement('li');
                    const polarity = item.polarity === 'negative' ? 'negative' : 'positive';
                    element.textContent = `${polarity} / ${item.kind}: ${item.content ?? ''}`;
                    return element;
                }),
            );
            const candidates = reviews.filter((item) => item.kind === 'unregistered');
            if (saveSection && unmatched && groupSelect) {
                unmatched.replaceChildren(
                    ...candidates.map((item, index) => {
                        const label = documentObject.createElement('label');
                        const check = documentObject.createElement('input');
                        check.type = 'checkbox';
                        check.value = String(index);
                        check.dataset.comfyuiImageRestorationUnmatched = '';
                        label.append(check, documentObject.createTextNode(item.content));
                        return label;
                    }),
                );
                groupSelect.replaceChildren(
                    ...analysis.optionGroups.map((group) =>
                        Object.assign(documentObject.createElement('option'), {
                            value: String(group.id),
                            textContent: group.name,
                        }),
                    ),
                );
                saveSection.hidden = candidates.length === 0 || analysis.optionGroups.length === 0;
            }
            status.textContent = '解析が完了しました。内容を確認してください。';
            preview.hidden = false;
        } catch (error) {
            analysis = null;
            status.textContent =
                error instanceof Error ? error.message : '画像を解析できませんでした。';
        } finally {
            pending = false;
            analyzeButton.disabled = false;
        }
    });

    applyButton?.addEventListener('click', () => {
        if (!analysis) {
            return;
        }
        const current = captureCurrentState();
        if (
            (current?.hasOutput || current?.hasSelections) &&
            !confirmAction('現在の出力と選択を画像内のプロンプトで置き換えますか？')
        ) {
            return;
        }
        if (applyRestoration(analysis) !== false) {
            close();
        }
    });

    saveButton?.addEventListener('click', async () => {
        if (!analysis || savePending) {
            return;
        }
        const candidates = reviewItems(analysis.review).filter(
            (item) => item.kind === 'unregistered',
        );
        const selected = [...unmatched.querySelectorAll('input:checked')]
            .map((input) => candidates[Number(input.value)])
            .filter(Boolean);
        const name = nameInput.value.trim();
        const groupId = Number(groupSelect.value);
        const allowedGroup = analysis.optionGroups.some((group) => group.id === groupId);
        if (selected.length === 0 || name === '' || !allowedGroup) {
            saveStatus.textContent = '候補、登録名、登録先を指定してください。';
            return;
        }
        savePending = true;
        saveButton.disabled = true;
        saveStatus.textContent = '登録しています。';
        try {
            const response = await fetcher(page.dataset.promptOptionsUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    groupId,
                    name,
                    content: `${selected.map((item) => item.content).join(', ')},`,
                }),
            });
            if (!response.ok) {
                throw new Error(await errorMessage(response));
            }
            const saved = await response.json();
            if (!Number.isInteger(saved?.id)) {
                throw new Error('登録結果の形式が正しくありません。');
            }
            await reloadPromptOptions(saved.id);
            const selectedSet = new Set(selected);
            const remainingReviews = reviewItems(analysis.review).filter(
                (item) => !selectedSet.has(item),
            );
            selected.forEach((item) =>
                unmatched
                    .querySelector(`input[value="${candidates.indexOf(item)}"]`)
                    ?.parentElement?.remove(),
            );
            summary.textContent = `確認が必要な項目: ${remainingReviews.length}件`;
            saveStatus.textContent = '不足タグを登録し、選択しました。';
            nameInput.value = '';
            if (unmatched.childElementCount === 0) {
                saveSection.hidden = true;
            }
        } catch (error) {
            saveStatus.textContent =
                error instanceof Error ? error.message : '登録できませんでした。';
        } finally {
            savePending = false;
            saveButton.disabled = false;
        }
    });

    return { close };
};
