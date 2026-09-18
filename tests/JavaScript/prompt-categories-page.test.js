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
        ['option', true, false],
    ];
    const dom = new JSDOM(`<!DOCTYPE html><main
        data-character-directions-url="/character-directions" data-scene-directions-url="/scene-directions"
        data-expressions-url="/expressions" data-gazes-url="/gazes" data-actions-url="/actions"
        data-locations-url="/locations" data-compositions-url="/compositions" data-prompt-options-url="/prompt-options">
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
const promptOptions = {
    options: [
        { id: 9, name: '高精細', content: 'detailed, sharp focus,' },
        { id: 10, name: '精密', content: 'sharp focus, intricate,' },
    ],
};

const responseFor = (url) =>
    url.includes('character') ? character : url.includes('scene') ? scene : promptOptions;

test('表情と視線は単一選択し場所と動作とオプションはバッジで複数選択する', async () => {
    const documentObject = createDocument();
    const fetcher = async (url) => ({
        ok: true,
        json: async () => responseFor(url),
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
        option: '',
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
    documentObject.querySelectorAll('[data-prompt-category="option"] .prompt-badge')[1].click();
    documentObject.querySelectorAll('[data-prompt-category="option"] .prompt-badge')[0].click();

    assert.deepEqual(controller.getSections(), {
        expression: 'smile, open mouth,',
        gaze: 'looking at viewer,',
        action: 'sitting, reading,',
        location: 'park, bench,',
        composition: 'from front,',
        option: 'detailed, sharp focus, intricate,',
    });
    assert.equal(
        documentObject
            .querySelectorAll('[data-prompt-category="action"] .prompt-badge')[0]
            .getAttribute('aria-pressed'),
        'true',
    );
    const optionBadges = documentObject.querySelectorAll(
        '[data-prompt-category="option"] .prompt-badge',
    );
    assert.equal(optionBadges[0].getAttribute('aria-pressed'), 'true');
    assert.equal(optionBadges[1].getAttribute('aria-pressed'), 'true');
    optionBadges[0].click();
    const updatedOptionBadges = documentObject.querySelectorAll(
        '[data-prompt-category="option"] .prompt-badge',
    );
    assert.equal(updatedOptionBadges[0].getAttribute('aria-pressed'), 'false');
    assert.equal(updatedOptionBadges[1].getAttribute('aria-pressed'), 'true');
    assert.equal(controller.getSections().option, 'sharp focus, intricate,');
});

test('表情検索は選択中候補を元の位置に残して一致候補を絞り込む', async () => {
    const documentObject = createDocument();
    const fetcher = async (url) => ({
        ok: true,
        json: async () => responseFor(url),
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

test('単一選択した表情と視線と構図から直後の入力セクションへスクロールする', async () => {
    const documentObject = createDocument();
    const scrolledSections = [];
    for (const type of ['gaze', 'action', 'option']) {
        documentObject.querySelector(`[data-prompt-category="${type}"]`).scrollIntoView = (
            options,
        ) => {
            scrolledSections.push([type, options]);
        };
    }
    initializePromptCategoriesPage({
        page: documentObject.querySelector('main'),
        documentObject,
        fetcher: async (url) => ({ ok: true, json: async () => responseFor(url) }),
        csrfToken: 'csrf',
        onLoadedChange: () => {},
        notify: () => {},
    });
    await flush();

    const select = (type, value) => {
        const list = documentObject.querySelector(
            `[data-prompt-category="${type}"] [data-category-list]`,
        );
        list.value = value;
        list.dispatchEvent(new documentObject.defaultView.Event('change'));
    };
    select('expression', '1');
    select('gaze', '3');
    select('composition', '6');

    assert.deepEqual(scrolledSections, [
        ['gaze', { behavior: 'smooth', block: 'start' }],
        ['action', { behavior: 'smooth', block: 'start' }],
        ['option', { behavior: 'smooth', block: 'start' }],
    ]);
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
            json: async () => responseFor(url),
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
    const cancelEvent = new documentObject.defaultView.Event('cancel', { cancelable: true });
    dialog.dispatchEvent(cancelEvent);
    documentObject.querySelector('[data-category-form]').requestSubmit();

    assert.equal(dialog.open, true);
    assert.equal(cancelEvent.defaultPrevented, true);
    assert.equal(saveRequestCount, 1);
    assert.equal(documentObject.querySelector('[data-category-save]').disabled, true);
    assert.equal(documentObject.querySelector('[data-category-cancel]').disabled, true);

    resolveSave({ ok: true, json: async () => ({ id: 9 }) });
    await flush();
    await flush();

    assert.equal(dialog.open, false);
});

test('選択中オプションの編集では選択を維持し削除では対象だけを解除する', async () => {
    const documentObject = createDocument();
    let options = promptOptions.options.map((option) => ({ ...option }));
    const requests = [];
    const fetcher = async (url, request = {}) => {
        if (request.method === 'PUT') {
            requests.push([url, request.method]);
            options = options.map((option) =>
                option.id === 9
                    ? { ...option, name: '超高精細', content: 'detailed, ultra,' }
                    : option,
            );
            return { ok: true, json: async () => options[0] };
        }
        if (request.method === 'DELETE') {
            requests.push([url, request.method]);
            options = options.filter((option) => option.id !== 9);
            return { ok: true };
        }
        return {
            ok: true,
            json: async () =>
                url.includes('character') ? character : url.includes('scene') ? scene : { options },
        };
    };
    const controller = initializePromptCategoriesPage({
        page: documentObject.querySelector('main'),
        documentObject,
        fetcher,
        csrfToken: 'csrf',
        onLoadedChange: () => {},
        notify: () => {},
    });
    await flush();
    const optionRoot = documentObject.querySelector('[data-prompt-category="option"]');
    optionRoot.querySelectorAll('.prompt-badge')[0].click();
    optionRoot.querySelectorAll('.prompt-badge')[1].click();
    optionRoot.querySelectorAll('.badge-manage-button')[0].click();
    documentObject.querySelector('[data-category-name]').value = '超高精細';
    documentObject.querySelector('[data-category-content]').value = 'detailed, ultra';
    documentObject.querySelector('[data-category-form]').requestSubmit();
    await flush();
    await flush();

    assert.equal(controller.getSections().option, 'detailed, ultra, sharp focus, intricate,');
    optionRoot.querySelectorAll('.badge-manage-button')[1].click();
    documentObject.querySelector('[data-category-delete-form]').requestSubmit();
    await flush();
    await flush();

    assert.deepEqual(requests, [
        ['/prompt-options/9', 'PUT'],
        ['/prompt-options/9', 'DELETE'],
    ]);
    assert.equal(controller.getSections().option, 'sharp focus, intricate,');
    assert.equal(optionRoot.querySelectorAll('.prompt-badge').length, 1);
    assert.equal(optionRoot.querySelector('.prompt-badge').getAttribute('aria-pressed'), 'true');
});
