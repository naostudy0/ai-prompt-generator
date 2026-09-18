export const scrollToSelectionSection = (section) => {
    section?.scrollIntoView?.({ behavior: 'smooth', block: 'start' });
};
