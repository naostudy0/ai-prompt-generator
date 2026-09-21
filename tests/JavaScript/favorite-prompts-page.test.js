import assert from 'node:assert/strict';
import { setImmediate } from 'node:timers/promises';
import test from 'node:test';

import { JSDOM } from 'jsdom';

import { initializeFavoritePromptsPage } from '../../resources/js/favorite-prompts-page.js';

const createDocument = () => {
    const dom = new JSDOM(`<!doctype html><main data-favorite-prompts-url="/favorite-prompts">
        <button data-favorite-save></button><button data-favorite-open></button>
        <dialog data-favorite-form-dialog><form data-favorite-form>
            <h2 data-favorite-form-title></h2><input data-favorite-name><input type="file" data-favorite-image>
            <label data-favorite-remove-field><input type="checkbox" data-favorite-remove-image></label>
            <div data-favorite-selection-preview></div><p data-favorite-form-status></p>
            <button type="submit" data-favorite-submit></button><button type="button" data-favorite-save-copy></button>
            <button type="button" data-favorite-form-cancel></button>
        </form></dialog>
        <dialog data-favorite-list-dialog><p data-favorite-list-status></p><div data-favorite-list></div>
            <button data-favorite-list-retry hidden></button>
            <button data-favorite-list-close></button></dialog>
    </main>`);
    dom.window.HTMLDialogElement.prototype.showModal = function () {
        this.open = true;
    };
    dom.window.HTMLDialogElement.prototype.close = function () {
        this.open = false;
    };
    return dom.window.document;
};

test('現在の完成プロンプトと選択状態をお気に入りとして保存する', async () => {
    const documentObject = createDocument();
    const originalFormData = globalThis.FormData;
    globalThis.FormData = documentObject.defaultView.FormData;
    let request;
    const fetcher = async (url, options) => {
        request = { url, options };
        return { ok: true, status: 201, json: async () => ({ id: 4 }) };
    };
    const notifications = [];
    initializeFavoritePromptsPage({
        page: documentObject.querySelector('main'),
        documentObject,
        fetcher,
        csrfToken: 'token',
        capture: () => ({
            positivePrompt: 'masterpiece, park,',
            negativePrompt: 'bad anatomy,',
            selectionSnapshot: { optionIds: [3] },
            selectionSummary: [
                { key: 'place', label: '場所', items: [{ label: '公園', meta: '', details: [] }] },
            ],
        }),
        restore: () => ({ complete: true, matches: true }),
        notify: (message) => notifications.push(message),
    });

    documentObject.querySelector('[data-favorite-save]').click();
    documentObject.querySelector('[data-favorite-name]').value = '公園';
    documentObject.querySelector('[data-favorite-form]').requestSubmit();
    await setImmediate();

    assert.equal(request.url, '/favorite-prompts');
    assert.equal(request.options.method, 'POST');
    assert.equal(request.options.headers['X-CSRF-TOKEN'], 'token');
    assert.equal(request.options.body.get('name'), '公園');
    assert.equal(request.options.body.get('positivePrompt'), 'masterpiece, park,');
    assert.equal(request.options.body.get('negativePrompt'), 'bad anatomy,');
    assert.equal(request.options.body.get('selectionSnapshot'), '{"optionIds":[3]}');
    assert.equal(
        request.options.body.get('selectionSummary'),
        '[{"key":"place","label":"場所","items":[{"label":"公園","meta":"","details":[]}]}]',
    );
    assert.deepEqual(notifications, ['お気に入りを保存しました。']);
    globalThis.FormData = originalFormData;
});

test('お気に入りを呼び出して部分復元を通知し削除する', async () => {
    const documentObject = createDocument();
    const requests = [];
    const favorite = {
        id: 7,
        name: '夕方',
        displayName: '夕方',
        imageUrl: null,
        createdAt: '2026-09-19T12:00:00+09:00',
        selectionSummary: [
            { key: 'place', label: '場所', items: [{ label: '公園', meta: '', details: [] }] },
        ],
    };
    const fetcher = async (url, options = {}) => {
        requests.push({ url, options });
        if (options.method === 'DELETE') {
            return { ok: true, status: 204 };
        }
        if (url === '/favorite-prompts/7') {
            return {
                ok: true,
                status: 200,
                json: async () => ({
                    ...favorite,
                    positivePrompt: 'sunset,',
                    negativePrompt: '',
                    selectionSnapshot: { optionIds: [99] },
                }),
            };
        }
        return { ok: true, status: 200, json: async () => ({ favorites: [favorite] }) };
    };
    const notifications = [];
    let restored;
    initializeFavoritePromptsPage({
        page: documentObject.querySelector('main'),
        documentObject,
        fetcher,
        csrfToken: 'token',
        capture: () => ({
            positivePrompt: 'x',
            negativePrompt: '',
            selectionSnapshot: {},
            selectionSummary: [],
        }),
        restore: (value) => {
            restored = value;
            return { complete: false, matches: false, unavailableLabels: ['公園'] };
        },
        notify: (message) => notifications.push(message),
    });

    documentObject.querySelector('[data-favorite-open]').click();
    await setImmediate();
    documentObject.querySelector('.favorite-card .primary-button').click();
    await setImmediate();

    assert.equal(restored.id, 7);
    assert.match(notifications[0], /一部を選択状態へ反映できませんでした/);
    assert.match(notifications[0], /反映できなかった項目：公園/);

    documentObject.querySelector('[data-favorite-open]').click();
    await setImmediate();
    documentObject.querySelector('.favorite-card .danger-button').click();
    await setImmediate();
    const deleteRequest = requests.find((item) => item.options.method === 'DELETE');
    assert.equal(deleteRequest.url, '/favorite-prompts/7');
    assert.equal(deleteRequest.options.headers['X-CSRF-TOKEN'], 'token');
});
