import assert from 'node:assert/strict';
import { setImmediate } from 'node:timers/promises';
import test from 'node:test';

import { JSDOM } from 'jsdom';

import { initializeCharacterLoraVariantPage } from '../../resources/js/character-lora-variant-page.js';
import { createCharacterLoraSourceMetadata } from '../../resources/js/character-lora-variant.js';

test('候補を続けてコピーしても元の出力と選択は変わらない', async () => {
    const dom = new JSDOM(`
        <main>
            <button data-character-variant-open disabled>差し替え</button>
            <dialog data-character-variant-dialog>
                <button data-character-variant-close>閉じる</button>
                <input data-character-variant-search>
                <p data-character-variant-status></p>
                <div data-character-variant-list></div>
            </dialog>
        </main>
    `);
    const documentObject = dom.window.document;
    const dialog = documentObject.querySelector('dialog');
    dialog.showModal = () => {
        dialog.open = true;
    };
    dialog.close = () => {
        dialog.open = false;
    };
    const copied = [];
    const notifications = [];
    const positive =
        'masterpiece,\n\n<lora:original:1>,\n\nbrown hair,\n\n<lora:dress:1>,\n\npark,';
    const source = {
        positive,
        original: createCharacterLoraSourceMetadata(
            'masterpiece,',
            '<lora:original:1>,',
            'brown hair,',
        ),
    };
    const options = {
        loras: [
            {
                id: 1,
                name: '一人目',
                fileName: 'first',
                recommendedStrength: 0.8,
                tags: Array(11).fill('<lora:first:0.8>,'),
            },
            {
                id: 2,
                name: '二人目',
                fileName: 'second',
                recommendedStrength: 1,
                tags: Array(11).fill('<lora:second:1>,'),
            },
        ],
        triggers: [
            { id: 1, loraId: 1, name: '標準', content: 'blue hair,' },
            { id: 3, loraId: 1, name: '短髪', content: 'short hair,' },
            { id: 2, loraId: 2, name: '標準', content: 'red hair,' },
        ],
    };
    const controller = initializeCharacterLoraVariantPage({
        page: documentObject.querySelector('main'),
        documentObject,
        clipboard: { writeText: async (value) => copied.push(value) },
        getCandidates: () => options,
        getSource: () => source,
        notify: (message) => notifications.push(message),
    });

    controller.updateAvailability();
    const openButton = documentObject.querySelector('[data-character-variant-open]');
    assert.equal(openButton.disabled, false);
    openButton.click();
    const buttons = documentObject.querySelectorAll('.character-variant-item');
    assert.equal(buttons.length, 3);
    assert.match(buttons[1].getAttribute('aria-label'), /短髪/);
    const firstSelection = documentObject.querySelector('.character-variant-send-selection');
    assert.equal(firstSelection.textContent, '一括送信');
    assert.equal(firstSelection.querySelector('input').checked, true);
    buttons[0].click();
    await setImmediate();
    buttons[1].click();
    await setImmediate();
    buttons[2].click();
    await setImmediate();

    assert.equal(copied.length, 3);
    assert.match(copied[0], /<lora:first:0\.8>,\n\nblue hair,/);
    assert.match(copied[1], /<lora:first:0\.8>,\n\nshort hair,/);
    assert.match(copied[2], /<lora:second:1>,\n\nred hair,/);
    assert.match(copied[0], /<lora:dress:1>,\n\npark,/);
    assert.equal(source.positive, positive);
    assert.equal(notifications.length, 3);
    assert.equal(dialog.open, true);
});

test('クリップボード失敗時は成功通知せず候補一覧を保持する', async () => {
    const dom = new JSDOM(`
        <main>
            <button data-character-variant-open>差し替え</button>
            <dialog data-character-variant-dialog>
                <button data-character-variant-close>閉じる</button>
                <input data-character-variant-search>
                <p data-character-variant-status></p>
                <div data-character-variant-list></div>
            </dialog>
        </main>
    `);
    const page = dom.window.document.querySelector('main');
    const dialog = page.querySelector('dialog');
    dialog.showModal = () => {
        dialog.open = true;
    };
    const notifications = [];
    const candidate = {
        id: 1,
        name: '別人物',
        fileName: 'other',
        recommendedStrength: 1,
        tags: Array(11).fill('<lora:other:1>,'),
    };
    initializeCharacterLoraVariantPage({
        page,
        documentObject: dom.window.document,
        clipboard: {
            writeText: async () => {
                throw new Error('denied');
            },
        },
        getCandidates: () => ({ loras: [candidate], triggers: [] }),
        getSource: () => ({
            positive: 'masterpiece,',
            original: createCharacterLoraSourceMetadata('masterpiece,', '', ''),
        }),
        notify: (message) => notifications.push(message),
    });
    page.querySelector('[data-character-variant-open]').click();
    page.querySelector('.character-variant-item').click();
    await setImmediate();

    assert.equal(dialog.open, true);
    assert.equal(page.querySelectorAll('.character-variant-item').length, 1);
    assert.match(
        page.querySelector('[data-character-variant-status]').textContent,
        /コピーできません/,
    );
    assert.equal(notifications.length, 0);
});

test('別系統の人物LoRAへ差し替えると両側のデフォルトを候補系統へ変えてコピーする', async () => {
    const dom = new JSDOM(`<main>
        <button data-character-variant-open>差し替え</button>
        <dialog data-character-variant-dialog>
            <button data-character-variant-close>閉じる</button>
            <input data-character-variant-search>
            <p data-character-variant-status></p>
            <div data-character-variant-list></div>
        </dialog>
    </main>`);
    const page = dom.window.document.querySelector('main');
    page.querySelector('dialog').showModal = () => {};
    const copied = [];
    initializeCharacterLoraVariantPage({
        page,
        documentObject: dom.window.document,
        clipboard: { writeText: async (value) => copied.push(value) },
        getCandidates: () => ({
            loras: [
                {
                    id: 2,
                    name: 'anima人物',
                    fileName: 'anima',
                    modelFamilyId: 2,
                    modelFamilyName: 'anima',
                    recommendedStrength: 1,
                    tags: Array(11).fill('<lora:anima:1>,'),
                },
            ],
            triggers: [],
        }),
        getSource: () => ({
            positive: 'ill positive,\n\n<lora:ill:1>,\n\nstanding,',
            negative: 'ill negative,\n\nmanual negative,',
            original: createCharacterLoraSourceMetadata('ill positive,', '<lora:ill:1>,', ''),
            family: {
                modelFamilyId: 1,
                defaults: { positive: true, negative: true },
                defaultSections: { positive: 'ill positive,', negative: 'ill negative,' },
            },
        }),
        getFamilyPrompts: () => ({ positive: 'anima positive,', negative: 'anima negative,' }),
        notify: () => {},
    });
    page.querySelector('[data-character-variant-open]').click();
    page.querySelector('.character-variant-item').click();
    await setImmediate();
    page.querySelector('.character-variant-row .small-button').click();
    await setImmediate();

    assert.equal(copied[0], 'anima positive,\n\n<lora:anima:1>,\n\nstanding,');
    assert.equal(copied[1], 'anima negative,\n\nmanual negative,');
});

test('positiveの人物タグが手編集されてもnegativeは個別にコピーできる', async () => {
    const dom = new JSDOM(`<main>
        <button data-character-variant-open>差し替え</button>
        <dialog data-character-variant-dialog>
            <button data-character-variant-close>閉じる</button>
            <input data-character-variant-search>
            <p data-character-variant-status></p>
            <div data-character-variant-list></div>
        </dialog>
    </main>`);
    const page = dom.window.document.querySelector('main');
    page.querySelector('dialog').showModal = () => {};
    const copied = [];
    initializeCharacterLoraVariantPage({
        page,
        documentObject: dom.window.document,
        clipboard: { writeText: async (value) => copied.push(value) },
        getCandidates: () => ({
            loras: [
                {
                    id: 2,
                    name: 'anima人物',
                    fileName: 'anima',
                    modelFamilyId: 2,
                    recommendedStrength: 1,
                    tags: Array(11).fill('<lora:anima:1>,'),
                },
            ],
            triggers: [],
        }),
        getSource: () => ({
            positive: 'old positive,\n\n<lora:edited:1>,',
            negative: 'old negative,\n\nmanual,',
            original: createCharacterLoraSourceMetadata('old positive,', '<lora:original:1>,', ''),
            family: {
                modelFamilyId: 1,
                defaults: { positive: true, negative: true },
                defaultSections: { positive: 'old positive,', negative: 'old negative,' },
            },
        }),
        getFamilyPrompts: () => ({ positive: 'new positive,', negative: 'new negative,' }),
        notify: () => {},
    });
    page.querySelector('[data-character-variant-open]').click();
    page.querySelector('.character-variant-row .small-button').click();
    await setImmediate();

    assert.deepEqual(copied, ['new negative,\n\nmanual,']);
});

test('一括送信のチェック状態を変えずに候補を一件だけComfyUIへ送信する', async () => {
    const dom = new JSDOM(`<main data-comfy-ui-prompts-url="/comfyui/prompts">
        <button data-character-variant-open>差し替え</button>
        <dialog data-character-variant-dialog>
            <button data-character-variant-close>閉じる</button>
            <input data-character-variant-search>
            <p data-character-variant-status></p>
            <div data-character-variant-list></div>
        </dialog>
    </main>`);
    const page = dom.window.document.querySelector('main');
    page.querySelector('dialog').showModal = () => {};
    const requests = [];
    const notifications = [];
    initializeCharacterLoraVariantPage({
        page,
        documentObject: dom.window.document,
        clipboard: { writeText: async () => {} },
        getCandidates: () => ({
            loras: [
                {
                    id: 2,
                    name: 'テスト人物',
                    fileName: 'test-character',
                    modelFamilyId: 3,
                    recommendedStrength: 0.8,
                    tags: Array(11).fill('<lora:test-character:0.8>,'),
                },
            ],
            triggers: [{ id: 5, loraId: 2, name: '標準', content: 'red hair,' }],
        }),
        getSource: () => ({
            positive: 'quality,\n\n<lora:original:1>,\n\nblue hair,',
            negative: 'low quality,',
            original: createCharacterLoraSourceMetadata(
                'quality,',
                '<lora:original:1>,',
                'blue hair,',
            ),
            family: { modelFamilyId: 3 },
        }),
        fetcher: async (url, options) => {
            requests.push({ url, options });
            return { ok: true, json: async () => ({ promptId: 'prompt-one' }) };
        },
        csrfToken: 'csrf',
        notify: (message) => notifications.push(message),
    });
    page.querySelector('[data-character-variant-open]').click();
    const checkbox = page.querySelector('.character-variant-send-selection input');
    checkbox.click();
    page.querySelector('[aria-label="テスト人物・標準をComfyUIへ送信"]').click();
    await setImmediate();

    assert.equal(requests.length, 1);
    assert.equal(requests[0].url, '/comfyui/prompts');
    assert.deepEqual(JSON.parse(requests[0].options.body), {
        modelFamilyId: 3,
        positive: 'quality,\n\n<lora:test-character:0.8>,\n\nred hair,',
        negative: 'low quality,',
    });
    assert.equal(checkbox.checked, false);
    assert.match(page.querySelector('[data-character-variant-status]').textContent, /prompt-one/);
    assert.equal(notifications.length, 1);
});

test('一括送信の成功IDと失敗理由を候補ごとに表示する', async () => {
    const dom = new JSDOM(`<main data-comfy-ui-prompt-batch-url="/comfyui/prompts/batch">
        <button data-character-variant-open>差し替え</button>
        <dialog data-character-variant-dialog>
            <button data-character-variant-close>閉じる</button>
            <input data-character-variant-search>
            <span data-character-variant-count></span>
            <p data-character-variant-status></p>
            <div data-character-variant-list></div>
            <button data-character-variant-send>一括送信</button>
        </dialog>
    </main>`);
    const page = dom.window.document.querySelector('main');
    page.querySelector('dialog').showModal = () => {};
    dom.window.confirm = () => true;
    initializeCharacterLoraVariantPage({
        page,
        documentObject: dom.window.document,
        clipboard: { writeText: async () => {} },
        getCandidates: () => ({
            loras: [
                {
                    id: 1,
                    name: '成功人物',
                    fileName: 'accepted',
                    recommendedStrength: 1,
                    tags: Array(11).fill('<lora:accepted:1>,'),
                },
                {
                    id: 2,
                    name: '失敗人物',
                    fileName: 'failed',
                    recommendedStrength: 1,
                    tags: Array(11).fill('<lora:failed:1>,'),
                },
            ],
            triggers: [],
        }),
        getSource: () => ({
            positive: '<lora:original:1>,',
            negative: '',
            original: createCharacterLoraSourceMetadata('', '<lora:original:1>,', ''),
        }),
        fetcher: async () => ({
            ok: true,
            json: async () => ({
                results: [
                    {
                        candidateKey: 'lora:1:trigger:none',
                        status: 'accepted',
                        promptId: 'prompt-accepted',
                    },
                    {
                        candidateKey: 'lora:2:trigger:none',
                        status: 'failed',
                        message: 'ワークフローが未設定です。',
                    },
                ],
            }),
        }),
        csrfToken: 'csrf',
        notify: () => {},
    });
    page.querySelector('[data-character-variant-open]').click();
    page.querySelector('[data-character-variant-send]').click();
    await setImmediate();

    const results = [...page.querySelectorAll('.character-variant-result')].map(
        (element) => element.textContent,
    );
    assert.deepEqual(results, [
        '送信済み（ID: prompt-accepted）',
        '失敗：ワークフローが未設定です。',
    ]);
});
