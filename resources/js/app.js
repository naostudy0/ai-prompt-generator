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
    let loraOptionsLoaded = false;
    let clothingLoraOptionsLoaded = false;
    let promptCategoriesLoaded = false;
    let editingPrompt = false;
    let toastTimer;
    let variantSourceMetadata = null;
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
        const defaultActionsDisabled = editingPrompt || !promptsLoaded;
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
            (promptsLoaded && hasSelections.default) ||
            hasSelections.lora ||
            hasSelections.clothingLora ||
            hasSelections.optionGroups;
        const displayDisabled = editingPrompt || !hasLoadedSelection;

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
        updateActionAvailability();
        setLoadStatus('デフォルト文面を読み込んでいます。');

        try {
            const prompts = await requestDefaultPrompts(fetcher, url);

            savedPrompts.positive = prompts.positive;
            savedPrompts.negative = prompts.negative;
            promptsLoaded = true;
            updateActionAvailability();
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
        const lora = loraOptionsController.getSelections();
        const clothingLora = clothingLoraOptionsController.getSelections();
        const categories = promptCategoriesController.getSections();
        outputs.positive = createPositivePromptOutput(
            outputs.positive,
            createPositivePromptSections({ ...lora, ...clothingLora }, categories),
        );
        variantSourceMetadata = createCharacterLoraSourceMetadata(
            createPromptOutputs(savedPrompts, selectedPrompts).positive,
            lora.lora,
            lora.trigger,
        );
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

    retryButton?.addEventListener('click', loadDefaultPrompts);
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
            return positive && variantSourceMetadata
                ? { positive, original: { ...variantSourceMetadata } }
                : null;
        },
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
            const generated = createPromptOutputs(savedPrompts, selectedPrompts);
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
                    createPromptOutputs(savedPrompts, selectedPrompts).positive,
                    restoredLora.lora,
                    restoredLora.trigger,
                ),
            );
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
