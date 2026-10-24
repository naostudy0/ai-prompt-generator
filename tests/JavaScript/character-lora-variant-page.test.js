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
        source: 'characterVariant',
        modelFamilyId: 3,
        loraId: 2,
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

test('31人物をページ分割しページ間で一括送信の選択を保持する', () => {
    const dom = new JSDOM(`<main>
        <button data-character-variant-open>差し替え</button>
        <dialog data-character-variant-dialog>
            <button data-character-variant-close>閉じる</button>
            <input data-character-variant-search>
            <select data-character-variant-family><option value="">all</option><option value="1">Illustrious</option><option value="2">Anima</option></select>
            <button data-character-variant-previous>前へ</button>
            <span data-character-variant-page-status></span>
            <button data-character-variant-next>次へ</button>
            <span data-character-variant-count></span>
            <p data-character-variant-status></p>
            <div data-character-variant-list></div>
        </dialog>
    </main>`);
    const page = dom.window.document.querySelector('main');
    page.querySelector('dialog').showModal = () => {};
    const loras = Array.from({ length: 31 }, (_, index) => ({
        id: index + 1,
        name: `Character ${String(index + 1).padStart(2, '0')}`,
        fileName: `character-${index + 1}`,
        modelFamilyId: index === 30 ? 2 : 1,
        recommendedStrength: 1,
        tags: Array(11).fill(`<lora:character-${index + 1}:1>,`),
    }));
    initializeCharacterLoraVariantPage({
        page,
        documentObject: dom.window.document,
        clipboard: { writeText: async () => {} },
        getCandidates: () => ({ loras, triggers: [] }),
        getSource: () => ({
            positive: '<lora:original:1>,',
            original: createCharacterLoraSourceMetadata('', '<lora:original:1>,', ''),
        }),
        notify: () => {},
    });

    page.querySelector('[data-character-variant-open]').click();
    assert.equal(page.querySelectorAll('.character-variant-row').length, 30);
    assert.equal(
        page.querySelector('[data-character-variant-page-status]').textContent,
        '31人物・1/2ページ',
    );
    page.querySelector('[data-character-variant-next]').click();
    assert.equal(page.querySelectorAll('.character-variant-row').length, 1);
    page.querySelector('.character-variant-send-selection input').click();
    assert.equal(page.querySelector('[data-character-variant-count]').textContent, '30件選択');
    page.querySelector('[data-character-variant-previous]').click();
    page.querySelector('[data-character-variant-next]').click();
    assert.equal(page.querySelector('.character-variant-send-selection input').checked, false);

    const search = page.querySelector('[data-character-variant-search]');
    search.value = 'Character 01';
    search.dispatchEvent(new dom.window.Event('input'));
    assert.equal(
        page.querySelector('[data-character-variant-page-status]').textContent,
        '1人物・1/1ページ',
    );
    const family = page.querySelector('[data-character-variant-family]');
    search.value = '';
    search.dispatchEvent(new dom.window.Event('input'));
    family.value = '2';
    family.dispatchEvent(new dom.window.Event('change'));
    assert.equal(
        page.querySelector('[data-character-variant-page-status]').textContent,
        '1人物・1/1ページ',
    );
    assert.equal(page.querySelector('.character-variant-send-selection input').checked, false);
});

const createGroupDialog = () =>
    new JSDOM(
        `<main data-comfy-ui-prompt-batch-url="/comfyui/prompts/batch">
        <button data-character-variant-open>差し替え</button>
        <dialog data-character-variant-dialog>
            <button data-character-variant-close>閉じる</button>
            <select data-character-variant-group-select></select>
            <button data-character-variant-group-load>呼び出す</button>
            <button data-character-variant-group-delete>削除</button>
            <input data-character-variant-group-name>
            <button data-character-variant-group-save>現在のチェックで保存</button>
            <button data-character-variant-select-all>全選択</button>
            <button data-character-variant-clear-all>全解除</button>
            <input data-character-variant-search>
            <select data-character-variant-family><option value="">all</option></select>
            <button data-character-variant-previous>前へ</button>
            <span data-character-variant-page-status></span>
            <button data-character-variant-next>次へ</button>
            <span data-character-variant-count></span>
            <p data-character-variant-status></p>
            <div data-character-variant-list></div>
            <button data-character-variant-send>一括送信</button>
        </dialog>
    </main>`,
        { url: 'https://example.test' },
    );

const groupCandidates = {
    loras: [
        {
            id: 1,
            name: '一人目',
            fileName: 'first',
            modelFamilyId: 1,
            modelFamilyName: 'Illustrious',
            recommendedStrength: 1,
            tags: Array(11).fill('<lora:first:1>,'),
        },
        {
            id: 2,
            name: '二人目',
            fileName: 'second',
            modelFamilyId: 2,
            modelFamilyName: 'Anima',
            recommendedStrength: 1,
            tags: Array(11).fill('<lora:second:1>,'),
        },
    ],
    triggers: [
        { id: 11, loraId: 1, name: '目あり', content: 'blue eyes,' },
        { id: 12, loraId: 1, name: '目なし', content: 'short hair,' },
    ],
};

const initializeGroupDialog = (dom, overrides = {}) => {
    const page = dom.window.document.querySelector('main');
    page.querySelector('dialog').showModal = () => {};
    initializeCharacterLoraVariantPage({
        page,
        documentObject: dom.window.document,
        clipboard: { writeText: async () => {} },
        getCandidates: () => groupCandidates,
        getSource: () => ({
            positive: '<lora:original:1>,',
            negative: '',
            original: createCharacterLoraSourceMetadata('', '<lora:original:1>,', ''),
        }),
        notify: () => {},
        ...overrides,
    });
    page.querySelector('[data-character-variant-open]').click();
    return page;
};

test('現在のチェックを名前付きで保存し確認後だけ同名上書きと削除を行う', () => {
    const dom = createGroupDialog();
    const confirmations = [];
    dom.window.confirm = (message) => {
        confirmations.push(message);
        return confirmations.length > 1;
    };
    const page = initializeGroupDialog(dom);
    const name = page.querySelector('[data-character-variant-group-name]');
    const save = page.querySelector('[data-character-variant-group-save]');
    const select = page.querySelector('[data-character-variant-group-select]');

    page.querySelectorAll('.character-variant-send-selection input')[1].click();
    name.value = '  Portrait  ';
    save.click();
    assert.equal(select.options.length, 1);
    assert.equal(select.options[0].textContent, 'Portrait');

    page.querySelector('[data-character-variant-clear-all]').click();
    name.value = ' portrait ';
    save.click();
    assert.match(confirmations[0], /Portrait/);
    assert.match(confirmations[0], /0件/);
    assert.equal(
        JSON.parse(dom.window.localStorage.getItem('comfyui-character-variant-groups-v1')).groups[0]
            .candidateKeys.length,
        2,
    );

    save.click();
    assert.equal(select.options[0].textContent, 'portrait');
    assert.equal(
        JSON.parse(dom.window.localStorage.getItem('comfyui-character-variant-groups-v1')).groups[0]
            .candidateKeys.length,
        0,
    );

    name.value = '   ';
    save.click();
    assert.match(page.querySelector('[data-character-variant-status]').textContent, /グループ名/);
    name.value = 'a'.repeat(101);
    save.click();
    assert.match(page.querySelector('[data-character-variant-status]').textContent, /100文字/);

    const checkedBeforeDelete = page.querySelectorAll(
        '.character-variant-send-selection input:checked',
    ).length;
    dom.window.confirm = () => false;
    page.querySelector('[data-character-variant-group-delete]').click();
    assert.equal(select.options.length, 1);
    dom.window.confirm = () => true;
    page.querySelector('[data-character-variant-group-delete]').click();
    assert.equal(select.options[0].textContent, '保存済みグループなし');
    assert.equal(
        page.querySelectorAll('.character-variant-send-selection input:checked').length,
        checkedBeforeDelete,
    );
});

test('グループ呼び出しで全候補を置換し不足を通知して一括送信へ反映する', async () => {
    const dom = createGroupDialog();
    dom.window.localStorage.setItem(
        'comfyui-character-variant-groups-v1',
        JSON.stringify({
            version: 1,
            groups: [
                {
                    name: '構図用',
                    candidateKeys: ['lora:1:trigger:12', 'lora:99:trigger:none'],
                },
            ],
        }),
    );
    dom.window.confirm = () => true;
    const requests = [];
    const page = initializeGroupDialog(dom, {
        fetcher: async (url, options) => {
            requests.push({ url, body: JSON.parse(options.body) });
            return {
                ok: true,
                json: async () => ({
                    results: [
                        {
                            candidateKey: 'lora:1:trigger:12',
                            status: 'accepted',
                            promptId: 'prompt-1',
                        },
                    ],
                }),
            };
        },
    });
    const search = page.querySelector('[data-character-variant-search]');
    search.value = '一人目';
    search.dispatchEvent(new dom.window.Event('input'));
    const family = page.querySelector('[data-character-variant-family]');
    family.value = '1';
    family.dispatchEvent(new dom.window.Event('change'));
    page.querySelector('[data-character-variant-clear-all]').click();
    page.querySelectorAll('.character-variant-send-selection input')[0].click();

    page.querySelector('[data-character-variant-group-load]').click();

    const checks = [...page.querySelectorAll('.character-variant-send-selection input')].map(
        (checkbox) => checkbox.checked,
    );
    assert.deepEqual(checks, [false, true]);
    assert.equal(search.value, '一人目');
    assert.equal(family.value, '1');
    assert.match(
        page.querySelector('[data-character-variant-status]').textContent,
        /利用できない候補1件/,
    );

    page.querySelector('[data-character-variant-send]').click();
    await setImmediate();
    assert.equal(requests.length, 1);
    assert.deepEqual(
        requests[0].body.items.map((item) => item.candidateKey),
        ['lora:1:trigger:12'],
    );
});

test('ストレージを利用できなくてもチェックを変更しグループ保存失敗時は一覧を維持する', () => {
    const dom = createGroupDialog();
    Object.defineProperty(dom.window, 'localStorage', {
        configurable: true,
        get: () => {
            throw new Error('denied');
        },
    });
    const page = initializeGroupDialog(dom);

    assert.equal(
        page.querySelectorAll('.character-variant-send-selection input:checked').length,
        3,
    );
    page.querySelector('.character-variant-send-selection input').click();
    assert.equal(
        page.querySelectorAll('.character-variant-send-selection input:checked').length,
        2,
    );
    page.querySelector('[data-character-variant-group-name]').value = '保存不可';
    page.querySelector('[data-character-variant-group-save]').click();
    assert.equal(
        page.querySelector('[data-character-variant-group-select]').options[0].textContent,
        '保存済みグループなし',
    );
    assert.match(
        page.querySelector('[data-character-variant-status]').textContent,
        /保存できません/,
    );
});
