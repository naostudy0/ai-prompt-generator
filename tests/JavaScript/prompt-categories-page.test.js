import assert from 'node:assert/strict';
import { setImmediate } from 'node:timers/promises';
import test from 'node:test';
import { JSDOM } from 'jsdom';
import { initializePromptCategoriesPage } from '../../resources/js/prompt-categories-page.js';

const createDocument = () => {
    const dom =
        new JSDOM(`<!DOCTYPE html><main data-prompt-options-url="/options" data-prompt-option-groups-url="/groups">
      <section data-prompt-categories><p data-category-load-status></p><button data-category-retry></button><div data-option-groups></div><button data-option-group-add></button></section>
      <dialog data-option-group-dialog><form data-option-group-form><h2 data-option-group-dialog-title></h2><input data-option-group-name><select data-option-group-mode><option value="single">single</option><option value="multiple">multiple</option></select><p data-option-group-status></p><button data-option-group-save></button><button type="button" data-option-group-cancel></button></form></dialog>
      <dialog data-category-dialog><form data-category-form><h2 data-category-dialog-title></h2><input data-category-id><input data-category-name><textarea data-category-content></textarea><p data-category-form-status></p><button data-category-save></button><button type="button" data-category-cancel></button></form></dialog>
      <dialog data-category-manage-dialog><h2 data-category-manage-title></h2><button data-category-manage-edit></button><button data-category-manage-delete></button><button data-category-manage-cancel></button></dialog>
      <dialog data-category-delete-dialog><form data-category-delete-form><p data-category-delete-message></p><p data-category-delete-status></p><button data-category-delete-confirm></button><button type="button" data-category-delete-cancel></button></form></dialog>
    </main>`);
    for (const dialog of dom.window.document.querySelectorAll('dialog')) {
        dialog.showModal = () => {
            dialog.open = true;
        };
        dialog.close = () => {
            dialog.open = false;
        };
    }
    return dom.window.document;
};
const flush = async () => {
    await setImmediate();
    await setImmediate();
};
const groups = [
    {
        id: 1,
        name: '表情',
        selectionMode: 'multiple',
        position: 1,
        options: [
            { id: 10, position: 1, name: '笑顔', content: 'smile,' },
            { id: 11, position: 2, name: '口を開ける', content: 'open mouth,' },
        ],
    },
    {
        id: 2,
        name: '視線',
        selectionMode: 'single',
        position: 2,
        options: [
            { id: 20, position: 1, name: 'カメラ目線', content: 'looking at viewer,' },
            { id: 21, position: 2, name: '目線外し', content: 'looking away,' },
        ],
    },
];

test('複数選択と単一選択をブロック設定に従って出力する', async () => {
    const documentObject = createDocument();
    const controller = initializePromptCategoriesPage({
        page: documentObject.querySelector('main'),
        documentObject,
        fetcher: async () => ({ ok: true, json: async () => ({ groups }) }),
        csrfToken: 'csrf',
        onLoadedChange: () => {},
        notify: () => {},
    });
    await flush();
    const badges = documentObject.querySelectorAll('.prompt-badge');
    badges[0].click();
    badges[1].click();
    badges[2].click();
    badges[3].click();
    assert.deepEqual(controller.getSections(), {
        optionGroups: ['smile, open mouth,', 'looking away,'],
    });
    controller.reset();
    assert.deepEqual(controller.getSections(), { optionGroups: [] });
});

test('ブロック名と選択方式を指定して追加する', async () => {
    const documentObject = createDocument();
    let request = null;
    const fetcher = async (url, options = {}) => {
        if (options.method === 'POST') {
            request = JSON.parse(options.body);
            return { ok: true, status: 201, json: async () => ({}) };
        }
        return { ok: true, json: async () => ({ groups }) };
    };
    initializePromptCategoriesPage({
        page: documentObject.querySelector('main'),
        documentObject,
        fetcher,
        csrfToken: 'csrf',
        onLoadedChange: () => {},
        notify: () => {},
    });
    await flush();
    documentObject.querySelector('[data-option-group-add]').click();
    documentObject.querySelector('[data-option-group-name]').value = '画質';
    documentObject.querySelector('[data-option-group-mode]').value = 'single';
    documentObject.querySelector('[data-option-group-form]').requestSubmit();
    await flush();
    assert.deepEqual(request, { name: '画質', selectionMode: 'single' });
});

test('スクロールした一覧で項目を選択しても表示位置を維持する', async () => {
    const documentObject = createDocument();
    const scrollPositions = new WeakMap();
    Object.defineProperty(documentObject.defaultView.HTMLElement.prototype, 'scrollTop', {
        configurable: true,
        get() {
            return scrollPositions.get(this) ?? 0;
        },
        set(value) {
            scrollPositions.set(this, this.isConnected ? value : 0);
        },
    });
    initializePromptCategoriesPage({
        page: documentObject.querySelector('main'),
        documentObject,
        fetcher: async () => ({ ok: true, json: async () => ({ groups }) }),
        csrfToken: 'csrf',
        onLoadedChange: () => {},
        notify: () => {},
    });
    await flush();
    const optionList = documentObject.querySelector('[data-group-id="1"] .badge-options');
    optionList.scrollTop = 80;

    optionList.querySelectorAll('.prompt-badge')[1].click();

    assert.equal(documentObject.querySelector('[data-group-id="1"] .badge-options').scrollTop, 80);
});

test('項目には移動先セレクトを置かず管理操作を一つのメニューにまとめる', async () => {
    const documentObject = createDocument();
    initializePromptCategoriesPage({
        page: documentObject.querySelector('main'),
        documentObject,
        fetcher: async () => ({ ok: true, json: async () => ({ groups }) }),
        csrfToken: 'csrf',
        onLoadedChange: () => {},
        notify: () => {},
    });
    await flush();

    assert.equal(documentObject.querySelector('.option-move-select'), null);
    assert.equal(documentObject.querySelectorAll('.badge-option__actions').length, 4);
    assert.equal(documentObject.querySelectorAll('.badge-manage-toggle').length, 4);

    documentObject.querySelectorAll('.badge-manage-toggle')[3].click();
    const manageDialog = documentObject.querySelector('[data-category-manage-dialog]');
    assert.equal(manageDialog.open, true);
    assert.equal(
        documentObject.querySelector('[data-category-manage-title]').textContent,
        '目線外し',
    );
    documentObject.querySelector('[data-category-manage-edit]').click();
    assert.equal(manageDialog.open, false);
    assert.equal(documentObject.querySelector('[data-category-dialog]').open, true);
});
