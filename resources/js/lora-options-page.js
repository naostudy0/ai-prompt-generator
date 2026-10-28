import {
    createLoraPromptSections,
    changeCharacterLoraVisibility,
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
            getSelectionSnapshot: () => ({}),
            getVariantCandidates: () => null,
            getCharacterLoraApplication: () => null,
            getSelectedModelFamilyId: () => null,
            getSelectedLoraId: () => null,
            setModelFamilies: () => {},
            hasSelectionIds: () => false,
            restoreSelection: () => false,
            reset: () => {},
        };
    }

    const { HTMLButtonElement, HTMLDialogElement, HTMLInputElement, HTMLSelectElement } = view;
    const loadStatus = root.querySelector('[data-option-load-status]');
    const retryButton = root.querySelector('[data-option-retry]');
    const searchInput = root.querySelector('[data-lora-search]');
    const familyFilter = root.querySelector('[data-lora-family-filter]');
    const pagePrevious = root.querySelector('[data-lora-page-previous]');
    const pageNext = root.querySelector('[data-lora-page-next]');
    const pageStatus = root.querySelector('[data-lora-page-status]');
    const loraList = root.querySelector('[data-lora-list]');
    const triggerList = root.querySelector('[data-trigger-list]');
    const outfitList = root.querySelector('[data-outfit-list]');
    const outfitSearchInput = root.querySelector('[data-outfit-search]');
    const strengthSelect = root.querySelector('[data-lora-strength]');
    const visibilityDialog = page.querySelector('[data-lora-visibility-dialog]');
    const visibilityFilter = page.querySelector('[data-lora-visibility-filter]');
    const visibilitySearch = page.querySelector('[data-lora-visibility-search]');
    const visibilityList = page.querySelector('[data-lora-visibility-list]');
    const visibilityStatus = page.querySelector('[data-lora-visibility-status]');
    const optionDialog = page.querySelector('[data-option-dialog]');
    const optionForm = page.querySelector('[data-option-form]');
    const deleteDialog = page.querySelector('[data-delete-dialog]');
    const deleteForm = page.querySelector('[data-delete-form]');
    const state = {
        loras: [],
        triggers: [],
        outfits: [],
        modelFamilies: [{ id: 1, name: 'Illustrious' }],
        generationSettingOptions: [],
        selectedLoraId: null,
        selectedTriggerId: null,
        selectedOutfitId: null,
        loaded: false,
        editingType: null,
        deletingType: null,
        deletingId: null,
        loraPage: 1,
        managedLoras: [],
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
                onRemove: () => {
                    state.selectedLoraId = null;
                    state.selectedTriggerId = null;
                    strengthSelect.value = '1';
                    render();
                },
            });
        }
        if (outfit !== null) {
            items.push({
                label: `服装：${outfit.name}`,
                meta: '',
                details: [],
                onRemove: () => {
                    state.selectedOutfitId = null;
                    render();
                },
            });
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

    const renderGenerationSettingFields = (lora = null) => {
        const familyId = Number(page.querySelector('[data-option-family]')?.value ?? 0);
        for (const kind of ['checkpoint', 'sampler', 'scheduler']) {
            const selectedId = lora?.[`${kind}OptionId`] ?? null;
            replaceOptions(
                page.querySelector(`[data-option-generation-setting="${kind}"]`),
                state.generationSettingOptions.filter(
                    (option) => option.modelFamilyId === familyId && option.kind === kind,
                ),
                selectedId,
                (option) => option.value,
            );
        }
    };

    const render = () => {
        const searchedLoras = filterLoras(
            state.loras,
            searchInput instanceof HTMLInputElement ? searchInput.value : '',
        ).filter(
            (lora) =>
                !(familyFilter instanceof HTMLSelectElement) ||
                familyFilter.value === '' ||
                (lora.modelFamilyId ?? 1) === Number(familyFilter.value),
        );
        const pageSize = 30;
        const pageCount = Math.max(1, Math.ceil(searchedLoras.length / pageSize));
        state.loraPage = Math.min(state.loraPage, pageCount);
        const pagedLoras = searchedLoras.slice(
            (state.loraPage - 1) * pageSize,
            state.loraPage * pageSize,
        );
        const selectedOutsidePage = state.loras.find(
            (lora) =>
                lora.id === state.selectedLoraId && !pagedLoras.some((item) => item.id === lora.id),
        );
        if (selectedOutsidePage) {
            pagedLoras.unshift(selectedOutsidePage);
        }
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
            pagedLoras,
            state.selectedLoraId,
            (lora) =>
                `${lora.name} — ${lora.fileName} (${state.modelFamilies.find((family) => family.id === (lora.modelFamilyId ?? 1))?.name ?? '不明な系統'})`,
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
        if (familyFilter instanceof HTMLSelectElement) {
            familyFilter.disabled = !state.loaded;
        }
        if (pageStatus instanceof view.HTMLElement) {
            pageStatus.textContent = `${searchedLoras.length}件・${state.loraPage}/${pageCount}ページ`;
        }
        if (pagePrevious instanceof HTMLButtonElement) {
            pagePrevious.disabled = !state.loaded || state.loraPage <= 1;
        }
        if (pageNext instanceof HTMLButtonElement) {
            pageNext.disabled = !state.loaded || state.loraPage >= pageCount;
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

    const renderManagedLoras = () => {
        if (!(visibilityList instanceof view.HTMLElement)) {
            return;
        }
        const search = visibilitySearch?.value.trim().toLocaleLowerCase() ?? '';
        visibilityList.replaceChildren();
        state.managedLoras
            .filter((lora) => search === '' || lora.name.toLocaleLowerCase().includes(search))
            .forEach((lora) => {
                const row = documentObject.createElement('div');
                row.className = 'character-variant-row';
                const identity = documentObject.createElement('div');
                identity.className = 'lora-visibility-identity';
                const label = documentObject.createElement('span');
                label.textContent = lora.name;
                const badge = documentObject.createElement('span');
                badge.className = `lora-visibility-badge lora-visibility-badge--${lora.isHidden ? 'hidden' : 'visible'}`;
                badge.textContent = lora.isHidden ? '非表示' : '表示中';
                const button = documentObject.createElement('button');
                button.type = 'button';
                button.className = `small-button lora-visibility-action lora-visibility-action--${lora.isHidden ? 'show' : 'hide'}`;
                button.textContent = lora.isHidden ? '表示する' : '非表示にする';
                button.addEventListener('click', async () => {
                    button.disabled = true;
                    try {
                        await changeCharacterLoraVisibility(
                            fetcher,
                            endpoints.lora,
                            csrfToken,
                            lora.id,
                            !lora.isHidden,
                        );
                        if (!lora.isHidden && state.selectedLoraId === lora.id) {
                            state.selectedLoraId = null;
                            state.selectedTriggerId = null;
                            if (
                                state.selectedOutfitId !== null &&
                                state.outfits.find((item) => item.id === state.selectedOutfitId)
                                    ?.loraId === lora.id
                            ) {
                                state.selectedOutfitId = null;
                            }
                            notify('選択を解除しました。出力へ反映するには再表示してください。');
                        }
                        await loadOptions();
                        await loadManagedLoras();
                    } catch {
                        visibilityStatus.textContent =
                            '表示状態を保存できませんでした。再度お試しください。';
                    } finally {
                        button.disabled = false;
                    }
                });
                identity.append(label, badge);
                row.append(identity, button);
                visibilityList.append(row);
            });
        visibilityStatus.textContent =
            state.managedLoras.length === 0 ? '該当する人物LoRAはありません。' : '';
    };

    const loadManagedLoras = async () => {
        const visibility = visibilityFilter?.value ?? 'all';
        visibilityStatus.textContent = '読み込んでいます。';
        try {
            const result = await requestLoraPromptOptions(
                fetcher,
                `${page.dataset.loraOptionsUrl}?visibility=${visibility}`,
            );
            state.managedLoras = result.loras;
            renderManagedLoras();
        } catch {
            visibilityStatus.textContent = '人物LoRAを読み込めませんでした。';
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
        const familyField = page.querySelector('[data-lora-family-field]');
        if (familyField) {
            familyField.hidden = type !== 'lora';
        }
        replaceOptions(
            page.querySelector('[data-option-family]'),
            state.modelFamilies,
            item?.modelFamilyId ?? 1,
            (family) => family.name,
        );
        const generationSettings = page.querySelector('[data-lora-generation-settings]');
        if (generationSettings) {
            generationSettings.hidden = type !== 'lora';
        }
        page.querySelectorAll('[data-option-generation-setting]').forEach((select) => {
            select.disabled = type !== 'lora';
        });
        renderGenerationSettingFields(item);
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
                modelFamilyId: Number(page.querySelector('[data-option-family]')?.value ?? 1),
                checkpointOptionId: Number(
                    page.querySelector('[data-option-generation-setting="checkpoint"]')?.value,
                ),
                samplerOptionId: Number(
                    page.querySelector('[data-option-generation-setting="sampler"]')?.value,
                ),
                schedulerOptionId: Number(
                    page.querySelector('[data-option-generation-setting="scheduler"]')?.value,
                ),
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
        } catch (error) {
            status.textContent =
                error instanceof Error
                    ? error.message
                    : '保存できませんでした。時間をおいて再度お試しください。';
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
        state.loraPage = 1;
        render();
    });
    familyFilter?.addEventListener('change', () => {
        state.loraPage = 1;
        render();
    });
    pagePrevious?.addEventListener('click', () => {
        state.loraPage = Math.max(1, state.loraPage - 1);
        render();
    });
    pageNext?.addEventListener('click', () => {
        state.loraPage += 1;
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
    });
    outfitList?.addEventListener('change', () => {
        state.selectedOutfitId = Number(outfitList.value);
        render();
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
    page.querySelector('[data-option-family]')?.addEventListener('change', () =>
        renderGenerationSettingFields(),
    );
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
    visibilityDialog?.addEventListener('click', (event) =>
        closeDialogFromBackdrop(event, visibilityDialog),
    );
    page.querySelector('[data-lora-visibility-manage]')?.addEventListener('click', () => {
        showDialog(visibilityDialog);
        void loadManagedLoras();
    });
    visibilityFilter?.addEventListener('change', () => void loadManagedLoras());
    visibilitySearch?.addEventListener('input', renderManagedLoras);
    page.querySelector('[data-lora-visibility-close]')?.addEventListener('click', () =>
        closeDialog(visibilityDialog),
    );

    void loadOptions();

    return {
        getSelectedModelFamilyId: () => selectedItem('lora')?.modelFamilyId ?? null,
        getSelectedLoraId: () => selectedItem('lora')?.id ?? null,
        setModelFamilies: (families) => {
            state.modelFamilies = families;
            if (familyFilter instanceof HTMLSelectElement) {
                const current = familyFilter.value;
                familyFilter.replaceChildren(
                    Object.assign(documentObject.createElement('option'), {
                        value: '',
                        textContent: 'すべての系統',
                    }),
                    ...families.map((family) =>
                        Object.assign(documentObject.createElement('option'), {
                            value: String(family.id),
                            textContent: family.name,
                        }),
                    ),
                );
                familyFilter.value = current;
            }
            render();
            void Promise.all(
                families.map(async (family) => {
                    const response = await fetcher(
                        `${page.dataset.modelFamiliesUrl}/${family.id}/generation-setting-options`,
                        { headers: { Accept: 'application/json' } },
                    );
                    if (!response.ok) {
                        throw new Error();
                    }
                    return (await response.json()).options;
                }),
            )
                .then((groups) => {
                    state.generationSettingOptions = groups.flat();
                })
                .catch(() => {
                    state.generationSettingOptions = [];
                    loadStatus.textContent = '生成設定を読み込めませんでした。';
                });
        },
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
        getSelectionSnapshot: () => ({
            loraId: state.selectedLoraId,
            strength: Number(strengthSelect?.value ?? 1),
            triggerId: state.selectedTriggerId,
            outfitId: state.selectedOutfitId,
        }),
        getCharacterLoraApplication: () => {
            const lora = selectedItem('lora');
            if (!state.loaded || lora === null) {
                return null;
            }
            const trigger = selectedItem('trigger');
            const strength = Number(strengthSelect?.value ?? 1);
            const loraSection =
                createLoraPromptSections({ lora, strength, trigger: null, outfit: null })[0] ?? '';
            return {
                options: {
                    loras: state.loras.map((item) => ({ ...item })),
                    triggers: state.triggers.map((item) => ({ ...item })),
                },
                selection: {
                    loraId: lora.id,
                    strength,
                    triggerId: trigger?.id ?? null,
                },
                selectedSections: {
                    lora: loraSection,
                    trigger: trigger?.content ?? '',
                },
                summary: `${lora.name} / ${trigger?.name ?? 'トリガーなし'} / 強度${strength}`,
            };
        },
        hasSelectionIds: (snapshot) => {
            const lora = state.loras.find((item) => item.id === snapshot?.loraId) ?? null;
            const strength = Number(snapshot?.strength ?? 1);
            const validStrength =
                Number.isFinite(strength) &&
                strength >= 0 &&
                strength <= 1 &&
                Math.abs(strength * 10 - Math.round(strength * 10)) < Number.EPSILON;
            const trigger = state.triggers.find(
                (item) => item.id === snapshot?.triggerId && item.loraId === lora?.id,
            );
            const outfit = state.outfits.find((item) => item.id === snapshot?.outfitId);
            return (
                validStrength &&
                (snapshot?.loraId === null || snapshot?.loraId === undefined || lora !== null) &&
                (snapshot?.triggerId === null ||
                    snapshot?.triggerId === undefined ||
                    trigger !== undefined) &&
                (snapshot?.outfitId === null ||
                    snapshot?.outfitId === undefined ||
                    outfit !== undefined)
            );
        },
        getVariantCandidates: () =>
            state.loaded
                ? {
                      loras: state.loras.map((lora) => ({
                          ...lora,
                          modelFamilyName:
                              state.modelFamilies.find(
                                  (family) => family.id === (lora.modelFamilyId ?? 1),
                              )?.name ?? 'Illustrious',
                      })),
                      triggers: [...state.triggers],
                  }
                : null,
        restoreSelection: (snapshot) => {
            const lora = state.loras.find((item) => item.id === snapshot?.loraId) ?? null;
            state.selectedLoraId = lora?.id ?? null;
            const trigger = state.triggers.find(
                (item) => item.id === snapshot?.triggerId && item.loraId === lora?.id,
            );
            const outfit = state.outfits.find((item) => item.id === snapshot?.outfitId);
            state.selectedTriggerId = trigger?.id ?? null;
            state.selectedOutfitId = outfit?.id ?? null;
            render();
            if (lora !== null && strengthSelect instanceof HTMLSelectElement) {
                strengthSelect.value = String(snapshot.strength);
                if (strengthSelect.value === '') {
                    strengthSelect.value = String(lora.recommendedStrength);
                }
                onSidebarSnapshotChange(getSidebarSnapshot());
            }
            return (
                (snapshot?.loraId === null || snapshot?.loraId === undefined || lora !== null) &&
                (snapshot?.triggerId === null ||
                    snapshot?.triggerId === undefined ||
                    trigger !== undefined) &&
                (snapshot?.outfitId === null ||
                    snapshot?.outfitId === undefined ||
                    outfit !== undefined)
            );
        },
        reset: () => {
            state.selectedLoraId = null;
            state.selectedTriggerId = null;
            state.selectedOutfitId = null;
            if (searchInput instanceof HTMLInputElement) {
                searchInput.value = '';
            }
            if (familyFilter instanceof HTMLSelectElement) {
                familyFilter.value = '';
            }
            state.loraPage = 1;
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
