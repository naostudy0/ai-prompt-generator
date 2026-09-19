import {
    createClothingLoraSections,
    deleteClothingLora,
    deleteClothingLoraTrigger,
    filterClothingLoras,
    requestClothingLoraOptions,
    saveClothingLora,
    saveClothingLoraTrigger,
} from './clothing-lora-options.js';

export const initializeClothingLoraOptionsPage = ({
    page,
    documentObject,
    fetcher,
    csrfToken,
    onLoadedChange,
    onSidebarSnapshotChange = () => {},
    notify,
}) => {
    const view = documentObject.defaultView;
    const root = page.querySelector('[data-clothing-lora-options]');
    if (!view || !(root instanceof view.HTMLElement)) {
        onLoadedChange(true);
        return {
            getSelections: () => ({ clothingLoras: '', clothingLoraTriggers: '' }),
            reset: () => {},
        };
    }

    const { HTMLDialogElement, HTMLInputElement } = view;
    const search = root.querySelector('[data-clothing-lora-search]');
    const list = root.querySelector('[data-clothing-lora-list]');
    const status = root.querySelector('[data-clothing-lora-status]');
    const retry = root.querySelector('[data-clothing-lora-retry]');
    const dialog = page.querySelector('[data-clothing-option-dialog]');
    const deleteDialog = page.querySelector('[data-clothing-delete-dialog]');
    const state = {
        loras: [],
        triggers: [],
        selections: new Map(),
        editingType: null,
        editingId: null,
        editingLoraId: null,
        deletingType: null,
        deletingId: null,
        loaded: false,
        busy: false,
    };
    const endpoints = {
        lora: page.dataset.clothingLorasUrl,
        trigger: page.dataset.clothingLoraTriggersUrl,
    };

    const getSidebarSnapshot = () => ({
        navigationItems: [
            {
                key: 'clothing-lora',
                label: '衣装LoRA',
                target: '[data-clothing-lora-options]',
            },
        ],
        selectionGroups: [
            {
                key: 'clothing-lora',
                label: '衣装LoRA',
                items: state.loras
                    .filter((lora) => state.selections.has(lora.id))
                    .map((lora) => {
                        const selected = state.selections.get(lora.id);
                        const trigger = state.triggers.find(
                            (candidate) => candidate.id === selected.triggerId,
                        );
                        return {
                            label: lora.name,
                            meta: `強度 ${selected.strength}`,
                            details: trigger === undefined ? [] : [`トリガー：${trigger.name}`],
                        };
                    }),
            },
        ],
    });

    const closeDialog = (target) => {
        if (target instanceof HTMLDialogElement) {
            target.close();
        }
    };
    const showDialog = (target) => {
        if (target instanceof HTMLDialogElement) {
            target.showModal();
        }
    };
    const closeFromBackdrop = (event, target) => {
        if (state.busy || !(target instanceof HTMLDialogElement) || event.target !== target) {
            return;
        }
        const bounds = target.getBoundingClientRect();
        if (
            event.clientX < bounds.left ||
            event.clientX > bounds.right ||
            event.clientY < bounds.top ||
            event.clientY > bounds.bottom
        ) {
            closeDialog(target);
        }
    };

    const openEditor = (type, loraId = null, item = null) => {
        state.editingType = type;
        state.editingId = item?.id ?? null;
        state.editingLoraId = loraId;
        page.querySelector('[data-clothing-option-title]').textContent =
            `${type === 'lora' ? '衣装LoRA' : 'トリガー'}を${item === null ? '追加' : '編集'}`;
        page.querySelector(
            type === 'lora' ? '[data-clothing-lora-name]' : '[data-clothing-trigger-name]',
        ).value = item?.name ?? '';
        page.querySelector('[data-clothing-option-file-name]').value = item?.fileName ?? '';
        page.querySelector('[data-clothing-option-strength]').value = String(
            item?.recommendedStrength ?? 1,
        );
        page.querySelector('[data-clothing-option-content]').value = item?.content ?? '';
        page.querySelector('[data-clothing-lora-fields]').hidden = type !== 'lora';
        page.querySelector('[data-clothing-trigger-fields]').hidden = type !== 'trigger';
        page.querySelector('[data-clothing-option-status]').textContent = '';
        showDialog(dialog);
    };

    const openDelete = (type, item) => {
        state.deletingType = type;
        state.deletingId = item.id;
        const effect = type === 'lora' ? '所属するトリガーも削除されます。' : '';
        page.querySelector('[data-clothing-delete-message]').textContent =
            `${type === 'lora' ? '衣装LoRA' : 'トリガー'}「${item.name}」を削除します。${effect}`;
        page.querySelector('[data-clothing-delete-status]').textContent = '';
        showDialog(deleteDialog);
    };

    const render = () => {
        const ids = new Set(state.selections.keys());
        const visible = filterClothingLoras(
            state.loras,
            search instanceof HTMLInputElement ? search.value : '',
            ids,
        );
        list.replaceChildren();
        visible.forEach((lora) => {
            const selected = state.selections.get(lora.id) ?? null;
            const card = documentObject.createElement('article');
            card.className = 'clothing-lora-card';
            if (selected !== null) {
                card.classList.add('is-selected');
            }
            const header = documentObject.createElement('div');
            header.className = 'clothing-lora-card__header';
            const toggle = documentObject.createElement('button');
            toggle.type = 'button';
            toggle.className = 'clothing-lora-toggle';
            toggle.setAttribute('aria-pressed', selected === null ? 'false' : 'true');
            toggle.disabled = !state.loaded || state.busy;
            toggle.textContent = `${selected === null ? '' : '✓ '}${lora.name} — ${lora.fileName}`;
            toggle.addEventListener('click', () => {
                if (selected === null) {
                    const firstTrigger = state.triggers.find(
                        (trigger) => trigger.loraId === lora.id,
                    );
                    state.selections.set(lora.id, {
                        strength: lora.recommendedStrength,
                        triggerId: firstTrigger?.id ?? null,
                    });
                } else {
                    state.selections.delete(lora.id);
                }
                render();
            });
            const actions = documentObject.createElement('div');
            actions.className = 'clothing-lora-card__actions';
            for (const [label, action, danger] of [
                ['編集', () => openEditor('lora', lora.id, lora), false],
                ['削除', () => openDelete('lora', lora), true],
            ]) {
                const button = documentObject.createElement('button');
                button.type = 'button';
                button.className = `small-button${danger ? ' small-button--danger' : ''}`;
                button.textContent = label;
                button.disabled = !state.loaded || state.busy;
                button.addEventListener('click', action);
                actions.append(button);
            }
            header.append(toggle, actions);
            card.append(header);

            if (selected !== null) {
                const details = documentObject.createElement('div');
                details.className = 'clothing-lora-card__details';
                const strength = documentObject.createElement('select');
                strength.className = 'text-input';
                strength.setAttribute('aria-label', `${lora.name}の強度`);
                for (let step = 0; step <= 10; step++) {
                    const option = documentObject.createElement('option');
                    option.value = String(step / 10);
                    option.textContent = step === 10 ? '1' : (step / 10).toFixed(1);
                    option.selected = step === Math.round(selected.strength * 10);
                    strength.append(option);
                }
                strength.addEventListener('change', () => {
                    selected.strength = Number(strength.value);
                    onSidebarSnapshotChange(getSidebarSnapshot());
                });
                strength.disabled = !state.loaded || state.busy;
                const triggerSelect = documentObject.createElement('select');
                triggerSelect.className = 'text-input';
                triggerSelect.setAttribute('aria-label', `${lora.name}のトリガー`);
                const empty = documentObject.createElement('option');
                empty.value = '';
                empty.textContent = 'トリガーを使わない';
                triggerSelect.append(empty);
                const triggers = state.triggers.filter((trigger) => trigger.loraId === lora.id);
                triggers.forEach((trigger) => {
                    const option = documentObject.createElement('option');
                    option.value = String(trigger.id);
                    option.textContent = `${trigger.name} — ${trigger.content}`;
                    option.selected = trigger.id === selected.triggerId;
                    triggerSelect.append(option);
                });
                triggerSelect.value = selected.triggerId === null ? '' : String(selected.triggerId);
                triggerSelect.addEventListener('change', () => {
                    selected.triggerId =
                        triggerSelect.value === '' ? null : Number(triggerSelect.value);
                    render();
                });
                triggerSelect.disabled = !state.loaded || state.busy;
                const triggerActions = documentObject.createElement('div');
                triggerActions.className = 'list-actions';
                const current =
                    triggers.find((trigger) => trigger.id === selected.triggerId) ?? null;
                for (const [label, action, disabled, danger] of [
                    ['トリガー追加', () => openEditor('trigger', lora.id), false, false],
                    [
                        '編集',
                        () => openEditor('trigger', lora.id, current),
                        current === null,
                        false,
                    ],
                    ['削除', () => openDelete('trigger', current), current === null, true],
                ]) {
                    const button = documentObject.createElement('button');
                    button.type = 'button';
                    button.className = `small-button${danger ? ' small-button--danger' : ''}`;
                    button.textContent = label;
                    button.disabled = disabled || !state.loaded || state.busy;
                    button.addEventListener('click', action);
                    triggerActions.append(button);
                }
                details.append(strength, triggerSelect, triggerActions);
                card.append(details);
            }
            list.append(card);
        });
        search.disabled = !state.loaded;
        root.querySelector('[data-clothing-lora-add]').disabled = !state.loaded || state.busy;
        onSidebarSnapshotChange(getSidebarSnapshot());
    };

    const load = async () => {
        state.loaded = false;
        onLoadedChange(false);
        status.textContent = '衣装LoRAを読み込んでいます。';
        retry.hidden = true;
        render();
        try {
            const result = await requestClothingLoraOptions(
                fetcher,
                page.dataset.clothingLoraOptionsUrl,
            );
            state.loras = result.loras;
            state.triggers = result.triggers;
            for (const id of state.selections.keys()) {
                if (!state.loras.some((lora) => lora.id === id)) {
                    state.selections.delete(id);
                }
            }
            state.loaded = true;
            status.textContent = '';
            render();
            onLoadedChange(true);
        } catch {
            status.textContent = '衣装LoRAを読み込めませんでした。';
            retry.hidden = false;
            render();
        }
    };

    page.querySelector('[data-clothing-option-form]')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const formStatus = page.querySelector('[data-clothing-option-status]');
        if (state.busy) {
            return;
        }
        const values =
            state.editingType === 'lora'
                ? {
                      name: page.querySelector('[data-clothing-lora-name]').value,
                      fileName: page.querySelector('[data-clothing-option-file-name]').value,
                      recommendedStrength: Number(
                          page.querySelector('[data-clothing-option-strength]').value,
                      ),
                  }
                : {
                      loraId: state.editingLoraId,
                      name: page.querySelector('[data-clothing-trigger-name]').value,
                      content: page.querySelector('[data-clothing-option-content]').value,
                  };
        formStatus.textContent = '保存しています。';
        state.busy = true;
        page.querySelector('[data-clothing-option-form] button[type="submit"]').disabled = true;
        try {
            const save = state.editingType === 'lora' ? saveClothingLora : saveClothingLoraTrigger;
            await save(fetcher, endpoints[state.editingType], csrfToken, state.editingId, values);
            closeDialog(dialog);
            notify('保存しました。');
            await load();
        } catch {
            formStatus.textContent = '保存できませんでした。入力内容を確認してください。';
            formStatus.dataset.state = 'error';
        } finally {
            state.busy = false;
            page.querySelector('[data-clothing-option-form] button[type="submit"]').disabled =
                false;
            render();
        }
    });
    page.querySelector('[data-clothing-delete-form]')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const deleteStatus = page.querySelector('[data-clothing-delete-status]');
        if (state.busy) {
            return;
        }
        deleteStatus.textContent = '削除しています。';
        state.busy = true;
        page.querySelector('[data-clothing-delete-form] button[type="submit"]').disabled = true;
        try {
            const deleteResource =
                state.deletingType === 'lora' ? deleteClothingLora : deleteClothingLoraTrigger;
            await deleteResource(
                fetcher,
                endpoints[state.deletingType],
                csrfToken,
                state.deletingId,
            );
            if (state.deletingType === 'lora') {
                state.selections.delete(state.deletingId);
            }
            closeDialog(deleteDialog);
            notify('削除しました。');
            await load();
        } catch {
            deleteStatus.textContent = '削除できませんでした。再度お試しください。';
            deleteStatus.dataset.state = 'error';
        } finally {
            state.busy = false;
            page.querySelector('[data-clothing-delete-form] button[type="submit"]').disabled =
                false;
            render();
        }
    });
    search?.addEventListener('input', render);
    retry?.addEventListener('click', load);
    root.querySelector('[data-clothing-lora-add]')?.addEventListener('click', () =>
        openEditor('lora'),
    );
    page.querySelector('[data-clothing-option-cancel]')?.addEventListener('click', () => {
        if (!state.busy) {
            closeDialog(dialog);
        }
    });
    page.querySelector('[data-clothing-delete-cancel]')?.addEventListener('click', () => {
        if (!state.busy) {
            closeDialog(deleteDialog);
        }
    });
    dialog?.addEventListener('click', (event) => closeFromBackdrop(event, dialog));
    deleteDialog?.addEventListener('click', (event) => closeFromBackdrop(event, deleteDialog));
    for (const target of [dialog, deleteDialog]) {
        target?.addEventListener('cancel', (event) => {
            if (state.busy) {
                event.preventDefault();
            }
        });
    }
    void load();

    return {
        getSelections: () => {
            const sections = createClothingLoraSections(
                state.loras,
                state.triggers,
                state.selections,
            );
            return {
                clothingLoras: sections.loras,
                clothingLoraTriggers: sections.triggers,
            };
        },
        reset: () => {
            state.selections.clear();
            if (search instanceof HTMLInputElement) {
                search.value = '';
            }
            render();
        },
    };
};
