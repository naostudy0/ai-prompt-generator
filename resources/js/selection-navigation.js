export const scrollToSelectionSection = (section) => {
    if (section === null || section === undefined) {
        return;
    }
    const view = section.ownerDocument?.defaultView;
    const reduceMotion = view?.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches ?? false;
    section.focus?.({ preventScroll: true });
    section.scrollIntoView?.({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
};
