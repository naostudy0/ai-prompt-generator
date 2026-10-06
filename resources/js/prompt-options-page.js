import {
    createOptionSections,
    changeOptionGroupCategory,
    deleteOptionCategory,
    deleteOption,
    filterOptionsKeepingSelection,
    moveOption,
    moveOptionGroup,
    moveOptionCategory,
    requestOptionCategories,
    requestPromptOptions,
    saveOption,
    saveOptionGroup,
    saveOptionCategory,
} from './prompt-categories.js';

export const initializePromptOptionsPage = ({
    page,
    documentObject,
    fetcher,
    csrfToken,
    onLoadedChange,
    onSidebarSnapshotChange = () => {},
    notify,
}) => {
    const view = documentObject.defaultView;
    const root = page.querySelector('[data-prompt-categories]');
    if (!view || !(root instanceof view.HTMLElement)) {
        onLoadedChange(true);
        return {
            getSections: () => ({ optionGroups: [] }),
            getSelectionSnapshot: () => [],
            restoreSelection: () => false,
            reset: () => {},
            reorderGroup: () => {},
        };
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
    const managementDialog = page.querySelector('[data-option-management-dialog]');
    const managementTarget = page.querySelector('[data-option-management-groups]');
    const categoryList = page.querySelector('[data-option-category-management-list]');
    const categoryForm = page.querySelector('[data-option-category-form]');
    const categoryIdInput = categoryForm?.querySelector('[data-option-category-id]');
    const categoryNameInput = categoryForm?.querySelector('[data-option-category-name]');
    const categoryFormStatus = categoryForm?.querySelector('[data-option-category-status]');
    let groups = [];
    let categories = [];
    let selectedIds = new Set();
    const searches = new Map();
    const groupScrollPositions = new Map();
    let loaded = false;
    let busy = false;
    let editingGroupId = null;
    let editingItemId = null;
    let itemGroupId = null;
    let selectItemAfterSave = false;
    let deletingItemId = null;
    let managingItemId = null;
    let draggedGroupId = null;
    let draggedItemId = null;
    let itemFormPagePosition = { left: 0, top: 0 };
    let returnToManagement = false;
    const expandedGroups = new Set();
    const expandedCategories = new Set();

    const currentPagePosition = () => {
        const scrollingElement = documentObject.scrollingElement ?? documentObject.documentElement;
        return { left: scrollingElement.scrollLeft, top: scrollingElement.scrollTop };
    };

    const restorePagePosition = ({ left, top }) => {
        const apply = () => {
            const scrollingElement =
                documentObject.scrollingElement ?? documentObject.documentElement;
            scrollingElement.scrollLeft = left;
            scrollingElement.scrollTop = top;
        };
        apply();
        view.requestAnimationFrame?.(() => {
            apply();
            view.requestAnimationFrame?.(apply);
        });
        view.setTimeout(apply, 0);
        view.setTimeout(apply, 50);
    };

    const getSidebarSnapshot = () => ({
        navigationItems: loaded
            ? groups.map((group) => ({
                  key: `option-group-${group.id}`,
                  label: group.name,
                  target: `[data-option-group-id="${group.id}"]`,
                  categoryKey:
                      group.categoryId === null || group.categoryId === undefined
                          ? 'option-category-uncategorized'
                          : `option-category-${group.categoryId}`,
                  categoryLabel:
                      group.categoryId === null || group.categoryId === undefined
                          ? '未分類'
                          : (categories.find((category) => category.id === group.categoryId)
                                ?.name ?? '未分類'),
                  categoryId: group.categoryId ?? null,
              }))
            : [],
        selectionGroups: groups.map((group) => ({
            key: `option-group-${group.id}`,
            label: group.name,
            items: group.options
                .filter((option) => selectedIds.has(option.id))
                .map((option) => ({
                    label: option.name,
                    meta: '',
                    details: [],
                    onRemove: () => {
                        selectedIds.delete(option.id);
                        render();
                    },
                })),
        })),
    });

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
        const pagePosition = currentPagePosition();
        if (selectedIds.has(id)) {
            selectedIds.delete(id);
        } else {
            if (group.selectionMode === 'single') {
                group.options.forEach((option) => selectedIds.delete(option.id));
            }
            selectedIds.add(id);
        }
        render();
        restorePagePosition(pagePosition);
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

    const renderSelectionGroup = (group) => {
        const details = documentObject.createElement('details');
        details.className = 'linked-option option-group option-group--selection';
        details.dataset.groupId = String(group.id);
        details.dataset.optionGroupId = String(group.id);
        details.open = expandedGroups.has(group.id);
        const marker = documentObject.createElement('span');
        marker.className = 'section-disclosure-marker';
        marker.setAttribute('aria-hidden', 'true');
        const updateMarker = () => {
            marker.textContent = details.open ? '▼' : '▶';
        };
        updateMarker();
        details.addEventListener('toggle', () => {
            if (details.open) {
                expandedGroups.add(group.id);
            } else {
                expandedGroups.delete(group.id);
            }
            updateMarker();
        });
        const selected = group.options.filter((option) => selectedIds.has(option.id));
        const summary = documentObject.createElement('summary');
        const title = documentObject.createElement('span');
        title.className = 'option-group__title';
        title.textContent = group.name;
        const selectedSummary = documentObject.createElement('span');
        selectedSummary.className = 'option-group__selected-summary';
        selectedSummary.textContent =
            selected.length > 0 ? selected.map((option) => option.name).join('、') : '未選択';
        summary.append(marker, title, selectedSummary);
        const actions = documentObject.createElement('div');
        actions.className = 'option-group--selection__actions';
        const addButton = documentObject.createElement('button');
        addButton.type = 'button';
        addButton.className = 'small-button';
        addButton.textContent = 'タグを追加';
        addButton.addEventListener('click', () => openItemForm(group.id, null, true));
        actions.append(addButton);
        const search = documentObject.createElement('input');
        search.type = 'search';
        search.className = 'text-input';
        search.placeholder = `${group.name}を検索`;
        search.value = searches.get(group.id) ?? '';
        search.addEventListener('input', () => {
            searches.set(group.id, search.value);
            expandedGroups.add(group.id);
            render();
            const replacement = groupsTarget.querySelector(
                `[data-group-id="${group.id}"] input[type="search"]`,
            );
            replacement?.focus({ preventScroll: true });
            replacement?.setSelectionRange(search.value.length, search.value.length);
        });
        const badges = documentObject.createElement('div');
        badges.className = 'badge-options';
        filterOptionsKeepingSelection(group.options, search.value, selectedIds).forEach(
            (option) => {
                const badge = documentObject.createElement('button');
                badge.type = 'button';
                badge.className = 'prompt-badge';
                badge.textContent = `${selectedIds.has(option.id) ? '✓ ' : ''}${option.name}`;
                badge.setAttribute('aria-pressed', selectedIds.has(option.id) ? 'true' : 'false');
                badge.addEventListener('click', () => selectItem(group, option.id));
                badges.append(badge);
            },
        );
        details.append(summary, actions, search, badges);
        return details;
    };

    const renderSelectionView = () => {
        groupsTarget.replaceChildren();
        const sections = [
            ...categories.map((category) => ({
                ...category,
                groups: groups.filter((group) => group.categoryId === category.id),
            })),
            {
                id: null,
                name: '未分類',
                groups: groups.filter(
                    (group) => group.categoryId === null || group.categoryId === undefined,
                ),
            },
        ].filter((category) => category.groups.length > 0);
        sections.forEach((category) => {
            const categoryKey = category.id === null ? 'uncategorized' : String(category.id);
            const details = documentObject.createElement('details');
            details.className = 'option-category-section';
            details.dataset.optionCategoryId = categoryKey;
            details.open = expandedCategories.has(categoryKey);
            details.addEventListener('toggle', () => {
                if (details.open) {
                    expandedCategories.add(categoryKey);
                } else {
                    expandedCategories.delete(categoryKey);
                }
            });
            const summary = documentObject.createElement('summary');
            const marker = documentObject.createElement('span');
            marker.className = 'category-disclosure-marker';
            marker.setAttribute('aria-hidden', 'true');
            const updateMarker = () => {
                marker.textContent = details.open ? '▼' : '▶';
            };
            const title = documentObject.createElement('span');
            title.className = 'category-disclosure-label';
            title.textContent = category.name;
            summary.append(marker, title);
            updateMarker();
            details.addEventListener('toggle', updateMarker);
            details.append(summary, ...category.groups.map(renderSelectionGroup));
            groupsTarget.append(details);
            details.querySelectorAll('[data-group-id]').forEach((groupDetails) => {
                groupDetails.querySelector('.badge-options').scrollTop =
                    groupScrollPositions.get(Number(groupDetails.dataset.groupId)) ?? 0;
            });
        });
    };

    const renderCategoryManagement = () => {
        if (!categoryList) {
            return;
        }
        categoryList.replaceChildren();
        categories.forEach((category, index) => {
            const row = documentObject.createElement('div');
            row.className = 'option-category-management-row';
            const name = documentObject.createElement('strong');
            name.className = 'option-category-management-row__name';
            name.textContent = category.name;
            const count = documentObject.createElement('span');
            count.className = 'option-category-management-row__count';
            count.textContent = `${groups.filter((group) => group.categoryId === category.id).length}ブロック`;
            const actions = documentObject.createElement('div');
            actions.className = 'list-actions option-category-management-row__actions';
            const operations = [
                ['上へ', () => moveCategory(category.id, Math.max(0, index - 1)), index === 0],
                [
                    '下へ',
                    () => moveCategory(category.id, index + 1),
                    index === categories.length - 1,
                ],
                ['編集', () => editCategory(category), false],
                ['削除', () => deleteCategory(category), false],
            ];
            operations.forEach(([label, action, disabled]) => {
                const button = documentObject.createElement('button');
                button.type = 'button';
                button.className =
                    label === '削除' ? 'small-button small-button--danger' : 'small-button';
                button.textContent = label;
                button.disabled = busy || disabled;
                button.addEventListener('click', action);
                actions.append(button);
            });
            row.append(name, count, actions);
            categoryList.append(row);
        });
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
            container.dataset.optionGroupId = String(group.id);
            container.tabIndex = -1;
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
                replacement?.focus({ preventScroll: true });
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
        if (managementTarget) {
            managementTarget.replaceChildren(...groupsTarget.children);
            managementTarget.querySelectorAll('[data-group-id]').forEach((container) => {
                const group = groupById(Number(container.dataset.groupId));
                if (!group) {
                    return;
                }
                const select = documentObject.createElement('select');
                select.className = 'text-input option-group-category-select';
                select.setAttribute('aria-label', `${group.name}のカテゴリ`);
                const uncategorized = documentObject.createElement('option');
                uncategorized.value = '';
                uncategorized.textContent = '未分類';
                select.append(
                    uncategorized,
                    ...categories.map((category) => {
                        const option = documentObject.createElement('option');
                        option.value = String(category.id);
                        option.textContent = category.name;
                        return option;
                    }),
                );
                select.value =
                    group.categoryId === null || group.categoryId === undefined
                        ? ''
                        : String(group.categoryId);
                select.addEventListener(
                    'change',
                    () =>
                        void assignCategory(
                            group.id,
                            select.value === '' ? null : Number(select.value),
                        ),
                );
                container.querySelector('.option-group__heading')?.after(select);
                container.querySelectorAll('[data-option-id]').forEach((item) => {
                    const itemId = Number(item.dataset.optionId);
                    const itemIndex = group.options.findIndex((option) => option.id === itemId);
                    const movement = documentObject.createElement('span');
                    movement.className = 'badge-option__management-movement';
                    const destination = documentObject.createElement('select');
                    destination.className = 'text-input';
                    destination.setAttribute(
                        'aria-label',
                        `${group.options[itemIndex]?.name ?? ''}の移動先ブロック`,
                    );
                    destination.append(
                        ...groups.map((candidate) => {
                            const option = documentObject.createElement('option');
                            option.value = String(candidate.id);
                            option.textContent = candidate.name;
                            return option;
                        }),
                    );
                    destination.value = String(group.id);
                    destination.addEventListener('change', () => {
                        moveItemToIndex(
                            itemId,
                            Number(destination.value),
                            groupById(Number(destination.value))?.options.length ?? 0,
                        );
                    });
                    const edit = documentObject.createElement('button');
                    edit.type = 'button';
                    edit.className = 'small-button option-item-icon-button';
                    edit.textContent = '✎';
                    edit.title = `${group.options[itemIndex]?.name ?? ''}を編集`;
                    edit.setAttribute('aria-label', edit.title);
                    edit.addEventListener('click', () =>
                        openItemForm(group.id, group.options[itemIndex]),
                    );
                    const remove = documentObject.createElement('button');
                    remove.type = 'button';
                    remove.className = 'small-button small-button--danger option-item-icon-button';
                    remove.textContent = '×';
                    remove.title = `${group.options[itemIndex]?.name ?? ''}を削除`;
                    remove.setAttribute('aria-label', remove.title);
                    remove.addEventListener('click', () => {
                        returnToManagement = true;
                        close(managementDialog);
                        openDelete(group.options[itemIndex]);
                    });
                    item.querySelector('.badge-option__actions')?.remove();
                    movement.append(destination, edit, remove);
                    item.append(movement);
                });
            });
        }
        renderSelectionView();
        renderCategoryManagement();
        onSidebarSnapshotChange(getSidebarSnapshot());
    };

    const load = async (preferredSelections = new Map()) => {
        loaded = false;
        onLoadedChange(false);
        status.textContent = 'オプションを読み込んでいます。';
        retry.hidden = true;
        render();
        try {
            [groups, categories] = await Promise.all([
                requestPromptOptions(fetcher, page.dataset.promptOptionsUrl),
                page.dataset.promptOptionCategoriesUrl
                    ? requestOptionCategories(fetcher, page.dataset.promptOptionCategoriesUrl)
                    : Promise.resolve([]),
            ]);
            if (expandedCategories.size === 0) {
                categories.forEach((category) => expandedCategories.add(String(category.id)));
                if (
                    groups.some(
                        (group) => group.categoryId === null || group.categoryId === undefined,
                    )
                ) {
                    expandedCategories.add('uncategorized');
                }
            }
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
        returnToManagement = managementDialog?.open === true;
        if (returnToManagement) {
            close(managementDialog);
        }
        editingGroupId = group?.id ?? null;
        page.querySelector('[data-option-group-dialog-title]').textContent =
            group === null ? 'オプションブロックを追加' : 'オプションブロックを編集';
        page.querySelector('[data-option-group-name]').value = group?.name ?? '';
        page.querySelector('[data-option-group-mode]').value = group?.selectionMode ?? 'multiple';
        page.querySelector('[data-option-group-status]').textContent = '';
        show(groupDialog);
    };
    const openItemForm = (groupId, option = null, selectAfterSave = false) => {
        returnToManagement = managementDialog?.open === true;
        if (returnToManagement) {
            close(managementDialog);
        }
        itemFormPagePosition = currentPagePosition();
        itemGroupId = groupId;
        selectItemAfterSave = selectAfterSave;
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

    const reloadOptionManagementData = async () => {
        categories = await requestOptionCategories(fetcher, page.dataset.promptOptionCategoriesUrl);
        groups = await requestPromptOptions(fetcher, page.dataset.promptOptionsUrl);
        render();
    };
    const editCategory = (category) => {
        categoryIdInput.value = String(category.id);
        categoryNameInput.value = category.name;
        categoryNameInput.focus();
    };
    const clearCategoryForm = () => {
        categoryIdInput.value = '';
        categoryNameInput.value = '';
        categoryFormStatus.textContent = '';
    };
    const moveCategory = async (id, index) => {
        const without = categories.filter((category) => category.id !== id);
        const beforeCategoryId = without[index]?.id ?? null;
        busy = true;
        render();
        try {
            await moveOptionCategory(
                fetcher,
                page.dataset.promptOptionCategoriesUrl,
                csrfToken,
                id,
                beforeCategoryId,
            );
        } catch {
            notify('カテゴリを移動できませんでした。');
            busy = false;
            render();
            return;
        }
        try {
            await reloadOptionManagementData();
            notify('カテゴリを移動しました。');
        } catch {
            notify('カテゴリは移動済みですが、一覧を更新できません。再読み込みしてください。');
        } finally {
            busy = false;
            render();
        }
    };
    const deleteCategory = async (category) => {
        if (!view.confirm(`「${category.name}」を削除します。所属ブロックは未分類へ移動します。`)) {
            return;
        }
        busy = true;
        render();
        try {
            await deleteOptionCategory(
                fetcher,
                page.dataset.promptOptionCategoriesUrl,
                csrfToken,
                category.id,
            );
        } catch {
            notify('カテゴリを削除できませんでした。');
            busy = false;
            render();
            return;
        }
        try {
            await reloadOptionManagementData();
            notify('カテゴリを削除し、所属ブロックを未分類へ移動しました。');
        } catch {
            notify('カテゴリは削除済みですが、一覧を更新できません。再読み込みしてください。');
        } finally {
            busy = false;
            render();
        }
    };
    const assignCategory = async (groupId, categoryId) => {
        busy = true;
        render();
        try {
            await changeOptionGroupCategory(
                fetcher,
                page.dataset.promptOptionGroupsUrl,
                csrfToken,
                groupId,
                categoryId,
            );
            try {
                await reloadOptionManagementData();
                notify('ブロックのカテゴリを変更しました。');
            } catch {
                notify(
                    'ブロックのカテゴリは変更済みですが、一覧を更新できません。再読み込みしてください。',
                );
            }
        } catch {
            notify('カテゴリを変更できませんでした。');
        } finally {
            busy = false;
            render();
        }
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
                if (returnToManagement) {
                    show(managementDialog);
                    returnToManagement = false;
                }
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
            .then(async (savedOption) => {
                if (
                    editingItemId === null &&
                    selectItemAfterSave &&
                    Number.isInteger(savedOption?.id)
                ) {
                    const targetGroup = groupById(itemGroupId);
                    if (targetGroup?.selectionMode === 'single') {
                        targetGroup.options.forEach((option) => selectedIds.delete(option.id));
                    }
                    selectedIds.add(savedOption.id);
                }
                close(itemDialog);
                await load();
                selectItemAfterSave = false;
                if (returnToManagement) {
                    show(managementDialog);
                    returnToManagement = false;
                }
                notify('オプション項目を保存しました。');
            })
            .catch(() => {
                page.querySelector('[data-category-form-status]').textContent =
                    '保存できませんでした。';
            })
            .finally(() => {
                busy = false;
                render();
                restorePagePosition(itemFormPagePosition);
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
                if (returnToManagement) {
                    show(managementDialog);
                    returnToManagement = false;
                }
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
    page.querySelector('[data-management-group-add]')?.addEventListener('click', () =>
        openGroupForm(),
    );
    page.querySelector('[data-option-category-cancel]')?.addEventListener(
        'click',
        clearCategoryForm,
    );
    categoryForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (busy) {
            return;
        }
        const idValue = categoryIdInput.value;
        busy = true;
        render();
        try {
            await saveOptionCategory(
                fetcher,
                page.dataset.promptOptionCategoriesUrl,
                csrfToken,
                idValue === '' ? null : Number(idValue),
                categoryNameInput.value,
            );
        } catch {
            categoryFormStatus.textContent = '保存できませんでした。';
            busy = false;
            render();
            return;
        }
        clearCategoryForm();
        try {
            await reloadOptionManagementData();
            notify('カテゴリを保存しました。');
        } catch {
            notify('カテゴリは保存済みですが、一覧を更新できません。再読み込みしてください。');
        } finally {
            busy = false;
            render();
        }
    });
    retry.addEventListener('click', () => void load());
    const closeEditorAndReturn = (dialog) => {
        close(dialog);
        if (returnToManagement) {
            show(managementDialog);
            returnToManagement = false;
        }
    };
    page.querySelector('[data-option-group-cancel]').addEventListener('click', () =>
        closeEditorAndReturn(groupDialog),
    );
    page.querySelector('[data-category-cancel]').addEventListener('click', () => {
        selectItemAfterSave = false;
        closeEditorAndReturn(itemDialog);
    });
    page.querySelector('[data-category-delete-cancel]').addEventListener('click', () =>
        closeEditorAndReturn(deleteDialog),
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
    for (const dialog of [groupDialog, itemDialog, manageDialog, deleteDialog, managementDialog]) {
        if (!(dialog instanceof HTMLDialogElement)) {
            continue;
        }
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
        reorderGroup: (id, beforeId) => {
            if (!loaded || busy || id === beforeId || !groupById(id)) {
                return;
            }
            const without = groups.filter((group) => group.id !== id);
            const index =
                beforeId === null
                    ? without.length
                    : without.findIndex((group) => group.id === beforeId);
            if (index < 0 || index === groups.findIndex((group) => group.id === id)) {
                return;
            }
            moveGroupToIndex(id, index);
        },
        changeGroupCategory: (id, categoryId) => {
            if (!loaded || busy || !groupById(id)) {
                return;
            }
            void assignCategory(id, categoryId);
        },
        getSections: () => ({ optionGroups: createOptionSections(groups, selectedIds) }),
        getSelectionSnapshot: () => [...selectedIds],
        restoreSelection: (snapshot) => {
            const requested = new Set(Array.isArray(snapshot) ? snapshot : []);
            const available = new Set(
                groups.flatMap((group) => group.options.map((item) => item.id)),
            );
            selectedIds = new Set([...requested].filter((id) => available.has(id)));
            normalizeSingleSelections();
            render();
            return [...requested].every((id) => selectedIds.has(id));
        },
        reset: () => {
            selectedIds.clear();
            searches.clear();
            groupScrollPositions.clear();
            render();
        },
    };
};
