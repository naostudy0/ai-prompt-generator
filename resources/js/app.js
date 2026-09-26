import {
    createPromptOutputs,
    requestDefaultPrompts,
    requestSaveDefaultPrompt,
    writePromptToClipboard,
} from './default-prompts.js';
import { initializeLoraOptionsPage } from './lora-options-page.js';
import { initializeClothingLoraOptionsPage } from './clothing-lora-options-page.js';
import { createPositivePromptOutput } from './lora-options.js';
import { initializePromptCategoriesPage } from './prompt-categories-page.js';
import { createPositivePromptSections } from './positive-prompt-sections.js';
import { initializePromptSidebars } from './prompt-sidebars.js';
import { initializeFavoritePromptsPage } from './favorite-prompts-page.js';
import { normalizePromptForComparison } from './prompt-text.js';
import { initializeCharacterLoraVariantPage } from './character-lora-variant-page.js';
import {
    createCharacterLoraSourceMetadata,
    locateCharacterLoraSourceMetadata,
    trackCharacterLoraSourceEdit,
} from './character-lora-variant.js';
import { initializeComfyUiPage, initializeComfyUiWorkflowPage } from './comfyui-page.js';

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
    const sidebarDisplayButton = page.querySelector('[data-sidebar-display]');
    const favoriteSaveButton = page.querySelector('[data-favorite-save]');
    const favoriteOpenButton = page.querySelector('[data-favorite-open]');
    const resetButton = page.querySelector('[data-reset]');
    const promptElements = [...page.querySelectorAll('[data-default-prompt]')];
    const outputElements = [...page.querySelectorAll('[data-output]')];
    const outputSection = page.querySelector('[data-output-section]');
    const toast = page.querySelector('[data-toast]');
    const savedPrompts = { positive: '', negative: '' };
    const familyPrompts = new Map([[1, savedPrompts]]);
    let modelFamilies = [{ id: 1, name: 'Illustrious' }];
    let editFamilyId = 1;
    const selectedPrompts = { positive: true, negative: true };
    const hasSelections = {
        default: true,
        lora: false,
        clothingLora: false,
        optionGroups: false,
    };
    const sidebars = initializePromptSidebars({
        page,
        documentObject,
        onReorderOptionGroup: (id, beforeId) =>
            promptCategoriesController.reorderGroup(id, beforeId),
    });
    let promptsLoaded = false;
    let familiesLoaded = !page.dataset.modelFamiliesUrl;
    let loraOptionsLoaded = false;
    let clothingLoraOptionsLoaded = false;
    let promptCategoriesLoaded = false;
    let editingPrompt = false;
    let toastTimer;
    let variantSourceMetadata = null;
    let variantFamilySource = null;
    let previousPositiveOutput = '';
    let characterVariantController = { updateAvailability: () => {} };
    let loraOptionsController = {
        getSelections: () => ({ lora: '', trigger: '', outfit: '' }),
        getSelectionSnapshot: () => ({}),
        getVariantCandidates: () => null,
        restoreSelection: () => false,
        reset: () => {},
    };
    let clothingLoraOptionsController = {
        getSelections: () => ({ clothingLoras: '', clothingLoraTriggers: '' }),
        getSelectionSnapshot: () => [],
        restoreSelection: () => false,
        reset: () => {},
    };
    let promptCategoriesController = {
        reorderGroup: () => {},
        getSections: () => ({
            expression: '',
            gaze: '',
            action: '',
            location: '',
            composition: '',
            optionGroups: [],
        }),
        getSelectionSnapshot: () => [],
        restoreSelection: () => false,
        reset: () => {},
    };

    const isPolarity = (value) => value === 'positive' || value === 'negative';
    const activeFamilyId = () => loraOptionsController.getSelectedModelFamilyId?.() ?? 1;
    const familyPromptsUrl = (id) => `${page.dataset.modelFamiliesUrl}/${id}/default-prompts`;
    const familyName = (id) =>
        modelFamilies.find((family) => family.id === id)?.name ?? '不明な系統';

    const fetchFamilyPrompts = async (id) => {
        const response = await fetcher(familyPromptsUrl(id), {
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) {
            throw new Error('系統のデフォルト文面を取得できませんでした。');
        }
        const prompts = await response.json();
        if (typeof prompts.positive !== 'string' || typeof prompts.negative !== 'string') {
            throw new Error('系統のデフォルト文面が不正です。');
        }
        familyPrompts.set(id, prompts);
        return prompts;
    };

    const updateFamilyControls = () => {
        const activeName = page.querySelector('[data-active-family-name]');
        if (activeName instanceof HTMLElement) {
            activeName.textContent = familyName(activeFamilyId());
        }
        const editSelect = page.querySelector('[data-edit-family]');
        const manageSelect = page.querySelector('[data-family-list]');
        for (const select of [editSelect, manageSelect]) {
            if (!(select instanceof view.HTMLSelectElement)) {
                continue;
            }
            const previous = select === editSelect ? editFamilyId : Number(select.value);
            select.replaceChildren(
                ...modelFamilies.map((family) => {
                    const option = documentObject.createElement('option');
                    option.value = String(family.id);
                    option.textContent = family.name;
                    return option;
                }),
            );
            select.value = String(
                modelFamilies.some((family) => family.id === previous) ? previous : 1,
            );
        }
        loraOptionsController.setModelFamilies?.(modelFamilies);
    };

    const loadFamiliesAndDefaults = async () => {
        if (!page.dataset.modelFamiliesUrl) {
            return false;
        }
        familiesLoaded = false;
        familyPrompts.clear();
        updateActionAvailability();
        try {
            const response = await fetcher(page.dataset.modelFamiliesUrl, {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) {
                throw new Error('系統を取得できませんでした。');
            }
            const families = await response.json();
            if (
                !Array.isArray(families) ||
                !families.every(
                    (family) => Number.isInteger(family.id) && typeof family.name === 'string',
                )
            ) {
                throw new Error('系統一覧が不正です。');
            }
            modelFamilies = families;
            await Promise.all(families.map((family) => fetchFamilyPrompts(family.id)));
            familiesLoaded = true;
            updateFamilyControls();
            updateActionAvailability();
            characterVariantController.updateAvailability();
            if (promptsLoaded) {
                setLoadStatus('');
            }
            return true;
        } catch {
            familiesLoaded = false;
            setLoadStatus(
                '系統とデフォルト文面を読み込めませんでした。再読み込みしてください。',
                true,
            );
            updateActionAvailability();
            return false;
        }
    };

    const updateDefaultView = () => {
        const items = ['positive', 'negative']
            .filter((polarity) => selectedPrompts[polarity])
            .map((polarity) => ({
                label: `${polarity} デフォルト`,
                meta: '',
                details: [],
                onRemove: () => {
                    selectedPrompts[polarity] = false;
                    const element = promptElements.find(
                        (candidate) => candidate.dataset.defaultPrompt === polarity,
                    );
                    if (element instanceof HTMLElement) {
                        updateSelection(element, polarity);
                    }
                    updateDefaultView();
                    updateActionAvailability();
                },
            }));
        sidebars.update('default', {
            navigationItems: [
                {
                    key: 'default',
                    label: 'デフォルト',
                    target: '[data-default-prompts-section]',
                },
            ],
            selectionGroups: [{ key: 'default', label: 'デフォルト', items }],
        });
        hasSelections.default = items.length > 0;
    };

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

    const updateActionAvailability = () => {
        const defaultActionsDisabled = editingPrompt || !promptsLoaded || !familiesLoaded;
        promptElements.forEach((promptElement) => {
            const editButton = promptElement.querySelector('[data-edit]');
            const selectButton = promptElement.querySelector('[data-select]');

            if (editButton instanceof HTMLButtonElement) {
                editButton.disabled = defaultActionsDisabled;
            }

            if (selectButton instanceof HTMLButtonElement) {
                selectButton.disabled = defaultActionsDisabled;
            }
        });

        const hasLoadedSelection =
            (promptsLoaded && familiesLoaded && hasSelections.default) ||
            hasSelections.lora ||
            hasSelections.clothingLora ||
            hasSelections.optionGroups;
        const displayDisabled =
            editingPrompt ||
            !familiesLoaded ||
            !hasLoadedSelection ||
            !familyPrompts.has(activeFamilyId());

        [displayButton, sidebarDisplayButton].forEach((button) => {
            if (button instanceof HTMLButtonElement) {
                button.disabled = displayDisabled;
            }
        });
        if (resetButton instanceof HTMLButtonElement) {
            resetButton.disabled =
                editingPrompt ||
                !loraOptionsLoaded ||
                !clothingLoraOptionsLoaded ||
                !promptCategoriesLoaded;
        }
        const favoritesDisabled =
            editingPrompt ||
            !promptsLoaded ||
            !familiesLoaded ||
            !loraOptionsLoaded ||
            !clothingLoraOptionsLoaded ||
            !promptCategoriesLoaded;
        [favoriteSaveButton, favoriteOpenButton].forEach((button) => {
            if (button instanceof HTMLButtonElement) {
                button.disabled = favoritesDisabled;
            }
        });
    };

    const updateSidebarSnapshot = (source, snapshot) => {
        sidebars.update(source, snapshot);
        hasSelections[source] = snapshot.selectionGroups.some((group) => group.items.length > 0);
        if (source === 'lora') {
            const activeName = page.querySelector('[data-active-family-name]');
            if (activeName instanceof HTMLElement) {
                activeName.textContent = familyName(activeFamilyId());
            }
        }
        updateActionAvailability();
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

    const resizeOutputContent = (content) => {
        if (!(content instanceof HTMLTextAreaElement)) {
            return;
        }

        content.style.height = 'auto';
        const minimumHeight = Number.parseFloat(view.getComputedStyle(content).minHeight) || 0;
        const inputSpace = 20;
        content.style.height = `${Math.max(minimumHeight, content.scrollHeight + inputSpace)}px`;
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
        updateActionAvailability();

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
        updateActionAvailability();
        editor.hidden = false;
        content.value = (familyPrompts.get(editFamilyId) ?? savedPrompts)[polarity];
        content.focus();
    };

    const loadDefaultPrompts = async () => {
        const url = page.dataset.defaultPromptsUrl;

        if (!url) {
            setLoadStatus('読み込み先が設定されていません。', true);
            return;
        }

        promptsLoaded = false;
        updateActionAvailability();
        setLoadStatus('デフォルト文面を読み込んでいます。');

        try {
            const prompts = await requestDefaultPrompts(fetcher, url);

            savedPrompts.positive = prompts.positive;
            savedPrompts.negative = prompts.negative;
            promptsLoaded = true;
            updateActionAvailability();
            if (familiesLoaded) {
                setLoadStatus('');
            }
        } catch {
            setLoadStatus('デフォルト文面を読み込めませんでした。再度お試しください。', true);
        }
    };

    const saveDefaultPrompt = async (promptElement) => {
        const polarity = promptElement.dataset.defaultPrompt;
        const url = page.dataset.modelFamiliesUrl
            ? `${familyPromptsUrl(editFamilyId)}/${polarity}`
            : promptElement.dataset.updateUrl;
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

            if (editFamilyId === 1) {
                savedPrompts[polarity] = result.content;
            }
            const editedPrompts = familyPrompts.get(editFamilyId);
            if (editedPrompts) {
                editedPrompts[polarity] = result.content;
            }
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
        const currentPrompts = familyPrompts.get(activeFamilyId());
        if (!currentPrompts) {
            setLoadStatus('選択中の系統の文面を読み込めませんでした。', true);
            return;
        }
        const outputs = createPromptOutputs(currentPrompts, selectedPrompts);
        const lora = loraOptionsController.getSelections();
        const clothingLora = clothingLoraOptionsController.getSelections();
        const categories = promptCategoriesController.getSections();
        outputs.positive = createPositivePromptOutput(
            outputs.positive,
            createPositivePromptSections({ ...lora, ...clothingLora }, categories),
        );
        variantSourceMetadata = createCharacterLoraSourceMetadata(
            createPromptOutputs(currentPrompts, selectedPrompts).positive,
            lora.lora,
            lora.trigger,
        );
        variantFamilySource = {
            modelFamilyId: activeFamilyId(),
            defaults: { ...selectedPrompts },
            defaultSections: createPromptOutputs(currentPrompts, selectedPrompts),
        };
        previousPositiveOutput = outputs.positive;

        for (const polarity of ['positive', 'negative']) {
            const outputElement = getOutputElement(polarity);
            const content = outputElement?.querySelector('[data-output-content]');
            const status = outputElement?.querySelector('[data-copy-status]');

            if (content instanceof HTMLTextAreaElement) {
                content.value = outputs[polarity];
                resizeOutputContent(content);
            }

            if (status instanceof HTMLElement) {
                status.textContent = '';
            }

            if (outputElement instanceof HTMLElement) {
                updateCopyAvailability(outputElement);
            }
        }

        characterVariantController.updateAvailability();

        outputSection?.scrollIntoView?.({ behavior: 'smooth', block: 'start' });
    };

    const resetPrompts = () => {
        variantSourceMetadata = null;
        variantFamilySource = null;
        previousPositiveOutput = '';
        selectedPrompts.positive = true;
        selectedPrompts.negative = true;
        promptElements.forEach((element) => {
            const polarity = element.dataset.defaultPrompt;
            if (isPolarity(polarity)) {
                updateSelection(element, polarity);
            }
        });
        updateDefaultView();
        loraOptionsController.reset();
        clothingLoraOptionsController.reset();
        promptCategoriesController.reset();
        outputElements.forEach((element) => {
            const content = element.querySelector('[data-output-content]');
            if (content instanceof HTMLTextAreaElement) {
                content.value = '';
                resizeOutputContent(content);
            }
            const status = element.querySelector('[data-copy-status]');
            if (status instanceof HTMLElement) {
                status.textContent = '';
                delete status.dataset.state;
            }
            updateCopyAvailability(element);
        });
        characterVariantController.updateAvailability();
        page.querySelector('[data-lora-options]')?.scrollIntoView?.({
            behavior: 'smooth',
            block: 'start',
        });
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
            updateDefaultView();
            updateActionAvailability();
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

    const editFamilySelect = page.querySelector('[data-edit-family]');
    editFamilySelect?.addEventListener('change', () => {
        if (editingPrompt) {
            editFamilySelect.value = String(editFamilyId);
            return;
        }
        editFamilyId = Number(editFamilySelect.value);
    });
    const familyDialog = page.querySelector('[data-family-dialog]');
    const familyList = page.querySelector('[data-family-list]');
    const familyNameInput = page.querySelector('[data-family-name]');
    const familyStatus = page.querySelector('[data-family-status]');
    const comfyUiWorkflowController = initializeComfyUiWorkflowPage({
        page,
        documentObject,
        fetcher,
        csrfToken,
    });
    page.querySelector('[data-family-manage]')?.addEventListener('click', () => {
        if (editingPrompt || !familiesLoaded) {
            return;
        }
        updateFamilyControls();
        familyDialog?.showModal();
        void comfyUiWorkflowController.refresh();
    });
    familyList?.addEventListener('change', () => {
        if (familyNameInput) {
            familyNameInput.value = familyName(Number(familyList.value));
        }
    });
    const saveOrDeleteFamily = async (method) => {
        const id = Number(familyList?.value);
        const url =
            method === 'POST'
                ? page.dataset.modelFamiliesUrl
                : `${page.dataset.modelFamiliesUrl}/${id}`;
        if (!url || !csrfToken || !familyStatus) {
            return;
        }
        familyStatus.textContent = method === 'DELETE' ? '削除しています。' : '保存しています。';
        try {
            const response = await fetcher(url, {
                method,
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                ...(method === 'DELETE'
                    ? {}
                    : { body: JSON.stringify({ name: familyNameInput?.value ?? '' }) }),
            });
            if (!response.ok) {
                throw new Error('系統を保存できませんでした。');
            }
            const saved = method === 'DELETE' ? null : await response.json();
            if (method === 'POST' && saved?.id) {
                editFamilyId = saved.id;
            }
            if (method === 'DELETE') {
                familyPrompts.delete(id);
                if (editFamilyId === id) {
                    editFamilyId = 1;
                }
            }
            if (!(await loadFamiliesAndDefaults())) {
                throw new Error('系統を再取得できませんでした。');
            }
            familyStatus.textContent = method === 'DELETE' ? '削除しました。' : '保存しました。';
            if (method === 'POST' && editFamilySelect) {
                editFamilySelect.value = String(saved.id);
            }
        } catch {
            familyStatus.textContent =
                method === 'DELETE'
                    ? '使用中または初期の系統は削除できません。'
                    : '保存できませんでした。名前を確認してください。';
        }
    };
    page.querySelector('[data-family-add]')?.addEventListener(
        'click',
        () => void saveOrDeleteFamily('POST'),
    );
    page.querySelector('[data-family-rename]')?.addEventListener(
        'click',
        () => void saveOrDeleteFamily('PUT'),
    );
    page.querySelector('[data-family-delete]')?.addEventListener(
        'click',
        () => void saveOrDeleteFamily('DELETE'),
    );
    page.querySelector('[data-family-close]')?.addEventListener('click', () =>
        familyDialog?.close(),
    );
    familyDialog?.addEventListener('click', (event) => {
        if (event.target === familyDialog) {
            familyDialog.close();
        }
    });

    outputElements.forEach((outputElement) => {
        outputElement.querySelector('[data-output-content]')?.addEventListener('input', () => {
            const status = outputElement.querySelector('[data-copy-status]');

            if (status instanceof HTMLElement) {
                status.textContent = '';
            }

            updateCopyAvailability(outputElement);
            if (outputElement.dataset.output === 'positive' && variantSourceMetadata !== null) {
                const current = outputElement.querySelector('[data-output-content]')?.value ?? '';
                variantSourceMetadata = trackCharacterLoraSourceEdit(
                    variantSourceMetadata,
                    previousPositiveOutput,
                    current,
                );
                previousPositiveOutput = current;
            }
            characterVariantController.updateAvailability();
            resizeOutputContent(outputElement.querySelector('[data-output-content]'));
        });
        outputElement.querySelector('[data-copy]')?.addEventListener('click', () => {
            copyOutput(outputElement);
        });
    });

    retryButton?.addEventListener('click', () => {
        void loadDefaultPrompts();
        void loadFamiliesAndDefaults();
    });
    displayButton?.addEventListener('click', displayPrompts);
    sidebarDisplayButton?.addEventListener('click', displayPrompts);
    resetButton?.addEventListener('click', resetPrompts);
    updateDefaultView();
    loraOptionsController = initializeLoraOptionsPage({
        page,
        documentObject,
        fetcher,
        csrfToken,
        notify: showToast,
        onSidebarSnapshotChange: (snapshot) => updateSidebarSnapshot('lora', snapshot),
        onLoadedChange: (loaded) => {
            loraOptionsLoaded = loaded;
            updateActionAvailability();
            characterVariantController.updateAvailability();
        },
    });
    void loadFamiliesAndDefaults();
    clothingLoraOptionsController = initializeClothingLoraOptionsPage({
        page,
        documentObject,
        fetcher,
        csrfToken,
        onLoadedChange: (loaded) => {
            clothingLoraOptionsLoaded = loaded;
            updateActionAvailability();
        },
        notify: showToast,
        onSidebarSnapshotChange: (snapshot) => updateSidebarSnapshot('clothingLora', snapshot),
    });
    promptCategoriesController = initializePromptCategoriesPage({
        page,
        documentObject,
        fetcher,
        csrfToken,
        notify: showToast,
        onSidebarSnapshotChange: (snapshot) => updateSidebarSnapshot('optionGroups', snapshot),
        onLoadedChange: (loaded) => {
            promptCategoriesLoaded = loaded;
            updateActionAvailability();
        },
    });
    characterVariantController = initializeCharacterLoraVariantPage({
        page,
        documentObject,
        clipboard,
        getCandidates: () => loraOptionsController.getVariantCandidates(),
        getSource: () => {
            const positive =
                getOutputElement('positive')?.querySelector('[data-output-content]')?.value;
            const negative =
                getOutputElement('negative')?.querySelector('[data-output-content]')?.value ?? '';
            return positive && variantSourceMetadata
                ? {
                      positive,
                      negative,
                      original: { ...variantSourceMetadata },
                      family: variantFamilySource,
                  }
                : null;
        },
        getFamilyPrompts: (id) => familyPrompts.get(id) ?? null,
        notify: showToast,
        fetcher,
        csrfToken,
    });
    initializeComfyUiPage({
        page,
        documentObject,
        fetcher,
        csrfToken,
        getModelFamilyId: () => variantFamilySource?.modelFamilyId ?? activeFamilyId(),
        notify: showToast,
    });
    initializeFavoritePromptsPage({
        page,
        documentObject,
        fetcher,
        csrfToken,
        notify: showToast,
        capture: () => ({
            positivePrompt:
                getOutputElement('positive')?.querySelector('[data-output-content]')?.value ?? '',
            negativePrompt:
                getOutputElement('negative')?.querySelector('[data-output-content]')?.value ?? '',
            selectionSnapshot: {
                defaults: { ...selectedPrompts },
                modelFamilyId: variantFamilySource?.modelFamilyId ?? activeFamilyId(),
                defaultSections:
                    variantFamilySource?.defaultSections ??
                    createPromptOutputs(
                        familyPrompts.get(activeFamilyId()) ?? savedPrompts,
                        selectedPrompts,
                    ),
                lora: loraOptionsController.getSelectionSnapshot(),
                clothingLoras: clothingLoraOptionsController.getSelectionSnapshot(),
                optionIds: promptCategoriesController.getSelectionSnapshot(),
            },
            selectionSummary: sidebars.getSelectionGroups(),
        }),
        restore: (favorite) => {
            const snapshot = favorite.selectionSnapshot;
            selectedPrompts.positive = snapshot.defaults?.positive === true;
            selectedPrompts.negative = snapshot.defaults?.negative === true;
            promptElements.forEach((element) => {
                if (isPolarity(element.dataset.defaultPrompt)) {
                    updateSelection(element, element.dataset.defaultPrompt);
                }
            });
            updateDefaultView();
            loraOptionsController.restoreSelection(snapshot.lora);
            clothingLoraOptionsController.restoreSelection(snapshot.clothingLoras);
            promptCategoriesController.restoreSelection(snapshot.optionIds);
            const restoredGroups = sidebars.getSelectionGroups();
            const unavailableLabels = favorite.selectionSummary.flatMap((savedGroup) => {
                const restoredGroup = restoredGroups.find((group) => group.key === savedGroup.key);
                const restoredItems = new Set(
                    restoredGroup?.items.map((item) => JSON.stringify(item)) ?? [],
                );
                return savedGroup.items
                    .filter((item) => !restoredItems.has(JSON.stringify(item)))
                    .map((item) => item.label);
            });
            const restoredFamilyId = snapshot.modelFamilyId ?? 1;
            const restoredPrompts = familyPrompts.get(activeFamilyId()) ?? savedPrompts;
            const generated = createPromptOutputs(restoredPrompts, selectedPrompts);
            const restoredLora = loraOptionsController.getSelections();
            generated.positive = createPositivePromptOutput(
                generated.positive,
                createPositivePromptSections(
                    {
                        ...restoredLora,
                        ...clothingLoraOptionsController.getSelections(),
                    },
                    promptCategoriesController.getSections(),
                ),
            );
            for (const polarity of ['positive', 'negative']) {
                const outputElement = getOutputElement(polarity);
                const content = outputElement?.querySelector('[data-output-content]');
                if (content instanceof HTMLTextAreaElement) {
                    content.value = favorite[`${polarity}Prompt`];
                    resizeOutputContent(content);
                }
                if (outputElement instanceof HTMLElement) {
                    updateCopyAvailability(outputElement);
                }
            }
            previousPositiveOutput =
                getOutputElement('positive')?.querySelector('[data-output-content]')?.value ?? '';
            variantSourceMetadata = locateCharacterLoraSourceMetadata(
                previousPositiveOutput,
                createCharacterLoraSourceMetadata(
                    createPromptOutputs(restoredPrompts, selectedPrompts).positive,
                    restoredLora.lora,
                    restoredLora.trigger,
                ),
            );
            variantFamilySource = {
                modelFamilyId: restoredFamilyId,
                defaults: { ...selectedPrompts },
                defaultSections:
                    snapshot.defaultSections ?? createPromptOutputs(savedPrompts, selectedPrompts),
            };
            outputSection?.scrollIntoView?.({ behavior: 'smooth', block: 'start' });
            updateActionAvailability();
            characterVariantController.updateAvailability();
            return {
                complete: unavailableLabels.length === 0,
                unavailableLabels,
                matches:
                    normalizePromptForComparison(generated.positive) ===
                        normalizePromptForComparison(favorite.positivePrompt) &&
                    normalizePromptForComparison(generated.negative) ===
                        normalizePromptForComparison(favorite.negativePrompt),
            };
        },
    });
    void loadDefaultPrompts();
};

if (typeof document !== 'undefined') {
    initializePromptPreparationPage();
}
