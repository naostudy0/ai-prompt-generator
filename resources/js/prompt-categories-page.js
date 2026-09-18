import {
    createCategorySections,
    deleteNamedPrompt,
    filterOptionsKeepingSelection,
    requestPromptCategories,
    saveNamedPrompt,
} from './prompt-categories.js';
import { scrollToSelectionSection } from './selection-navigation.js';

const labels = {
    expression: '表情',
    gaze: '視線',
    action: '動作',
    location: '場所',
    composition: '構図',
    option: 'オプション',
};

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
        return {
            getSections: () => ({
                expression: '',
                gaze: '',
                action: '',
                location: '',
                composition: '',
                option: '',
            }),
        };
    }

    const { HTMLButtonElement, HTMLDialogElement, HTMLInputElement, HTMLSelectElement } = view;
    const status = root.querySelector('[data-category-load-status]');
    const retry = root.querySelector('[data-category-retry]');
    const dialog = page.querySelector('[data-category-dialog]');
    const form = page.querySelector('[data-category-form]');
    const deleteDialog = page.querySelector('[data-category-delete-dialog]');
    const deleteForm = page.querySelector('[data-category-delete-form]');
    const options = {
        expression: [],
        gaze: [],
        action: [],
        location: [],
        composition: [],
        option: [],
    };
    const selected = Object.fromEntries(Object.keys(options).map((type) => [type, new Set()]));
    const endpoints = {
        expression: page.dataset.expressionsUrl,
        gaze: page.dataset.gazesUrl,
        action: page.dataset.actionsUrl,
        location: page.dataset.locationsUrl,
        composition: page.dataset.compositionsUrl,
        option: page.dataset.promptOptionsUrl,
    };
    let loaded = false;
    let editingType = null;
    let deletingType = null;
    let deletingId = null;
    let saving = false;
    let deleting = false;

    const categoryRoot = (type) => root.querySelector(`[data-prompt-category="${type}"]`);
    const item = (type, id) => options[type].find((option) => option.id === id) ?? null;
    const selectedItem = (type) => item(type, [...selected[type]][0]);
    const setDisabled = (element, disabled) => {
        if (
            element instanceof HTMLButtonElement ||
            element instanceof HTMLInputElement ||
            element instanceof HTMLSelectElement
        ) {
            element.disabled = disabled;
        }
    };

    const renderSingle = (type, container) => {
        const list = container.querySelector('[data-category-list]');
        const searchInput = container.querySelector('[data-category-search]');
        const filtered = filterOptionsKeepingSelection(
            options[type],
            searchInput instanceof HTMLInputElement ? searchInput.value : '',
            selected[type],
        );
        list.replaceChildren();
        filtered.forEach((option) => {
            const element = documentObject.createElement('option');
            element.value = String(option.id);
            element.textContent = option.name;
            element.selected = selected[type].has(option.id);
            list.append(element);
        });
        if (selected[type].size === 0) {
            list.selectedIndex = -1;
        }
        setDisabled(list, !loaded);
        setDisabled(searchInput, !loaded);
        const unselected = selected[type].size === 0;
        setDisabled(container.querySelector('[data-category-clear]'), unselected);
        setDisabled(container.querySelector('[data-category-edit]'), unselected);
        setDisabled(container.querySelector('[data-category-delete]'), unselected);
    };

    const renderMultiple = (type, container) => {
        const badges = container.querySelector('[data-category-badges]');
        badges.replaceChildren();
        options[type].forEach((option) => {
            const wrapper = documentObject.createElement('span');
            wrapper.className = 'badge-option';
            const badge = documentObject.createElement('button');
            badge.type = 'button';
            badge.className = 'prompt-badge';
            badge.textContent = `${selected[type].has(option.id) ? '✓ ' : ''}${option.name}`;
            badge.setAttribute('aria-label', option.name);
            badge.setAttribute('aria-pressed', selected[type].has(option.id) ? 'true' : 'false');
            badge.disabled = !loaded;
            badge.addEventListener('click', () => {
                selected[type].has(option.id)
                    ? selected[type].delete(option.id)
                    : selected[type].add(option.id);
                render();
            });
            wrapper.append(badge);
            const actions = documentObject.createElement('span');
            actions.className = 'badge-option__actions';
            actions.setAttribute('aria-label', `${labels[type]}「${option.name}」の管理`);
            for (const [action, text] of [
                ['edit', '編集'],
                ['delete', '削除'],
            ]) {
                const button = documentObject.createElement('button');
                button.type = 'button';
                button.className = 'badge-manage-button';
                button.textContent = action === 'edit' ? '✎' : '×';
                button.setAttribute('aria-label', `${labels[type]}「${option.name}」を${text}`);
                button.disabled = !loaded;
                button.addEventListener('click', () =>
                    action === 'edit' ? openForm(type, option) : openDelete(type, option),
                );
                actions.append(button);
            }
            wrapper.append(actions);
            badges.append(wrapper);
        });
    };

    const render = () => {
        for (const type of Object.keys(options)) {
            const container = categoryRoot(type);
            setDisabled(container.querySelector('[data-category-add]'), !loaded);
            container.dataset.multiple === 'true'
                ? renderMultiple(type, container)
                : renderSingle(type, container);
        }
    };

    const load = async () => {
        loaded = false;
        onLoadedChange(false);
        render();
        status.textContent = '描写の選択肢を読み込んでいます。';
        retry.hidden = true;
        try {
            const result = await requestPromptCategories(
                fetcher,
                page.dataset.characterDirectionsUrl,
                page.dataset.sceneDirectionsUrl,
                page.dataset.promptOptionsUrl,
            );
            for (const type of Object.keys(options)) {
                options[type] = result[type];
                selected[type] = new Set(
                    [...selected[type]].filter((id) => item(type, id) !== null),
                );
            }
            loaded = true;
            status.textContent = '';
            retry.hidden = true;
            render();
            onLoadedChange(true);
        } catch {
            status.textContent = '描写の選択肢を読み込めませんでした。';
            retry.hidden = false;
            render();
        }
    };

    const close = (target) => target instanceof HTMLDialogElement && target.close();
    const show = (target) => target instanceof HTMLDialogElement && target.showModal();
    const openForm = (type, option = null) => {
        if (saving || deleting) {
            return;
        }
        editingType = type;
        page.querySelector('[data-category-dialog-title]').textContent =
            `${labels[type]}を${option ? '編集' : '追加'}`;
        page.querySelector('[data-category-id]').value = option?.id ?? '';
        page.querySelector('[data-category-name]').value = option?.name ?? '';
        page.querySelector('[data-category-content]').value = option?.content ?? '';
        const editingId = page.querySelector('[data-category-editing-id]');
        editingId.textContent = option ? `ID: ${option.id}` : '';
        editingId.hidden = option === null;
        page.querySelector('[data-category-form-status]').textContent = '';
        show(dialog);
        page.querySelector('[data-category-name]').focus();
    };
    const openDelete = (type, option) => {
        if (saving || deleting) {
            return;
        }
        deletingType = type;
        deletingId = option.id;
        page.querySelector('[data-category-delete-message]').textContent =
            `${labels[type]}「${option.name}」— ${option.content}（ID: ${option.id}）を削除します。`;
        page.querySelector('[data-category-delete-status]').textContent = '';
        show(deleteDialog);
    };
    const save = async () => {
        if (saving || editingType === null) {
            return;
        }
        saving = true;
        const operationType = editingType;
        const idValue = page.querySelector('[data-category-id]').value;
        const id = idValue === '' ? null : Number(idValue);
        const formStatus = page.querySelector('[data-category-form-status]');
        const button = page.querySelector('[data-category-save]');
        const cancelButton = page.querySelector('[data-category-cancel]');
        button.disabled = true;
        cancelButton.disabled = true;
        formStatus.textContent = '保存しています。';
        try {
            await saveNamedPrompt(fetcher, endpoints[operationType], csrfToken, id, {
                name: page.querySelector('[data-category-name]').value,
                content: page.querySelector('[data-category-content]').value,
            });
            close(dialog);
            notify('保存しました。');
            await load();
        } catch {
            formStatus.textContent =
                '保存できませんでした。入力内容を確認するか、時間をおいて再度お試しください。';
        } finally {
            saving = false;
            button.disabled = false;
            cancelButton.disabled = false;
        }
    };
    const remove = async () => {
        if (deleting || deletingType === null || deletingId === null) {
            return;
        }
        deleting = true;
        const operationType = deletingType;
        const operationId = deletingId;
        const deleteStatus = page.querySelector('[data-category-delete-status]');
        const button = page.querySelector('[data-category-delete-confirm]');
        const cancelButton = page.querySelector('[data-category-delete-cancel]');
        button.disabled = true;
        cancelButton.disabled = true;
        deleteStatus.textContent = '削除しています。';
        try {
            await deleteNamedPrompt(fetcher, endpoints[operationType], csrfToken, operationId);
            selected[operationType].delete(operationId);
            close(deleteDialog);
            notify('削除しました。');
            await load();
        } catch {
            deleteStatus.textContent = '削除できませんでした。再度お試しください。';
        } finally {
            deleting = false;
            button.disabled = false;
            cancelButton.disabled = false;
        }
    };

    root.querySelectorAll('[data-prompt-category]').forEach((container) => {
        const type = container.dataset.promptCategory;
        container
            .querySelector('[data-category-add]')
            ?.addEventListener('click', () => openForm(type));
        container.querySelector('[data-category-search]')?.addEventListener('input', render);
        container.querySelector('[data-category-list]')?.addEventListener('change', (event) => {
            selected[type] = new Set([Number(event.target.value)]);
            render();
            scrollToSelectionSection(container.nextElementSibling);
        });
        container.querySelector('[data-category-clear]')?.addEventListener('click', () => {
            selected[type].clear();
            render();
        });
        container
            .querySelector('[data-category-edit]')
            ?.addEventListener('click', () => openForm(type, selectedItem(type)));
        container
            .querySelector('[data-category-delete]')
            ?.addEventListener('click', () => openDelete(type, selectedItem(type)));
    });
    retry.addEventListener('click', load);
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        void save();
    });
    deleteForm.addEventListener('submit', (event) => {
        event.preventDefault();
        void remove();
    });
    page.querySelector('[data-category-cancel]').addEventListener('click', () => {
        if (!saving) {
            close(dialog);
        }
    });
    page.querySelector('[data-category-delete-cancel]').addEventListener(
        'click',
        () => !deleting && close(deleteDialog),
    );
    for (const target of [dialog, deleteDialog]) {
        target.addEventListener('cancel', (event) => {
            if (saving || deleting) {
                event.preventDefault();
            }
        });
        target.addEventListener('click', (event) => {
            if (saving || deleting) {
                return;
            }
            if (event.target !== target) {
                return;
            }
            const box = target.getBoundingClientRect();
            if (
                event.clientX < box.left ||
                event.clientX > box.right ||
                event.clientY < box.top ||
                event.clientY > box.bottom
            ) {
                close(target);
            }
        });
    }

    void load();
    return { getSections: () => createCategorySections({ options, selected }) };
};
