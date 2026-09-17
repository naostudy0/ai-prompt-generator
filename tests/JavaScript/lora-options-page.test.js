import assert from 'node:assert/strict';
import { setImmediate } from 'node:timers/promises';
import test from 'node:test';

import { JSDOM } from 'jsdom';

import { initializeLoraOptionsPage } from '../../resources/js/lora-options-page.js';

const createLora = (id, name, fileName, recommendedStrength) => ({
    id,
    name,
    fileName,
    recommendedStrength,
    tags: Array.from(
        { length: 11 },
        (_, step) => `<lora:${fileName}:${step === 10 ? '1' : (step / 10).toFixed(1)}>,`,
    ),
});

const createDocument = () => {
    const dom = new JSDOM(`
        <!DOCTYPE html>
        <main
            data-lora-options-url="/lora-prompt-options"
            data-loras-url="/loras"
            data-lora-triggers-url="/lora-triggers"
            data-outfits-url="/outfits"
        >
            <section data-lora-options>
                <p data-option-load-status></p>
                <button data-option-retry hidden></button>
                <input data-lora-search disabled>
                <select data-lora-list disabled></select>
                <select data-lora-strength disabled>
                    <option value="0.8">0.8</option><option value="0.9">0.9</option><option value="1">1</option>
                </select>
                <select data-trigger-list disabled></select>
                <select data-outfit-list disabled></select>
                ${['lora', 'trigger', 'outfit']
                    .map(
                        (type) => `
                            <button data-option-add="${type}" disabled></button>
                            <button data-option-edit="${type}" disabled></button>
                            <button data-option-delete="${type}" disabled></button>
                            <button data-option-clear="${type}" disabled></button>`,
                    )
                    .join('')}
            </section>
            <dialog data-option-dialog>
                <form data-option-form>
                    <h2 data-option-dialog-title></h2>
                    <input data-option-id><input data-option-name><input data-option-file-name>
                    <select data-option-strength><option value="1">1</option></select>
                    <textarea data-option-content></textarea><select data-option-lora></select>
                    <div data-lora-field></div><div data-strength-field></div>
                    <div data-content-field></div><div data-association-field></div>
                    <p data-option-form-status></p><button data-option-save></button>
                    <button type="button" data-option-cancel></button>
                </form>
            </dialog>
            <dialog data-delete-dialog>
                <form data-delete-form>
                    <p data-delete-message></p><p data-delete-status></p>
                    <button data-delete-confirm></button><button type="button" data-delete-cancel></button>
                </form>
            </dialog>
        </main>`);
    const { document } = dom.window;

    for (const dialog of document.querySelectorAll('dialog')) {
        dialog.showModal = () => {
            dialog.open = true;
        };
        dialog.close = () => {
            dialog.open = false;
        };
    }

    return document;
};

const flushAsyncEvents = async () => {
    await setImmediate();
    await setImmediate();
};

test('LoRA選択時に推奨強度とトリガーを反映し服装を上位へ並べる', async () => {
    const documentObject = createDocument();
    const catalog = {
        loras: [
            createLora(1, 'Alpha', 'alpha.safetensors', 0.8),
            createLora(2, 'Beta', 'beta.safetensors', 1),
        ],
        triggers: [
            { id: 11, loraId: 1, name: 'Alpha標準', content: 'alpha, long hair,' },
            { id: 12, loraId: 2, name: 'Beta標準', content: 'beta, short hair,' },
        ],
        outfits: [
            { id: 21, loraId: 2, name: 'Beta服', content: 'blue dress,' },
            { id: 22, loraId: 1, name: 'Alpha服', content: 'red dress,' },
            { id: 23, loraId: null, name: '汎用服', content: 'school uniform,' },
        ],
    };
    const fetcher = async () => ({ ok: true, json: async () => catalog });
    let loaded = false;
    const page = documentObject.querySelector('main');

    const controller = initializeLoraOptionsPage({
        page,
        documentObject,
        fetcher,
        csrfToken: 'csrf',
        onLoadedChange: (value) => {
            loaded = value;
        },
        notify: () => {},
    });
    await flushAsyncEvents();

    const loraList = documentObject.querySelector('[data-lora-list]');
    const triggerList = documentObject.querySelector('[data-trigger-list]');
    const outfitList = documentObject.querySelector('[data-outfit-list]');
    const strength = documentObject.querySelector('[data-lora-strength]');
    assert.equal(loaded, true);
    assert.deepEqual(controller.getPositiveSections(), []);

    loraList.value = '1';
    loraList.dispatchEvent(new documentObject.defaultView.Event('change'));

    assert.equal(strength.value, '0.8');
    assert.deepEqual(
        [...triggerList.options].map((option) => option.textContent),
        ['Alpha標準 — alpha, long hair, (#11)'],
    );
    assert.deepEqual(
        [...outfitList.options].map((option) => option.textContent),
        [
            'Alpha服 — Alpha — red dress, (#22)',
            'Beta服 — Beta — blue dress, (#21)',
            '汎用服 — 紐付けなし — school uniform, (#23)',
        ],
    );
    assert.equal(triggerList.value, '');
    assert.equal(outfitList.value, '');

    triggerList.value = '11';
    triggerList.dispatchEvent(new documentObject.defaultView.Event('change'));
    outfitList.value = '23';
    outfitList.dispatchEvent(new documentObject.defaultView.Event('change'));

    assert.deepEqual(controller.getPositiveSections(), [
        '<lora:alpha.safetensors:0.8>,',
        'alpha, long hair,',
        'school uniform,',
    ]);

    loraList.value = '2';
    loraList.dispatchEvent(new documentObject.defaultView.Event('change'));

    assert.equal(triggerList.value, '');
    assert.equal(outfitList.value, '23');
    assert.deepEqual(controller.getPositiveSections(), [
        '<lora:beta.safetensors:1>,',
        'school uniform,',
    ]);
});

test('LoRAを登録名とファイル名で絞り込み選択解除できる', async () => {
    const documentObject = createDocument();
    const catalog = {
        loras: [
            createLora(1, 'Character Alpha', 'alpha.safetensors', 1),
            createLora(2, 'Character Beta', 'BETA-v2.safetensors', 1),
        ],
        triggers: [],
        outfits: [],
    };
    const fetcher = async () => ({ ok: true, json: async () => catalog });
    const page = documentObject.querySelector('main');

    const controller = initializeLoraOptionsPage({
        page,
        documentObject,
        fetcher,
        csrfToken: 'csrf',
        onLoadedChange: () => {},
        notify: () => {},
    });
    await flushAsyncEvents();

    const search = documentObject.querySelector('[data-lora-search]');
    const loraList = documentObject.querySelector('[data-lora-list]');
    loraList.value = '1';
    loraList.dispatchEvent(new documentObject.defaultView.Event('change'));
    assert.notDeepEqual(controller.getPositiveSections(), []);

    search.value = 'beta-v2';
    search.dispatchEvent(new documentObject.defaultView.Event('input'));
    assert.deepEqual(
        [...loraList.options].map((option) => option.value),
        ['2'],
    );
    assert.deepEqual(controller.getPositiveSections(), []);

    loraList.value = '2';
    loraList.dispatchEvent(new documentObject.defaultView.Event('change'));
    documentObject.querySelector('[data-option-clear="lora"]').click();

    assert.deepEqual(controller.getPositiveSections(), []);
});

test('LoRA保存中は重複送信を防ぎ失敗時は入力内容を保持する', async () => {
    const documentObject = createDocument();
    let resolveSave;
    const saveResponse = new Promise((resolve) => {
        resolveSave = resolve;
    });
    const fetcher = async (url, options = {}) => {
        if (options.method === 'POST') {
            return saveResponse;
        }

        return { ok: true, json: async () => ({ loras: [], triggers: [], outfits: [] }) };
    };
    const page = documentObject.querySelector('main');
    initializeLoraOptionsPage({
        page,
        documentObject,
        fetcher,
        csrfToken: 'csrf',
        onLoadedChange: () => {},
        notify: () => {},
    });
    await flushAsyncEvents();

    documentObject.querySelector('[data-option-add="lora"]').click();
    const name = documentObject.querySelector('[data-option-name]');
    const fileName = documentObject.querySelector('[data-option-file-name]');
    const saveButton = documentObject.querySelector('[data-option-save]');
    name.value = 'キャラクター';
    fileName.value = 'character.safetensors';
    documentObject.querySelector('[data-option-form]').requestSubmit();
    await setImmediate();

    assert.equal(saveButton.disabled, true);
    assert.equal(
        documentObject.querySelector('[data-option-form-status]').textContent,
        '保存しています。',
    );

    resolveSave({ ok: false });
    await flushAsyncEvents();

    assert.equal(saveButton.disabled, false);
    assert.equal(name.value, 'キャラクター');
    assert.equal(fileName.value, 'character.safetensors');
    assert.equal(documentObject.querySelector('[data-option-dialog]').open, true);
    assert.equal(
        documentObject.querySelector('[data-option-form-status]').textContent,
        '保存できませんでした。入力内容を確認するか、時間をおいて再度お試しください。',
    );
});

test('モーダル外のクリックで閉じフォーム内のクリックでは閉じない', async () => {
    const documentObject = createDocument();
    const fetcher = async () => ({
        ok: true,
        json: async () => ({ loras: [], triggers: [], outfits: [] }),
    });
    initializeLoraOptionsPage({
        page: documentObject.querySelector('main'),
        documentObject,
        fetcher,
        csrfToken: 'csrf',
        onLoadedChange: () => {},
        notify: () => {},
    });
    await flushAsyncEvents();

    documentObject.querySelector('[data-option-add="lora"]').click();
    const dialog = documentObject.querySelector('[data-option-dialog]');
    dialog.getBoundingClientRect = () => ({ left: 100, right: 500, top: 100, bottom: 500 });

    documentObject.querySelector('[data-option-form]').dispatchEvent(
        new documentObject.defaultView.MouseEvent('click', {
            bubbles: true,
            clientX: 200,
            clientY: 200,
        }),
    );
    assert.equal(dialog.open, true);

    dialog.dispatchEvent(
        new documentObject.defaultView.MouseEvent('click', {
            bubbles: true,
            clientX: 50,
            clientY: 50,
        }),
    );
    assert.equal(dialog.open, false);
});
