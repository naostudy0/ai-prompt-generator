import assert from 'node:assert/strict';
import test from 'node:test';
import { setImmediate } from 'node:timers/promises';
import { JSDOM } from 'jsdom';
import { initializeClothingLoraOptionsPage } from '../../resources/js/clothing-lora-options-page.js';

const createPage = () => {
    const dom = new JSDOM(`<!doctype html><main
        data-clothing-lora-options-url="/clothing-lora-options"
        data-clothing-loras-url="/clothing-loras"
        data-clothing-lora-triggers-url="/clothing-lora-triggers">
        <section data-clothing-lora-options>
            <p data-clothing-lora-status></p><button data-clothing-lora-retry hidden></button>
            <input data-clothing-lora-search><button data-clothing-lora-add></button>
            <div data-clothing-lora-list></div>
        </section>
        <dialog data-clothing-option-dialog><form data-clothing-option-form>
            <h2 data-clothing-option-title></h2><div data-clothing-lora-fields></div>
            <input data-clothing-option-file-name><select data-clothing-option-strength><option value="1">1</option></select>
            <input data-clothing-lora-name><div data-clothing-trigger-fields><input data-clothing-trigger-name><textarea data-clothing-option-content></textarea></div>
            <p data-clothing-option-status></p><button type="button" data-clothing-option-cancel></button>
        </form></dialog>
        <dialog data-clothing-delete-dialog><form data-clothing-delete-form>
            <p data-clothing-delete-message></p><p data-clothing-delete-status></p>
            <button type="button" data-clothing-delete-cancel></button>
        </form></dialog>
    </main>`);
    const { document } = dom.window;
    document.querySelectorAll('dialog').forEach((dialog) => {
        dialog.showModal = () => {
            dialog.open = true;
        };
        dialog.close = () => {
            dialog.open = false;
        };
    });
    return document;
};

const flush = () => setImmediate();
const lora = (id, name, strength) => ({
    id,
    name,
    fileName: `${name}.safetensors`,
    recommendedStrength: strength,
    tags: Array.from(
        { length: 11 },
        (_, step) => `<lora:${name}.safetensors:${step === 10 ? '1' : (step / 10).toFixed(1)}>,`,
    ),
});

test('衣装LoRAを複数選択して個別の強度とトリガーを出力しリセットする', async () => {
    const documentObject = createPage();
    const page = documentObject.querySelector('main');
    let loaded = false;
    let sidebarSnapshot;
    const controller = initializeClothingLoraOptionsPage({
        page,
        documentObject,
        csrfToken: 'token',
        notify: () => {},
        onLoadedChange: (value) => {
            loaded = value;
        },
        onSidebarSnapshotChange: (snapshot) => {
            sidebarSnapshot = snapshot;
        },
        fetcher: async () => ({
            ok: true,
            json: async () => ({
                loras: [lora(1, 'Dress', 0.8), lora(2, 'Jacket', 1)],
                triggers: [
                    { id: 11, loraId: 1, name: '標準', content: 'dress,' },
                    { id: 12, loraId: 2, name: '標準', content: 'jacket,' },
                ],
            }),
        }),
    });
    await flush();

    const toggles = documentObject.querySelectorAll('.clothing-lora-toggle');
    toggles[0].click();
    documentObject.querySelector('.clothing-lora-card select').value = '0.9';
    documentObject
        .querySelector('.clothing-lora-card select')
        .dispatchEvent(new documentObject.defaultView.Event('change'));
    documentObject.querySelectorAll('.clothing-lora-toggle')[1].click();

    assert.equal(loaded, true);
    assert.deepEqual(controller.getSelections(), {
        clothingLoras: '<lora:Dress.safetensors:0.9>, <lora:Jacket.safetensors:1>,',
        clothingLoraTriggers: 'dress, jacket,',
    });
    assert.deepEqual(sidebarSnapshot.selectionGroups, [
        {
            key: 'clothing-lora',
            label: '衣装LoRA',
            items: [
                {
                    label: 'Dress',
                    meta: '強度 0.9',
                    details: ['トリガー：標準'],
                },
                {
                    label: 'Jacket',
                    meta: '強度 1',
                    details: ['トリガー：標準'],
                },
            ],
        },
    ]);

    controller.reset();
    assert.deepEqual(controller.getSelections(), {
        clothingLoras: '',
        clothingLoraTriggers: '',
    });
    assert.equal(documentObject.querySelector('[data-clothing-lora-search]').value, '');
    assert.deepEqual(sidebarSnapshot.selectionGroups[0].items, []);
});
