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
                <div data-selection-section="trigger"><select data-trigger-list disabled></select></div>
                <input data-outfit-search disabled>
                <div data-selection-section="outfit"><select data-outfit-list disabled></select></div>
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
            <div data-prompt-category="expression"></div>
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

test('LoRA選択時に先頭トリガーを選択し紐づく服装を上位表示する', async () => {
    const documentObject = createDocument();
    const scrolledSections = [];
    documentObject.defaultView.HTMLElement.prototype.scrollIntoView = function () {
        scrolledSections.push(this.dataset.selectionSection ?? 'other');
    };
    let sidebarSnapshot;
    const catalog = {
        loras: [
            createLora(1, 'Alpha', 'alpha.safetensors', 0.8),
            createLora(2, 'Beta', 'beta.safetensors', 1),
        ],
        triggers: [
            { id: 11, loraId: 1, name: 'Alpha標準', content: 'alpha,' },
            { id: 12, loraId: 2, name: 'Beta標準', content: 'beta,' },
        ],
        outfits: [
            { id: 21, loraId: 1, name: 'Alpha服', content: 'red dress,' },
            { id: 22, loraId: 2, name: 'Beta服', content: 'blue dress,' },
        ],
    };
    const controller = initializeLoraOptionsPage({
        page: documentObject.querySelector('main'),
        documentObject,
        fetcher: async () => ({ ok: true, json: async () => catalog }),
        csrfToken: 'csrf',
        onLoadedChange: () => {},
        onSidebarSnapshotChange: (snapshot) => {
            sidebarSnapshot = snapshot;
        },
        notify: () => {},
    });
    await flushAsyncEvents();
    const loraList = documentObject.querySelector('[data-lora-list]');
    loraList.value = '1';
    loraList.dispatchEvent(new documentObject.defaultView.Event('change'));
    assert.deepEqual(scrolledSections, ['trigger']);
    assert.equal(documentObject.querySelector('[data-lora-strength]').value, '0.8');
    const triggerList = documentObject.querySelector('[data-trigger-list]');
    assert.equal(triggerList.value, '11');
    triggerList.dispatchEvent(new documentObject.defaultView.Event('change'));
    assert.deepEqual(scrolledSections, ['trigger']);
    const outfitList = documentObject.querySelector('[data-outfit-list]');
    outfitList.value = '21';
    outfitList.dispatchEvent(new documentObject.defaultView.Event('change'));
    assert.deepEqual(scrolledSections, ['trigger']);
    assert.deepEqual(
        [...documentObject.querySelector('[data-outfit-list]').options].map(
            (option) => option.value,
        ),
        ['21', '22'],
    );
    assert.equal(controller.getSelections().trigger, 'alpha,');
    assert.equal(
        sidebarSnapshot.selectionGroups[0].items.every(
            (item) => typeof item.onRemove === 'function',
        ),
        true,
    );
    const selectionGroups = sidebarSnapshot.selectionGroups.map((group) => ({
        ...group,
        items: group.items.map((item) => ({
            label: item.label,
            meta: item.meta,
            details: item.details,
        })),
    }));
    assert.deepEqual(selectionGroups, [
        {
            key: 'character-lora',
            label: '人物・キャラクターLoRA',
            items: [
                {
                    label: 'Alpha',
                    meta: '強度 0.8',
                    details: ['トリガー：Alpha標準'],
                },
                { label: '服装：Alpha服', meta: '', details: [] },
            ],
        },
    ]);
});

test('LoRAを切り替えても他のLoRAに属する服装の選択を維持する', async () => {
    const documentObject = createDocument();
    const catalog = {
        loras: [
            createLora(1, 'Alpha', 'alpha.safetensors', 1),
            createLora(2, 'Beta', 'beta.safetensors', 1),
        ],
        triggers: [],
        outfits: [
            { id: 21, loraId: 1, name: 'Alpha服', content: 'red dress,' },
            { id: 22, loraId: 2, name: 'Beta服', content: 'blue dress,' },
        ],
    };
    const controller = initializeLoraOptionsPage({
        page: documentObject.querySelector('main'),
        documentObject,
        fetcher: async () => ({ ok: true, json: async () => catalog }),
        csrfToken: 'csrf',
        onLoadedChange: () => {},
        notify: () => {},
    });
    await flushAsyncEvents();
    const loraList = documentObject.querySelector('[data-lora-list]');
    const outfitList = documentObject.querySelector('[data-outfit-list]');
    loraList.value = '1';
    loraList.dispatchEvent(new documentObject.defaultView.Event('change'));
    outfitList.value = '21';
    outfitList.dispatchEvent(new documentObject.defaultView.Event('change'));
    loraList.value = '2';
    loraList.dispatchEvent(new documentObject.defaultView.Event('change'));
    assert.deepEqual(
        [...outfitList.options].map((option) => option.value),
        ['22', '21'],
    );
    assert.equal(outfitList.value, '21');
    assert.equal(controller.getSelections().outfit, 'red dress,');
    assert.equal(
        controller.restoreSelection({ loraId: 1, strength: 0.8, triggerId: null, outfitId: 22 }),
        true,
    );
    assert.deepEqual(controller.getSelectionSnapshot(), {
        loraId: 1,
        strength: 0.8,
        triggerId: null,
        outfitId: 22,
    });
    assert.equal(controller.getSelections().outfit, 'blue dress,');
});
