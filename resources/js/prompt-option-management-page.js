import { initializePromptOptionsPage } from './prompt-options-page.js';

export const initializePromptOptionManagementPage = ({
    documentObject = document,
    fetcher = fetch,
} = {}) => {
    const page = documentObject.querySelector('[data-prompt-option-management]');
    if (!page) {
        return;
    }

    const csrfToken =
        documentObject.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    initializePromptOptionsPage({
        page,
        documentObject,
        fetcher,
        csrfToken,
        onLoadedChange: () => {},
        notify: (message) => {
            const status = page.querySelector('[data-category-load-status]');
            if (status) {
                status.textContent = message;
            }
        },
    });
};
