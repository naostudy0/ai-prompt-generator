const sourceOrder = ['default', 'lora', 'clothingLora', 'optionGroups'];

const orderedSnapshots = (snapshots) =>
    sourceOrder.map((source) => snapshots.get(source)).filter((snapshot) => snapshot !== undefined);

export const initializePromptSidebars = ({
    page,
    documentObject,
    onReorderOptionGroup = () => {},
    onChangeOptionGroupCategory = () => {},
}) => {
    const view = documentObject.defaultView;
    const navigation = page.querySelector('[data-section-navigation-list]');
    const summary = page.querySelector('[data-selection-summary-list]');
    const snapshots = new Map();
    let draggedKey = null;

    if (
        !view ||
        !(navigation instanceof view.HTMLElement) ||
        !(summary instanceof view.HTMLElement)
    ) {
        return { update: () => {}, getSelectionGroups: () => [] };
    }

    const scrollToTarget = (selector) => {
        const target = page.querySelector(selector);
        if (!(target instanceof view.HTMLElement)) {
            return;
        }
        if (target instanceof view.HTMLDetailsElement) {
            target.open = true;
        }
        let ancestor = target.parentElement;
        while (ancestor instanceof view.HTMLElement) {
            if (ancestor instanceof view.HTMLDetailsElement) {
                ancestor.open = true;
            }
            ancestor = ancestor.parentElement;
        }
        target.scrollIntoView?.({ behavior: 'auto', block: 'start' });
        target.focus({ preventScroll: true });
    };

    const renderNavigation = () => {
        navigation.replaceChildren();
        const navigationItems = orderedSnapshots(snapshots).flatMap(
            (snapshot) => snapshot.navigationItems,
        );
        const optionGroupId = (key) => Number(key.slice('option-group-'.length));
        const appendItem = (item, parent) => {
            const row = documentObject.createElement('div');
            row.className = 'section-navigation__row';
            const button = documentObject.createElement('button');
            button.type = 'button';
            button.className = 'section-navigation__link';
            button.textContent = item.label;
            button.dataset.sectionKey = item.key;
            button.addEventListener('click', () => scrollToTarget(item.target));
            row.append(button);
            if (item.key.startsWith('option-group-')) {
                const handle = documentObject.createElement('button');
                handle.type = 'button';
                handle.className = 'section-navigation__drag';
                handle.textContent = '☰';
                handle.draggable = true;
                handle.setAttribute('aria-label', `${item.label}をドラッグして並べ替え`);
                handle.addEventListener('dragstart', (event) => {
                    draggedKey = item.key;
                    event.dataTransfer?.setData('text/plain', item.key);
                    if (event.dataTransfer) {
                        event.dataTransfer.effectAllowed = 'move';
                    }
                });
                handle.addEventListener('dragend', () => {
                    draggedKey = null;
                    navigation
                        .querySelectorAll('.is-drop-target')
                        .forEach((target) => target.classList.remove('is-drop-target'));
                });
                row.addEventListener('dragover', (event) => {
                    if (draggedKey !== null && draggedKey !== item.key) {
                        event.preventDefault();
                        row.classList.add('is-drop-target');
                    }
                });
                row.addEventListener('dragleave', () => row.classList.remove('is-drop-target'));
                row.addEventListener('drop', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    row.classList.remove('is-drop-target');
                    if (draggedKey === null || draggedKey === item.key) {
                        return;
                    }
                    const sourceId = optionGroupId(draggedKey);
                    const source = navigationItems.find(
                        (candidate) => candidate.key === draggedKey,
                    );
                    if (source?.categoryKey !== item.categoryKey) {
                        onChangeOptionGroupCategory(sourceId, item.categoryId ?? null);
                        draggedKey = null;
                        return;
                    }
                    const after =
                        event.clientY >
                        row.getBoundingClientRect().top + row.getBoundingClientRect().height / 2;
                    const optionItems = navigationItems.filter(
                        (candidate) =>
                            candidate.key.startsWith('option-group-') &&
                            candidate.key !== draggedKey,
                    );
                    const nextItem =
                        optionItems[
                            optionItems.findIndex((candidate) => candidate.key === item.key) + 1
                        ];
                    const beforeKey = after ? nextItem?.key : item.key;
                    onReorderOptionGroup(sourceId, beforeKey ? optionGroupId(beforeKey) : null);
                    draggedKey = null;
                });
                row.prepend(handle);
            }
            parent.append(row);
        };

        navigationItems
            .filter((item) => item.categoryKey === undefined)
            .forEach((item) => appendItem(item, navigation));
        const categorizedItems = navigationItems.filter((item) => item.categoryKey !== undefined);
        const categoryKeys = [...new Set(categorizedItems.map((item) => item.categoryKey))];
        categoryKeys.forEach((categoryKey) => {
            const items = categorizedItems.filter((item) => item.categoryKey === categoryKey);
            const details = documentObject.createElement('details');
            details.className = 'section-navigation__category';
            details.open = true;
            const heading = documentObject.createElement('summary');
            const marker = documentObject.createElement('span');
            marker.className = 'category-disclosure-marker';
            marker.setAttribute('aria-hidden', 'true');
            const updateMarker = () => {
                marker.textContent = details.open ? '▼' : '▶';
            };
            const label = documentObject.createElement('span');
            label.className = 'category-disclosure-label';
            label.textContent = items[0]?.categoryLabel ?? '未分類';
            heading.append(marker, label);
            updateMarker();
            details.addEventListener('toggle', updateMarker);
            const children = documentObject.createElement('div');
            children.className = 'section-navigation__category-items';
            details.addEventListener('dragover', (event) => {
                if (draggedKey !== null) {
                    event.preventDefault();
                    details.classList.add('is-drop-target');
                }
            });
            details.addEventListener('dragleave', (event) => {
                if (!details.contains(event.relatedTarget)) {
                    details.classList.remove('is-drop-target');
                }
            });
            details.addEventListener('drop', (event) => {
                event.preventDefault();
                details.classList.remove('is-drop-target');
                if (draggedKey === null) {
                    return;
                }
                const source = navigationItems.find((candidate) => candidate.key === draggedKey);
                if (source?.categoryKey !== categoryKey) {
                    onChangeOptionGroupCategory(
                        optionGroupId(draggedKey),
                        items[0]?.categoryId ?? null,
                    );
                }
                draggedKey = null;
            });
            items.forEach((item) => appendItem(item, children));
            details.append(heading, children);
            navigation.append(details);
        });
    };

    const renderSummary = () => {
        summary.replaceChildren();
        orderedSnapshots(snapshots)
            .flatMap((snapshot) => snapshot.selectionGroups)
            .filter((group) => group.items.length > 0)
            .forEach((group) => {
                const navigationItem = orderedSnapshots(snapshots)
                    .flatMap((snapshot) => snapshot.navigationItems)
                    .find((item) => item.key === group.key);
                const section = documentObject.createElement('section');
                section.className = 'selection-summary__group';
                section.dataset.sectionKey = group.key;
                const heading = documentObject.createElement('h3');
                const headingButton = documentObject.createElement('button');
                headingButton.type = 'button';
                headingButton.className = 'selection-summary__section-link';
                headingButton.textContent = group.label;
                headingButton.addEventListener('click', () => {
                    if (navigationItem !== undefined) {
                        scrollToTarget(navigationItem.target);
                    }
                });
                heading.append(headingButton);
                const items = documentObject.createElement('ul');
                group.items.forEach((item) => {
                    const listItem = documentObject.createElement('li');
                    const content = documentObject.createElement('div');
                    content.className = 'selection-summary__item-content';
                    const label = documentObject.createElement('span');
                    label.className = 'selection-summary__item-label';
                    label.textContent = item.label;
                    content.append(label);
                    if (item.meta !== '') {
                        const meta = documentObject.createElement('span');
                        meta.className = 'selection-summary__item-meta';
                        meta.textContent = item.meta;
                        content.append(meta);
                    }
                    if (item.details.length > 0) {
                        const details = documentObject.createElement('ul');
                        details.className = 'selection-summary__details';
                        item.details.forEach((detail) => {
                            const detailItem = documentObject.createElement('li');
                            detailItem.textContent = detail;
                            details.append(detailItem);
                        });
                        content.append(details);
                    }
                    listItem.append(content);
                    if (typeof item.onRemove === 'function') {
                        const removeButton = documentObject.createElement('button');
                        removeButton.type = 'button';
                        removeButton.className = 'selection-summary__remove';
                        removeButton.textContent = '×';
                        removeButton.setAttribute('aria-label', `${item.label}の選択を解除`);
                        removeButton.addEventListener('click', item.onRemove);
                        listItem.append(removeButton);
                    }
                    items.append(listItem);
                });
                section.append(heading, items);
                summary.append(section);
            });
    };

    return {
        update: (source, snapshot) => {
            snapshots.set(source, snapshot);
            renderNavigation();
            renderSummary();
        },
        getSelectionGroups: () =>
            orderedSnapshots(snapshots)
                .flatMap((snapshot) => snapshot.selectionGroups)
                .filter((group) => group.items.length > 0),
    };
};
