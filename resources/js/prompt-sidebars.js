const sourceOrder = ['default', 'lora', 'clothingLora', 'optionGroups'];

const orderedSnapshots = (snapshots) =>
    sourceOrder.map((source) => snapshots.get(source)).filter((snapshot) => snapshot !== undefined);

export const initializePromptSidebars = ({ page, documentObject }) => {
    const view = documentObject.defaultView;
    const navigation = page.querySelector('[data-section-navigation-list]');
    const summary = page.querySelector('[data-selection-summary-list]');
    const snapshots = new Map();

    if (
        !view ||
        !(navigation instanceof view.HTMLElement) ||
        !(summary instanceof view.HTMLElement)
    ) {
        return { update: () => {} };
    }

    const scrollToTarget = (selector) => {
        const target = page.querySelector(selector);
        if (!(target instanceof view.HTMLElement)) {
            return;
        }
        const reduceMotion = view.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
        target.scrollIntoView?.({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
        target.focus({ preventScroll: true });
    };

    const renderNavigation = () => {
        navigation.replaceChildren();
        orderedSnapshots(snapshots)
            .flatMap((snapshot) => snapshot.navigationItems)
            .forEach((item) => {
                const button = documentObject.createElement('button');
                button.type = 'button';
                button.className = 'section-navigation__link';
                button.textContent = item.label;
                button.dataset.sectionKey = item.key;
                button.addEventListener('click', () => scrollToTarget(item.target));
                navigation.append(button);
            });
    };

    const renderSummary = () => {
        summary.replaceChildren();
        orderedSnapshots(snapshots)
            .flatMap((snapshot) => snapshot.selectionGroups)
            .filter((group) => group.items.length > 0)
            .forEach((group) => {
                const section = documentObject.createElement('section');
                section.className = 'selection-summary__group';
                section.dataset.sectionKey = group.key;
                const heading = documentObject.createElement('h3');
                heading.textContent = group.label;
                const items = documentObject.createElement('ul');
                group.items.forEach((item) => {
                    const listItem = documentObject.createElement('li');
                    const label = documentObject.createElement('span');
                    label.className = 'selection-summary__item-label';
                    label.textContent = item.label;
                    listItem.append(label);
                    if (item.meta !== '') {
                        const meta = documentObject.createElement('span');
                        meta.className = 'selection-summary__item-meta';
                        meta.textContent = item.meta;
                        listItem.append(meta);
                    }
                    if (item.details.length > 0) {
                        const details = documentObject.createElement('ul');
                        details.className = 'selection-summary__details';
                        item.details.forEach((detail) => {
                            const detailItem = documentObject.createElement('li');
                            detailItem.textContent = detail;
                            details.append(detailItem);
                        });
                        listItem.append(details);
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
    };
};
