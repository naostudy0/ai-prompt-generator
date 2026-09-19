import {
    createOptionSections,
    deleteOption,
    filterOptionsKeepingSelection,
    moveOption,
    moveOptionGroup,
    requestPromptOptions,
    saveOption,
    saveOptionGroup,
} from './prompt-categories.js';

export const initializePromptCategoriesPage = ({
    page,
    documentObject,
    fetcher,
    csrfToken,
    onLoadedChange,
    notify,
}) => {
    const view = documentObject.defaultView;
    const root = page.querySelector('[data-prompt-categories]');
    if (!view || !(root instanceof view.HTMLElement)) {
        onLoadedChange(true);
        return { getSections: () => ({ optionGroups: [] }), reset: () => {} };
    }

    const { HTMLDialogElement } = view;
    const status = root.querySelector('[data-category-load-status]');
    const retry = root.querySelector('[data-category-retry]');
    const groupsTarget = root.querySelector('[data-option-groups]');
    const itemDialog = page.querySelector('[data-category-dialog]');
    const itemForm = page.querySelector('[data-category-form]');
    const deleteDialog = page.querySelector('[data-category-delete-dialog]');
    const deleteForm = page.querySelector('[data-category-delete-form]');
    const manageDialog = page.querySelector('[data-category-manage-dialog]');
    const groupDialog = page.querySelector('[data-option-group-dialog]');
    const groupForm = page.querySelector('[data-option-group-form]');
    let groups = [];
    let selectedIds = new Set();
    const searches = new Map();
    const groupScrollPositions = new Map();
    let loaded = false;
    let busy = false;
    let editingGroupId = null;
    let editingItemId = null;
    let itemGroupId = null;
    let deletingItemId = null;
    let managingItemId = null;
    let draggedGroupId = null;
    let draggedItemId = null;

    const normalizeSingleSelections = (preferredSelections = new Map()) => {
        groups
            .filter((group) => group.selectionMode === 'single')
            .forEach((group) => {
                const selected = group.options.filter((option) => selectedIds.has(option.id));
                if (selected.length < 2) {
                    return;
                }
                const preferredId = preferredSelections.get(group.id);
                const retained = selected.some((option) => option.id === preferredId)
                    ? preferredId
                    : selected[0].id;
                selected.forEach((option) => {
                    if (option.id !== retained) {
                        selectedIds.delete(option.id);
                    }
                });
            });
    };

    const close = (dialog) => dialog instanceof HTMLDialogElement && dialog.close();
    const show = (dialog) => dialog instanceof HTMLDialogElement && dialog.showModal();
    const groupById = (id) => groups.find((group) => group.id === id) ?? null;
    const runMove = async (operation, successMessage, preferredSelections = new Map()) => {
        if (busy) {
            return;
        }
        busy = true;
        render();
        try {
            await operation();
            await load(preferredSelections);
            notify(successMessage);
        } catch {
            notify('並べ替えを保存できませんでした。');
            await load();
        } finally {
            busy = false;
            render();
        }
    };

    const moveGroupToIndex = (id, index) => {
        const without = groups.filter((group) => group.id !== id);
        const beforeId = without[index]?.id ?? null;
        void runMove(
            () =>
                moveOptionGroup(
                    fetcher,
                    page.dataset.promptOptionGroupsUrl,
                    csrfToken,
                    id,
                    beforeId,
                ),
            'オプションブロックを移動しました。',
        );
    };

    const moveItemToIndex = (id, groupId, index) => {
        const target = groupById(groupId)?.options.filter((option) => option.id !== id) ?? [];
        const beforeId = target[index]?.id ?? null;
        const preferredSelections = new Map();
        const selectedTarget = target.find((option) => selectedIds.has(option.id));
        if (selectedTarget !== undefined) {
            preferredSelections.set(groupId, selectedTarget.id);
        }
        void runMove(
            () =>
                moveOption(
                    fetcher,
                    page.dataset.promptOptionsUrl,
                    csrfToken,
                    id,
                    groupId,
                    beforeId,
                ),
            'オプション項目を移動しました。',
            preferredSelections,
        );
    };

    const selectItem = (group, id) => {
        if (selectedIds.has(id)) {
            selectedIds.delete(id);
        } else {
            if (group.selectionMode === 'single') {
                group.options.forEach((option) => selectedIds.delete(option.id));
            }
            selectedIds.add(id);
        }
        render();
    };

    const openManage = (option) => {
        managingItemId = option.id;
        page.querySelector('[data-category-manage-title]').textContent = option.name;
        show(manageDialog);
    };

    const startPointerMove = (event, type, id) => {
        if (busy || event.pointerType === 'mouse') {
            return;
        }
        event.preventDefault();
        const handle = event.currentTarget;
        handle.classList.add('is-dragging');
        let dropTarget = null;
        const clearDropTarget = () => {
            dropTarget?.classList.remove('is-drop-target');
            dropTarget = null;
        };
        const finish = (finishEvent) => {
            documentObject.removeEventListener('pointermove', move);
            documentObject.removeEventListener('pointerup', finish);
            documentObject.removeEventListener('pointercancel', cancel);
            handle.classList.remove('is-dragging');
            clearDropTarget();
            const target = documentObject.elementFromPoint?.(
                finishEvent.clientX,
                finishEvent.clientY,
            );
            const targetGroup = target?.closest?.('[data-group-id]');
            if (!(targetGroup instanceof view.HTMLElement)) {
                return;
            }
            const groupId = Number(targetGroup.dataset.groupId);
            if (type === 'group') {
                moveGroupToIndex(
                    id,
                    groups.findIndex((group) => group.id === groupId),
                );
                return;
            }
            const targetItem = target?.closest?.('[data-option-id]');
            const targetOptions = groupById(groupId)?.options ?? [];
            const index = targetItem
                ? targetOptions.findIndex(
                      (option) => option.id === Number(targetItem.dataset.optionId),
                  )
                : targetOptions.length;
            moveItemToIndex(id, groupId, index);
        };
        const cancel = () => {
            documentObject.removeEventListener('pointermove', move);
            documentObject.removeEventListener('pointerup', finish);
            documentObject.removeEventListener('pointercancel', cancel);
            handle.classList.remove('is-dragging');
            clearDropTarget();
        };
        const move = (moveEvent) => {
            const pointed = documentObject.elementFromPoint?.(moveEvent.clientX, moveEvent.clientY);
            const nextDropTarget = pointed?.closest?.(
                type === 'group' ? '[data-group-id]' : '[data-option-id], [data-group-id]',
            );
            if (nextDropTarget !== dropTarget) {
                clearDropTarget();
                dropTarget = nextDropTarget;
                dropTarget?.classList.add('is-drop-target');
            }
            const edge = 64;
            if (moveEvent.clientY < edge) {
                view.scrollBy({ top: -24, behavior: 'auto' });
            } else if (moveEvent.clientY > view.innerHeight - edge) {
                view.scrollBy({ top: 24, behavior: 'auto' });
            }
        };
        documentObject.addEventListener('pointermove', move);
        documentObject.addEventListener('pointerup', finish);
        documentObject.addEventListener('pointercancel', cancel);
    };

    const render = () => {
        groupsTarget.querySelectorAll('[data-group-id]').forEach((container) => {
            const options = container.querySelector('.badge-options');
            if (options instanceof view.HTMLElement) {
                groupScrollPositions.set(Number(container.dataset.groupId), options.scrollTop);
            }
        });
        groupsTarget.replaceChildren();
        groups.forEach((group, groupIndex) => {
            const container = documentObject.createElement('section');
            container.className = 'linked-option option-group';
            container.dataset.groupId = String(group.id);
            container.addEventListener('dragover', (event) => event.preventDefault());
            container.addEventListener('drop', (event) => {
                event.preventDefault();
                if (draggedItemId !== null) {
                    moveItemToIndex(draggedItemId, group.id, group.options.length);
                }
                if (draggedGroupId !== null) {
                    moveGroupToIndex(draggedGroupId, groupIndex);
                }
            });

            const heading = documentObject.createElement('div');
            heading.className =
                'subsection-heading subsection-heading--compact option-group__heading';
            const drag = documentObject.createElement('button');
            drag.type = 'button';
            drag.className = 'drag-handle';
            drag.textContent = '☰';
            drag.draggable = loaded && !busy;
            drag.setAttribute('aria-label', `${group.name}をドラッグして移動`);
            drag.addEventListener('dragstart', () => {
                draggedGroupId = group.id;
            });
            drag.addEventListener('dragend', () => {
                draggedGroupId = null;
            });
            drag.addEventListener('pointerdown', (event) =>
                startPointerMove(event, 'group', group.id),
            );
            const title = documentObject.createElement('h3');
            title.textContent = group.name;
            const mode = documentObject.createElement('span');
            mode.className = 'selection-mode';
            mode.textContent = group.selectionMode === 'single' ? '1つ選択' : '複数選択';
            const groupActions = documentObject.createElement('div');
            groupActions.className = 'list-actions';
            for (const [label, action, disabled] of [
                [
                    '上へ',
                    () => moveGroupToIndex(group.id, Math.max(0, groupIndex - 1)),
                    groupIndex === 0,
                ],
                [
                    '下へ',
                    () => moveGroupToIndex(group.id, groupIndex + 1),
                    groupIndex === groups.length - 1,
                ],
                ['編集', () => openGroupForm(group), false],
                ['項目追加', () => openItemForm(group.id), false],
            ]) {
                const button = documentObject.createElement('button');
                button.type = 'button';
                button.className = 'small-button';
                button.textContent = label;
                button.disabled = !loaded || busy || disabled;
                button.addEventListener('click', action);
                groupActions.append(button);
            }
            heading.append(drag, title, mode, groupActions);

            const search = documentObject.createElement('input');
            search.type = 'search';
            search.className = 'text-input';
            search.placeholder = `${group.name}を検索`;
            search.value = searches.get(group.id) ?? '';
            search.disabled = !loaded || busy;
            search.addEventListener('input', () => {
                searches.set(group.id, search.value);
                render();
                const replacement = groupsTarget.querySelector(
                    `[data-group-id="${group.id}"] input[type="search"]`,
                );
                replacement?.focus();
                replacement?.setSelectionRange(search.value.length, search.value.length);
            });

            const badges = documentObject.createElement('div');
            badges.className = 'badge-options';
            filterOptionsKeepingSelection(group.options, search.value, selectedIds).forEach(
                (option) => {
                    const optionIndex = group.options.findIndex(
                        (candidate) => candidate.id === option.id,
                    );
                    const wrapper = documentObject.createElement('span');
                    wrapper.className = 'badge-option';
                    wrapper.dataset.optionId = String(option.id);
                    wrapper.addEventListener('dragover', (event) => event.preventDefault());
                    wrapper.addEventListener('drop', (event) => {
                        event.preventDefault();
                        event.stopPropagation();
                        if (draggedItemId !== null) {
                            moveItemToIndex(draggedItemId, group.id, optionIndex);
                        }
                    });
                    const itemDrag = documentObject.createElement('button');
                    itemDrag.type = 'button';
                    itemDrag.className = 'drag-handle drag-handle--item';
                    itemDrag.textContent = '⋮⋮';
                    itemDrag.draggable = loaded && !busy;
                    itemDrag.setAttribute('aria-label', `${option.name}をドラッグして移動`);
                    itemDrag.addEventListener('dragstart', () => {
                        draggedItemId = option.id;
                    });
                    itemDrag.addEventListener('dragend', () => {
                        draggedItemId = null;
                    });
                    itemDrag.addEventListener('pointerdown', (event) =>
                        startPointerMove(event, 'item', option.id),
                    );
                    const badge = documentObject.createElement('button');
                    badge.type = 'button';
                    badge.className = 'prompt-badge';
                    badge.textContent = `${selectedIds.has(option.id) ? '✓ ' : ''}${option.name}`;
                    badge.setAttribute(
                        'aria-pressed',
                        selectedIds.has(option.id) ? 'true' : 'false',
                    );
                    badge.disabled = !loaded || busy;
                    badge.addEventListener('click', () => selectItem(group, option.id));
                    const actions = documentObject.createElement('span');
                    actions.className = 'badge-option__actions';
                    const actionsToggle = documentObject.createElement('button');
                    actionsToggle.type = 'button';
                    actionsToggle.className = 'badge-manage-toggle';
                    actionsToggle.textContent = '⋯';
                    actionsToggle.setAttribute('aria-label', `${option.name}の管理メニュー`);
                    actionsToggle.disabled = !loaded || busy;
                    actionsToggle.addEventListener('click', () => openManage(option));
                    actions.append(actionsToggle);
                    wrapper.append(itemDrag, badge, actions);
                    badges.append(wrapper);
                },
            );
            container.append(heading, search, badges);
            groupsTarget.append(container);
            badges.scrollTop = groupScrollPositions.get(group.id) ?? 0;
        });
        root.querySelector('[data-option-group-add]').disabled = !loaded || busy;
    };

    const load = async (preferredSelections = new Map()) => {
        loaded = false;
        onLoadedChange(false);
        status.textContent = 'オプションを読み込んでいます。';
        retry.hidden = true;
        render();
        try {
            groups = await requestPromptOptions(fetcher, page.dataset.promptOptionsUrl);
            const validIds = new Set(
                groups.flatMap((group) => group.options.map((option) => option.id)),
            );
            selectedIds = new Set([...selectedIds].filter((id) => validIds.has(id)));
            normalizeSingleSelections(preferredSelections);
            loaded = true;
            status.textContent = '';
            onLoadedChange(true);
            render();
        } catch {
            status.textContent = 'オプションを読み込めませんでした。';
            retry.hidden = false;
            render();
        }
    };

    const openGroupForm = (group = null) => {
        editingGroupId = group?.id ?? null;
        page.querySelector('[data-option-group-dialog-title]').textContent =
            group === null ? 'オプションブロックを追加' : 'オプションブロックを編集';
        page.querySelector('[data-option-group-name]').value = group?.name ?? '';
        page.querySelector('[data-option-group-mode]').value = group?.selectionMode ?? 'multiple';
        page.querySelector('[data-option-group-status]').textContent = '';
        show(groupDialog);
    };
    const openItemForm = (groupId, option = null) => {
        itemGroupId = groupId;
        editingItemId = option?.id ?? null;
        page.querySelector('[data-category-dialog-title]').textContent = option
            ? '項目を編集'
            : '項目を追加';
        page.querySelector('[data-category-id]').value = option?.id ?? '';
        page.querySelector('[data-category-name]').value = option?.name ?? '';
        page.querySelector('[data-category-content]').value = option?.content ?? '';
        page.querySelector('[data-category-form-status]').textContent = '';
        show(itemDialog);
    };
    const openDelete = (option) => {
        deletingItemId = option.id;
        page.querySelector('[data-category-delete-message]').textContent =
            `「${option.name}」— ${option.content}（ID: ${option.id}）を削除します。`;
        show(deleteDialog);
    };

    groupForm.addEventListener('submit', (event) => {
        event.preventDefault();
        if (busy) {
            return;
        }
        busy = true;
        const editedGroup = editingGroupId === null ? null : groupById(editingGroupId);
        const selectionMode = page.querySelector('[data-option-group-mode]').value;
        const releasesSelections =
            editedGroup?.selectionMode === 'multiple' &&
            selectionMode === 'single' &&
            editedGroup.options.filter((option) => selectedIds.has(option.id)).length > 1;
        void saveOptionGroup(
            fetcher,
            page.dataset.promptOptionGroupsUrl,
            csrfToken,
            editingGroupId,
            {
                name: page.querySelector('[data-option-group-name]').value,
                selectionMode,
            },
        )
            .then(async () => {
                close(groupDialog);
                await load();
                notify(
                    releasesSelections
                        ? '単一選択へ変更し、先頭の選択だけを残しました。'
                        : 'オプションブロックを保存しました。',
                );
            })
            .catch(() => {
                page.querySelector('[data-option-group-status]').textContent =
                    '保存できませんでした。';
            })
            .finally(() => {
                busy = false;
                render();
            });
    });
    itemForm.addEventListener('submit', (event) => {
        event.preventDefault();
        if (busy) {
            return;
        }
        busy = true;
        void saveOption(fetcher, page.dataset.promptOptionsUrl, csrfToken, editingItemId, {
            name: page.querySelector('[data-category-name]').value,
            content: page.querySelector('[data-category-content]').value,
            ...(editingItemId === null ? { groupId: itemGroupId } : {}),
        })
            .then(async () => {
                close(itemDialog);
                await load();
                notify('オプション項目を保存しました。');
            })
            .catch(() => {
                page.querySelector('[data-category-form-status]').textContent =
                    '保存できませんでした。';
            })
            .finally(() => {
                busy = false;
                render();
            });
    });
    deleteForm.addEventListener('submit', (event) => {
        event.preventDefault();
        if (busy || deletingItemId === null) {
            return;
        }
        busy = true;
        void deleteOption(fetcher, page.dataset.promptOptionsUrl, csrfToken, deletingItemId)
            .then(async () => {
                selectedIds.delete(deletingItemId);
                close(deleteDialog);
                await load();
                notify('オプション項目を削除しました。');
            })
            .catch(() => {
                page.querySelector('[data-category-delete-status]').textContent =
                    '削除できませんでした。';
            })
            .finally(() => {
                busy = false;
                render();
            });
    });

    root.querySelector('[data-option-group-add]').addEventListener('click', () => openGroupForm());
    retry.addEventListener('click', () => void load());
    page.querySelector('[data-option-group-cancel]').addEventListener('click', () =>
        close(groupDialog),
    );
    page.querySelector('[data-category-cancel]').addEventListener('click', () => close(itemDialog));
    page.querySelector('[data-category-delete-cancel]').addEventListener('click', () =>
        close(deleteDialog),
    );
    page.querySelector('[data-category-manage-cancel]').addEventListener('click', () =>
        close(manageDialog),
    );
    page.querySelector('[data-category-manage-edit]').addEventListener('click', () => {
        const group = groups.find((candidate) =>
            candidate.options.some((option) => option.id === managingItemId),
        );
        const option = group?.options.find((candidate) => candidate.id === managingItemId);
        close(manageDialog);
        if (group !== undefined && option !== undefined) {
            openItemForm(group.id, option);
        }
    });
    page.querySelector('[data-category-manage-delete]').addEventListener('click', () => {
        const option = groups
            .flatMap((group) => group.options)
            .find((candidate) => candidate.id === managingItemId);
        close(manageDialog);
        if (option !== undefined) {
            openDelete(option);
        }
    });
    for (const dialog of [groupDialog, itemDialog, manageDialog, deleteDialog]) {
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog && !busy) {
                close(dialog);
            }
        });
        dialog.addEventListener('cancel', (event) => {
            if (busy) {
                event.preventDefault();
            }
        });
    }

    void load();
    return {
        getSections: () => ({ optionGroups: createOptionSections(groups, selectedIds) }),
        reset: () => {
            selectedIds.clear();
            searches.clear();
            groupScrollPositions.clear();
            render();
        },
    };
};
