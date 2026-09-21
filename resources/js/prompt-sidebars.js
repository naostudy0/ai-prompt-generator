const sourceOrder = ['default', 'lora', 'clothingLora', 'optionGroups'];

const orderedSnapshots = (snapshots) =>
    sourceOrder.map((source) => snapshots.get(source)).filter((snapshot) => snapshot !== undefined);

export const initializePromptSidebars = ({
    page,
    documentObject,
    onReorderOptionGroup = () => {},
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
        target.scrollIntoView?.({ behavior: 'auto', block: 'start' });
        target.focus({ preventScroll: true });
    };

    const renderNavigation = () => {
        navigation.replaceChildren();
        const navigationItems = orderedSnapshots(snapshots).flatMap(
            (snapshot) => snapshot.navigationItems,
        );
        navigationItems.forEach((item) => {
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
                    row.classList.remove('is-drop-target');
                    if (draggedKey === null || draggedKey === item.key) {
                        return;
                    }
                    const sourceId = Number(draggedKey.slice('option-group-'.length));
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
                    onReorderOptionGroup(
                        sourceId,
                        beforeKey ? Number(beforeKey.slice('option-group-'.length)) : null,
                    );
                    draggedKey = null;
                });
                row.prepend(handle);
            }
            navigation.append(row);
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
