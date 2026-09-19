import {
    createLoraPromptSections,
    deleteLoraPromptOption,
    filterLoras,
    orderOutfits,
    requestLoraPromptOptions,
    saveLoraPromptOption,
} from './lora-options.js';
import { scrollToSelectionSection } from './selection-navigation.js';

export const initializeLoraOptionsPage = ({
    page,
    documentObject,
    fetcher,
    csrfToken,
    onLoadedChange,
    onSidebarSnapshotChange = () => {},
    notify,
}) => {
    const view = documentObject.defaultView;
    const root = page.querySelector('[data-lora-options]');

    if (!view || !(root instanceof view.HTMLElement)) {
        onLoadedChange(true);
        return {
            getSelections: () => ({ lora: '', trigger: '', outfit: '' }),
            reset: () => {},
        };
    }

    const { HTMLButtonElement, HTMLDialogElement, HTMLInputElement, HTMLSelectElement } = view;
    const loadStatus = root.querySelector('[data-option-load-status]');
    const retryButton = root.querySelector('[data-option-retry]');
    const searchInput = root.querySelector('[data-lora-search]');
    const loraList = root.querySelector('[data-lora-list]');
    const triggerList = root.querySelector('[data-trigger-list]');
    const outfitList = root.querySelector('[data-outfit-list]');
    const outfitSearchInput = root.querySelector('[data-outfit-search]');
    const strengthSelect = root.querySelector('[data-lora-strength]');
    const optionDialog = page.querySelector('[data-option-dialog]');
    const optionForm = page.querySelector('[data-option-form]');
    const deleteDialog = page.querySelector('[data-delete-dialog]');
    const deleteForm = page.querySelector('[data-delete-form]');
    const state = {
        loras: [],
        triggers: [],
        outfits: [],
        selectedLoraId: null,
        selectedTriggerId: null,
        selectedOutfitId: null,
        loaded: false,
        editingType: null,
        deletingType: null,
        deletingId: null,
    };

    const endpoints = {
        lora: page.dataset.lorasUrl,
        trigger: page.dataset.loraTriggersUrl,
        outfit: page.dataset.outfitsUrl,
    };

    const selectedItem = (type) => {
        const collection =
            type === 'lora' ? state.loras : type === 'trigger' ? state.triggers : state.outfits;
        const id =
            type === 'lora'
                ? state.selectedLoraId
                : type === 'trigger'
                  ? state.selectedTriggerId
                  : state.selectedOutfitId;

        return collection.find((item) => item.id === id) ?? null;
    };

    const getSidebarSnapshot = () => {
        const lora = selectedItem('lora');
        const trigger = selectedItem('trigger');
        const outfit = selectedItem('outfit');
        const items = [];
        if (lora !== null) {
            items.push({
                label: lora.name,
                meta: `強度 ${strengthSelect?.value ?? lora.recommendedStrength}`,
                details: trigger === null ? [] : [`トリガー：${trigger.name}`],
            });
        }
        if (outfit !== null) {
            items.push({ label: `服装：${outfit.name}`, meta: '', details: [] });
        }
        return {
            navigationItems: [
                {
                    key: 'character-lora',
                    label: '人物・キャラクターLoRA',
                    target: '[data-lora-options]',
                },
            ],
            selectionGroups: [{ key: 'character-lora', label: '人物・キャラクターLoRA', items }],
        };
    };

    const replaceOptions = (select, items, selectedId, label) => {
        if (!(select instanceof HTMLSelectElement)) {
            return;
        }

        select.replaceChildren();
        items.forEach((item) => {
            const option = documentObject.createElement('option');
            option.value = String(item.id);
            option.textContent = label(item);
            option.selected = item.id === selectedId;
            select.append(option);
        });

        if (selectedId === null || !items.some((item) => item.id === selectedId)) {
            select.selectedIndex = -1;
        }
    };

    const setButtonDisabled = (selector, disabled) => {
        const button = root.querySelector(selector);

        if (button instanceof HTMLButtonElement) {
            button.disabled = disabled;
        }
    };

    const render = () => {
        const searchedLoras = filterLoras(
            state.loras,
            searchInput instanceof HTMLInputElement ? searchInput.value : '',
        );
        const selectedLora = selectedItem('lora');
        const triggers = state.triggers.filter(
            (trigger) => trigger.loraId === state.selectedLoraId,
        );
        const outfitSearch =
            outfitSearchInput instanceof HTMLInputElement
                ? outfitSearchInput.value.trim().toLocaleLowerCase()
                : '';
        const matchingOutfits = state.outfits.filter(
            (outfit) =>
                outfitSearch === '' || outfit.name.toLocaleLowerCase().includes(outfitSearch),
        );
        const selectedOutfit = selectedItem('outfit');
        const selectedOutfitDoesNotMatch =
            selectedOutfit !== null &&
            !matchingOutfits.some((outfit) => outfit.id === selectedOutfit.id);
        const outfits = orderOutfits(matchingOutfits, state.selectedLoraId);
        if (selectedOutfitDoesNotMatch) {
            outfits.unshift(selectedOutfit);
        }

        replaceOptions(
            loraList,
            searchedLoras,
            state.selectedLoraId,
            (lora) => `${lora.name} — ${lora.fileName}`,
        );
        replaceOptions(
            triggerList,
            triggers,
            state.selectedTriggerId,
            (trigger) => `${trigger.name} — ${trigger.content} (#${trigger.id})`,
        );
        replaceOptions(outfitList, outfits, state.selectedOutfitId, (outfit) => {
            const linkedLora = state.loras.find((lora) => lora.id === outfit.loraId);
            return `${outfit.name} — ${linkedLora?.name ?? ''} — ${outfit.content} (#${outfit.id})`;
        });

        if (strengthSelect instanceof HTMLSelectElement) {
            strengthSelect.disabled = selectedLora === null;
        }

        if (searchInput instanceof HTMLInputElement) {
            searchInput.disabled = !state.loaded;
        }

        if (loraList instanceof HTMLSelectElement) {
            loraList.disabled = !state.loaded;
        }
        if (triggerList instanceof HTMLSelectElement) {
            triggerList.disabled = selectedLora === null;
        }
        if (outfitList instanceof HTMLSelectElement) {
            outfitList.disabled = !state.loaded;
        }
        if (outfitSearchInput instanceof HTMLInputElement) {
            outfitSearchInput.disabled = !state.loaded;
        }

        setButtonDisabled('[data-option-add="lora"]', !state.loaded);
        setButtonDisabled('[data-option-add="trigger"]', selectedLora === null);
        setButtonDisabled('[data-option-add="outfit"]', !state.loaded || state.loras.length === 0);
        for (const type of ['lora', 'trigger', 'outfit']) {
            const unselected = selectedItem(type) === null;
            setButtonDisabled(`[data-option-edit="${type}"]`, unselected);
            setButtonDisabled(`[data-option-delete="${type}"]`, unselected);
            setButtonDisabled(`[data-option-clear="${type}"]`, unselected);
        }
        onSidebarSnapshotChange(getSidebarSnapshot());
    };

    const loadOptions = async () => {
        const url = page.dataset.loraOptionsUrl;

        state.loaded = false;
        onLoadedChange(false);
        render();
        loadStatus.textContent = 'LoRAの選択肢を読み込んでいます。';
        retryButton.hidden = true;

        try {
            const result = await requestLoraPromptOptions(fetcher, url);
            state.loras = result.loras;
            state.triggers = result.triggers;
            state.outfits = result.outfits;
            state.selectedLoraId = state.loras.some((item) => item.id === state.selectedLoraId)
                ? state.selectedLoraId
                : null;
            state.selectedTriggerId = state.triggers.some(
                (item) => item.id === state.selectedTriggerId,
            )
                ? state.selectedTriggerId
                : null;
            state.selectedOutfitId = state.outfits.some(
                (item) => item.id === state.selectedOutfitId,
            )
                ? state.selectedOutfitId
                : null;
            state.loaded = true;
            loadStatus.textContent = '';
            retryButton.hidden = true;
            render();
            onLoadedChange(true);
        } catch {
            loadStatus.textContent = 'LoRAの選択肢を読み込めませんでした。';
            retryButton.hidden = false;
            render();
        }
    };

    const showDialog = (dialog) => {
        if (dialog instanceof HTMLDialogElement) {
            dialog.showModal();
        }
    };

    const closeDialog = (dialog) => {
        if (dialog instanceof HTMLDialogElement) {
            dialog.close();
        }
    };

    const closeDialogFromBackdrop = (event, dialog) => {
        if (!(dialog instanceof HTMLDialogElement) || event.target !== dialog) {
            return;
        }

        const bounds = dialog.getBoundingClientRect();
        const clickedOutside =
            event.clientX < bounds.left ||
            event.clientX > bounds.right ||
            event.clientY < bounds.top ||
            event.clientY > bounds.bottom;

        if (clickedOutside) {
            closeDialog(dialog);
        }
    };

    const openOptionDialog = (type, item = null) => {
        state.editingType = type;
        page.querySelector('[data-option-dialog-title]').textContent =
            `${type === 'lora' ? 'LoRA' : type === 'trigger' ? 'トリガー' : '服装'}を${item === null ? '追加' : '編集'}`;
        page.querySelector('[data-option-id]').value = item?.id ?? '';
        page.querySelector('[data-option-name]').value = item?.name ?? '';
        page.querySelector('[data-option-file-name]').value = item?.fileName ?? '';
        page.querySelector('[data-option-strength]').value = String(item?.recommendedStrength ?? 1);
        page.querySelector('[data-option-content]').value = item?.content ?? '';
        page.querySelector('[data-option-form-status]').textContent = '';
        page.querySelector('[data-lora-field]').hidden = type !== 'lora';
        page.querySelector('[data-strength-field]').hidden = type !== 'lora';
        page.querySelector('[data-content-field]').hidden = type === 'lora';
        page.querySelector('[data-association-field]').hidden = type !== 'outfit';

        const association = page.querySelector('[data-option-lora]');
        replaceOptions(
            association,
            state.loras,
            item?.loraId ?? state.selectedLoraId,
            (lora) => lora.name,
        );

        showDialog(optionDialog);
        page.querySelector(
            type === 'lora' ? '[data-option-file-name]' : '[data-option-name]',
        )?.focus();
    };

    const saveOption = async () => {
        const type = state.editingType;
        const endpoint = endpoints[type];
        const idValue = page.querySelector('[data-option-id]').value;
        const id = idValue === '' ? null : Number(idValue);
        const name = page.querySelector('[data-option-name]').value;
        const content = page.querySelector('[data-option-content]').value;
        const status = page.querySelector('[data-option-form-status]');
        let values;

        if (type === 'lora') {
            values = {
                name,
                fileName: page.querySelector('[data-option-file-name]').value,
                recommendedStrength: Number(page.querySelector('[data-option-strength]').value),
            };
        } else if (type === 'trigger') {
            values = { loraId: state.selectedLoraId, name, content };
        } else {
            const loraId = page.querySelector('[data-option-lora]').value;
            values = { loraId: Number(loraId), name, content };
        }

        status.textContent = '保存しています。';
        page.querySelector('[data-option-save]').disabled = true;

        try {
            await saveLoraPromptOption(fetcher, endpoint, csrfToken, id, values);
            closeDialog(optionDialog);
            notify('保存しました。');
            await loadOptions();
        } catch {
            status.textContent =
                '保存できませんでした。入力内容を確認するか、時間をおいて再度お試しください。';
            status.dataset.state = 'error';
        } finally {
            page.querySelector('[data-option-save]').disabled = false;
        }
    };

    const openDeleteDialog = (type) => {
        const item = selectedItem(type);

        if (item === null) {
            return;
        }
        state.deletingType = type;
        state.deletingId = item.id;
        const effect = type === 'lora' ? '紐づくトリガーと服装も削除されます。' : '';
        const typeName = type === 'lora' ? 'LoRA' : type === 'trigger' ? 'トリガー' : '服装';
        const identifier = type === 'lora' ? item.fileName : `${item.content}（ID: ${item.id}）`;
        page.querySelector('[data-delete-message]').textContent =
            `${typeName}「${item.name}」— ${identifier}を削除します。${effect}`;
        page.querySelector('[data-delete-status]').textContent = '';
        showDialog(deleteDialog);
    };

    const deleteOption = async () => {
        const type = state.deletingType;
        const endpoint = endpoints[type];
        const status = page.querySelector('[data-delete-status]');

        status.textContent = '削除しています。';
        page.querySelector('[data-delete-confirm]').disabled = true;

        try {
            const deletesSelectedOutfit =
                type === 'lora' && selectedItem('outfit')?.loraId === state.deletingId;
            await deleteLoraPromptOption(fetcher, endpoint, csrfToken, state.deletingId);
            if (type === 'lora') {
                state.selectedLoraId = null;
                state.selectedTriggerId = null;
                if (deletesSelectedOutfit) {
                    state.selectedOutfitId = null;
                }
            } else if (type === 'trigger') {
                state.selectedTriggerId = null;
            } else {
                state.selectedOutfitId = null;
            }
            closeDialog(deleteDialog);
            notify('削除しました。');
            await loadOptions();
        } catch {
            status.textContent = '削除できませんでした。再度お試しください。';
            status.dataset.state = 'error';
        } finally {
            page.querySelector('[data-delete-confirm]').disabled = false;
        }
    };

    searchInput?.addEventListener('input', () => {
        if (
            state.selectedLoraId !== null &&
            !filterLoras(state.loras, searchInput.value).some(
                (lora) => lora.id === state.selectedLoraId,
            )
        ) {
            state.selectedLoraId = null;
            state.selectedTriggerId = null;
            strengthSelect.value = '1';
        }
        render();
    });
    outfitSearchInput?.addEventListener('input', render);
    loraList?.addEventListener('change', () => {
        const loraId = Number(loraList.value);
        state.selectedLoraId = state.loras.some((lora) => lora.id === loraId) ? loraId : null;
        state.selectedTriggerId =
            state.triggers.find((trigger) => trigger.loraId === state.selectedLoraId)?.id ?? null;
        const lora = selectedItem('lora');
        strengthSelect.value = String(lora?.recommendedStrength ?? 1);
        render();
        scrollToSelectionSection(root.querySelector('[data-selection-section="trigger"]'));
    });
    triggerList?.addEventListener('change', () => {
        state.selectedTriggerId = Number(triggerList.value);
        render();
        scrollToSelectionSection(root.querySelector('[data-selection-section="outfit"]'));
    });
    outfitList?.addEventListener('change', () => {
        state.selectedOutfitId = Number(outfitList.value);
        render();
        scrollToSelectionSection(page.querySelector('[data-option-groups]'));
    });
    strengthSelect?.addEventListener('change', () => onSidebarSnapshotChange(getSidebarSnapshot()));

    root.querySelectorAll('[data-option-add]').forEach((button) =>
        button.addEventListener('click', () => openOptionDialog(button.dataset.optionAdd)),
    );
    root.querySelectorAll('[data-option-edit]').forEach((button) =>
        button.addEventListener('click', () => {
            const type = button.dataset.optionEdit;
            openOptionDialog(type, selectedItem(type));
        }),
    );
    root.querySelectorAll('[data-option-delete]').forEach((button) =>
        button.addEventListener('click', () => openDeleteDialog(button.dataset.optionDelete)),
    );
    root.querySelectorAll('[data-option-clear]').forEach((button) =>
        button.addEventListener('click', () => {
            const type = button.dataset.optionClear;
            if (type === 'lora') {
                state.selectedLoraId = null;
                state.selectedTriggerId = null;
                strengthSelect.value = '1';
            } else if (type === 'trigger') {
                state.selectedTriggerId = null;
            } else {
                state.selectedOutfitId = null;
            }
            render();
        }),
    );

    retryButton?.addEventListener('click', loadOptions);
    optionForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        void saveOption();
    });
    deleteForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        void deleteOption();
    });
    page.querySelector('[data-option-cancel]')?.addEventListener('click', () =>
        closeDialog(optionDialog),
    );
    page.querySelector('[data-delete-cancel]')?.addEventListener('click', () =>
        closeDialog(deleteDialog),
    );
    optionDialog?.addEventListener('click', (event) =>
        closeDialogFromBackdrop(event, optionDialog),
    );
    deleteDialog?.addEventListener('click', (event) =>
        closeDialogFromBackdrop(event, deleteDialog),
    );

    void loadOptions();

    return {
        getSelections: () => {
            const loraSections = createLoraPromptSections({
                lora: selectedItem('lora'),
                strength: Number(strengthSelect?.value ?? 1),
                trigger: null,
                outfit: null,
            });
            return {
                lora: loraSections[0] ?? '',
                trigger: selectedItem('trigger')?.content ?? '',
                outfit: selectedItem('outfit')?.content ?? '',
            };
        },
        reset: () => {
            state.selectedLoraId = null;
            state.selectedTriggerId = null;
            state.selectedOutfitId = null;
            if (searchInput instanceof HTMLInputElement) {
                searchInput.value = '';
            }
            if (outfitSearchInput instanceof HTMLInputElement) {
                outfitSearchInput.value = '';
            }
            if (strengthSelect instanceof HTMLSelectElement) {
                strengthSelect.value = '1';
            }
            render();
        },
    };
};
