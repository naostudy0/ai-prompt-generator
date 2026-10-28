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
        <dialog data-favorite-list-dialog><p data-favorite-list-status></p>
            <fieldset data-favorite-group-controls><input data-favorite-group-name><select data-favorite-group-list></select>
                <button data-favorite-group-save></button><button data-favorite-group-load></button><button data-favorite-group-delete></button>
            </fieldset>
            <button data-favorite-select-all></button><button data-favorite-clear-all></button>
            <span data-favorite-selected-count></span><span data-favorite-lora-summary></span>
            <button data-favorite-batch-send></button><div data-favorite-list></div>
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

const favorite = (id, name = `お気に入り${id}`) => ({
    id,
    name,
    displayName: name,
    imageUrl: null,
    createdAt: '2026-09-19T12:00:00+09:00',
    selectionSummary: [],
});

test('チェック状態と名前付きグループを保存して呼び出す', async () => {
    const documentObject = createDocument();
    const storage = new Map();
    const confirmMessages = [];
    initializeFavoritePromptsPage({
        page: documentObject.querySelector('main'),
        documentObject,
        fetcher: async () => ({
            ok: true,
            status: 200,
            json: async () => ({ favorites: [favorite(1), favorite(2)] }),
        }),
        csrfToken: 'token',
        capture: () => ({}),
        restore: () => ({}),
        notify: () => {},
        storage: {
            getItem: (key) => storage.get(key) ?? null,
            setItem: (key, value) => storage.set(key, value),
        },
        confirmAction: (message) => {
            confirmMessages.push(message);
            return true;
        },
        getCharacterLoraApplication: () => null,
    });
    documentObject.querySelector('[data-favorite-open]').click();
    await setImmediate();
    const checks = documentObject.querySelectorAll('[data-favorite-check]');
    assert.equal(checks.length, 2);
    assert.equal(checks[0].checked, false);
    checks[0].click();
    assert.equal(documentObject.querySelector('[data-favorite-batch-send]').disabled, true);
    documentObject.querySelector('[data-favorite-group-name]').value = '主役';
    documentObject.querySelector('[data-favorite-group-save]').click();
    assert.match(storage.get('favorite-prompt-checks-v1'), /favorite:1/);
    assert.match(storage.get('favorite-prompt-groups-v1'), /主役/);
    documentObject.querySelector('[data-favorite-clear-all]').click();
    documentObject.querySelector('[data-favorite-group-load]').click();
    assert.equal(checks[0].checked, true);
    assert.equal(
        documentObject.querySelector('[data-favorite-selected-count]').textContent,
        '1件選択',
    );
    documentObject.querySelector('[data-favorite-group-name]').value = '主役';
    documentObject.querySelector('[data-favorite-group-save]').click();
    assert.match(confirmMessages[0], /上書き/);
});

test('選択したお気に入りを準備して一度だけ一括送信する', async () => {
    const documentObject = createDocument();
    const requests = [];
    const listFavorite = favorite(7, '夕方');
    const fetcher = async (url, options = {}) => {
        requests.push({ url, options });
        if (url === '/favorite-prompts/7') {
            return {
                ok: true,
                status: 200,
                json: async () => ({
                    ...listFavorite,
                    positivePrompt: 'old',
                    negativePrompt: 'bad',
                    selectionSnapshot: {
                        lora: { loraId: null },
                        defaultSections: { positive: '' },
                    },
                }),
            };
        }
        if (url === '/batch') {
            return {
                ok: true,
                status: 200,
                json: async () => ({
                    results: [{ candidateKey: 'favorite:7', status: 'accepted', promptId: 'p-7' }],
                }),
            };
        }
        return { ok: true, status: 200, json: async () => ({ favorites: [listFavorite] }) };
    };
    const page = documentObject.querySelector('main');
    page.dataset.comfyUiPromptBatchUrl = '/batch';
    initializeFavoritePromptsPage({
        page,
        documentObject,
        fetcher,
        csrfToken: 'token',
        capture: () => ({}),
        restore: () => ({}),
        notify: () => {},
        storage: { getItem: () => null, setItem: () => {} },
        confirmAction: () => true,
        getCharacterLoraApplication: () => ({
            options: {
                loras: [{ id: 3, modelFamilyId: 2, fileName: 'new.safetensors' }],
                triggers: [],
            },
            selection: { loraId: 3, strength: 1, triggerId: null },
            selectedSections: { lora: '<lora:new:1>', trigger: '' },
            summary: '新人物 / トリガーなし / 強度1',
        }),
    });
    documentObject.querySelector('[data-favorite-open]').click();
    await setImmediate();
    documentObject.querySelector('[data-favorite-check]').click();
    documentObject.querySelector('[data-favorite-batch-send]').click();
    await setImmediate();
    await setImmediate();
    const batch = requests.find((item) => item.url === '/batch');
    assert.deepEqual(JSON.parse(batch.options.body), {
        items: [
            {
                candidateKey: 'favorite:7',
                modelFamilyId: 2,
                loraId: 3,
                positive: '<lora:new:1>\n\nold',
                negative: 'bad',
            },
        ],
    });
    assert.match(
        documentObject.querySelector('[data-favorite-result]').textContent,
        /送信済み.*p-7/,
    );
});

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
            positivePrompt: '(masterpiece:1.05), park,',
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
    assert.equal(request.options.body.get('positivePrompt'), '(masterpiece:1.05), park,');
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
