import assert from 'node:assert/strict';
import { setImmediate } from 'node:timers/promises';
import test from 'node:test';
import { JSDOM } from 'jsdom';
import { initializePromptCategoriesPage } from '../../resources/js/prompt-categories-page.js';

const createDocument = () => {
    const categories = [
        ['expression', false, true],
        ['gaze', false, false],
        ['action', true, false],
        ['location', true, false],
        ['composition', false, false],
    ];
    const dom = new JSDOM(`<!DOCTYPE html><main
        data-character-directions-url="/character-directions" data-scene-directions-url="/scene-directions"
        data-expressions-url="/expressions" data-gazes-url="/gazes" data-actions-url="/actions"
        data-locations-url="/locations" data-compositions-url="/compositions">
        <section data-prompt-categories><p data-category-load-status></p><button data-category-retry hidden></button>
        ${categories
            .map(
                ([
                    type,
                    multiple,
                    search,
                ]) => `<div data-prompt-category="${type}" data-multiple="${multiple}">
            <button data-category-add></button>${search ? '<input data-category-search>' : ''}
            ${multiple ? '<div data-category-badges></div>' : '<select data-category-list></select><button data-category-clear></button><button data-category-edit></button><button data-category-delete></button>'}
        </div>`,
            )
            .join('')}</section>
        <dialog data-category-dialog><form data-category-form><h2 data-category-dialog-title></h2><p data-category-editing-id hidden></p><input data-category-id>
        <input data-category-name><textarea data-category-content></textarea><p data-category-form-status></p>
        <button data-category-save></button><button type="button" data-category-cancel></button></form></dialog>
        <dialog data-category-delete-dialog><form data-category-delete-form><p data-category-delete-message></p>
        <p data-category-delete-status></p><button data-category-delete-confirm></button><button type="button" data-category-delete-cancel></button></form></dialog>
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

const character = {
    expressions: [
        { id: 1, name: '笑顔', content: 'smile, open mouth,' },
        { id: 2, name: '怒り顔', content: 'angry,' },
    ],
    gazes: [{ id: 3, name: 'カメラ目線', content: 'looking at viewer,' }],
};
const scene = {
    locations: [
        { id: 4, name: '公園', content: 'park,' },
        { id: 5, name: 'ベンチ', content: 'bench,' },
    ],
    compositions: [{ id: 6, name: '正面', content: 'from front,' }],
    actions: [
        { id: 7, name: '座る', content: 'sitting,' },
        { id: 8, name: '読む', content: 'reading,' },
    ],
};

test('表情と視線は単一選択し場所と動作はバッジで複数選択する', async () => {
    const documentObject = createDocument();
    const fetcher = async (url) => ({
        ok: true,
        json: async () => (url.includes('character') ? character : scene),
    });
    let loaded = false;
    const controller = initializePromptCategoriesPage({
        page: documentObject.querySelector('main'),
        documentObject,
        fetcher,
        csrfToken: 'csrf',
        onLoadedChange: (value) => {
            loaded = value;
        },
        notify: () => {},
    });
    await flush();
    assert.equal(loaded, true);
    assert.deepEqual(controller.getSections(), {
        expression: '',
        gaze: '',
        action: '',
        location: '',
        composition: '',
    });

    const expression = documentObject.querySelector(
        '[data-prompt-category="expression"] [data-category-list]',
    );
    expression.value = '1';
    expression.dispatchEvent(new documentObject.defaultView.Event('change'));
    const gaze = documentObject.querySelector('[data-prompt-category="gaze"] [data-category-list]');
    gaze.value = '3';
    gaze.dispatchEvent(new documentObject.defaultView.Event('change'));
    documentObject.querySelectorAll('[data-prompt-category="action"] .prompt-badge')[0].click();
    documentObject.querySelectorAll('[data-prompt-category="action"] .prompt-badge')[1].click();
    documentObject.querySelectorAll('[data-prompt-category="location"] .prompt-badge')[0].click();
    documentObject.querySelectorAll('[data-prompt-category="location"] .prompt-badge')[1].click();
    const composition = documentObject.querySelector(
        '[data-prompt-category="composition"] [data-category-list]',
    );
    composition.value = '6';
    composition.dispatchEvent(new documentObject.defaultView.Event('change'));

    assert.deepEqual(controller.getSections(), {
        expression: 'smile, open mouth,',
        gaze: 'looking at viewer,',
        action: 'sitting, reading,',
        location: 'park, bench,',
        composition: 'from front,',
    });
    assert.equal(
        documentObject
            .querySelectorAll('[data-prompt-category="action"] .prompt-badge')[0]
            .getAttribute('aria-pressed'),
        'true',
    );
});

test('表情検索は選択中候補を元の位置に残して一致候補を絞り込む', async () => {
    const documentObject = createDocument();
    const fetcher = async (url) => ({
        ok: true,
        json: async () => (url.includes('character') ? character : scene),
    });
    initializePromptCategoriesPage({
        page: documentObject.querySelector('main'),
        documentObject,
        fetcher,
        csrfToken: 'csrf',
        onLoadedChange: () => {},
        notify: () => {},
    });
    await flush();
    const root = documentObject.querySelector('[data-prompt-category="expression"]');
    const list = root.querySelector('[data-category-list]');
    list.value = '2';
    list.dispatchEvent(new documentObject.defaultView.Event('change'));
    const search = root.querySelector('[data-category-search]');
    search.value = '笑顔';
    search.dispatchEvent(new documentObject.defaultView.Event('input'));

    assert.deepEqual(
        [...list.options].map((option) => option.value),
        ['1', '2'],
    );
    assert.equal(list.value, '2');
});

test('保存中は重複送信とダイアログを閉じる操作を防ぐ', async () => {
    const documentObject = createDocument();
    let saveRequestCount = 0;
    let resolveSave;
    const saveResponse = new Promise((resolve) => {
        resolveSave = resolve;
    });
    const fetcher = async (url, options = {}) => {
        if (options.method === 'POST') {
            saveRequestCount++;
            return saveResponse;
        }
        return {
            ok: true,
            json: async () => (url.includes('character') ? character : scene),
        };
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
    documentObject.querySelector('[data-prompt-category="expression"] [data-category-add]').click();
    const dialog = documentObject.querySelector('[data-category-dialog]');
    documentObject.querySelector('[data-category-name]').value = '微笑み';
    documentObject.querySelector('[data-category-content]').value = 'soft smile';
    documentObject.querySelector('[data-category-form]').requestSubmit();
    await flush();

    documentObject.querySelector('[data-category-cancel]').click();
    documentObject.querySelector('[data-category-form]').requestSubmit();

    assert.equal(dialog.open, true);
    assert.equal(saveRequestCount, 1);
    assert.equal(documentObject.querySelector('[data-category-save]').disabled, true);

    resolveSave({ ok: true, json: async () => ({ id: 9 }) });
    await flush();
    await flush();

    assert.equal(dialog.open, false);
});
