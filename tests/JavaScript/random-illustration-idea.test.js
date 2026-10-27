import assert from 'node:assert/strict';
import { setTimeout as delay } from 'node:timers/promises';
import test from 'node:test';
import { JSDOM } from 'jsdom';
import { initializeRandomIllustrationIdea } from '../../resources/js/random-illustration-idea.js';

const fixture = () => {
    const dom = new JSDOM(`<!doctype html><main
        data-random-illustration-ideas-url="/random-illustration-ideas"
        data-random-illustration-ideas-resolve-url="/random-illustration-ideas/resolve"
    >
        <button data-random-idea-open>open</button>
        <dialog data-random-idea-dialog>
            <button data-random-idea-close>close</button>
            <section data-random-idea-preview hidden>
                <ul data-random-idea-fixed></ul>
                <p data-random-idea-anchor></p>
                <textarea data-random-idea-description></textarea>
                <div data-random-idea-suggestions></div>
            </section>
            <p data-random-idea-status></p>
            <button data-random-idea-generate>regenerate</button>
            <button data-random-idea-resolve>resolve</button>
        </dialog>
    </main>`);
    const { document } = dom.window;
    const dialog = document.querySelector('dialog');
    dialog.showModal = () => dialog.setAttribute('open', '');
    dialog.close = () => dialog.removeAttribute('open');

    return { document, page: document.querySelector('main') };
};

test('HTMLのサーバーエラーをJSON構文エラーではなくHTTPエラーとして表示する', async () => {
    const { document, page } = fixture();
    initializeRandomIllustrationIdea({
        page,
        documentObject: document,
        fetcher: async () => ({
            ok: false,
            status: 500,
            json: async () => {
                throw new SyntaxError(`Unexpected token '<'`);
            },
        }),
        csrfToken: 'token',
        captureFixedSelections: () => ({}),
        applyResolved: () => false,
        getSavableGroups: () => [],
        savePositiveSuggestion: async () => {},
    });

    document.querySelector('[data-random-idea-open]').click();
    await delay(0);

    const message = document.querySelector('[data-random-idea-status]').textContent;
    assert.match(message, /HTTP 500/);
    assert.doesNotMatch(message, /Unexpected token/);
});
