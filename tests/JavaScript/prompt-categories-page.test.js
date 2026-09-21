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
    assert.equal(controller.restoreSelection([10, 21, 999]), false);
    assert.deepEqual(controller.getSelectionSnapshot(), [10, 21]);
    assert.deepEqual(controller.getSections(), {
        optionGroups: ['smile,', 'looking away,'],
    });
});

test('ブロック名と選択方式を指定して追加する', async () => {
    const documentObject = createDocument();
    let request = null;
    let currentGroups = groups;
    let sidebarSnapshot;
    const fetcher = async (url, options = {}) => {
        if (options.method === 'POST') {
            request = JSON.parse(options.body);
            currentGroups = [
                ...groups,
                {
                    id: 3,
                    name: request.name,
                    selectionMode: request.selectionMode,
                    position: 3,
                    options: [],
                },
            ];
            return { ok: true, status: 201, json: async () => ({}) };
        }
        return { ok: true, json: async () => ({ groups: currentGroups }) };
    };
    initializePromptCategoriesPage({
        page: documentObject.querySelector('main'),
        documentObject,
        fetcher,
        csrfToken: 'csrf',
        onLoadedChange: () => {},
        onSidebarSnapshotChange: (snapshot) => {
            sidebarSnapshot = snapshot;
        },
        notify: () => {},
    });
    await flush();
    documentObject.querySelector('[data-option-group-add]').click();
    documentObject.querySelector('[data-option-group-name]').value = '画質';
    documentObject.querySelector('[data-option-group-mode]').value = 'single';
    documentObject.querySelector('[data-option-group-form]').requestSubmit();
    await flush();
    assert.deepEqual(request, { name: '画質', selectionMode: 'single' });
    assert.deepEqual(
        sidebarSnapshot.navigationItems.map((item) => [item.key, item.label]),
        [
            ['option-group-1', '表情'],
            ['option-group-2', '視線'],
            ['option-group-3', '画質'],
        ],
    );
});

test('単一選択と複数選択で項目を選んでもページ位置を移動しない', async () => {
    const documentObject = createDocument();
    const scrolledGroupIds = [];
    documentObject.defaultView.HTMLElement.prototype.scrollIntoView = function () {
        if (this.dataset.optionGroupId !== undefined) {
            scrolledGroupIds.push(this.dataset.optionGroupId);
        }
    };
    const selectionGroups = [
        groups[0],
        groups[1],
        {
            id: 3,
            name: '構図',
            selectionMode: 'single',
            position: 3,
            options: [{ id: 30, position: 1, name: '正面', content: 'from front,' }],
        },
    ];
    initializePromptCategoriesPage({
        page: documentObject.querySelector('main'),
        documentObject,
        fetcher: async () => ({ ok: true, json: async () => ({ groups: selectionGroups }) }),
        csrfToken: 'csrf',
        onLoadedChange: () => {},
        notify: () => {},
    });
    await flush();
    documentObject.documentElement.scrollTop = 640;

    documentObject.querySelector('[data-option-group-id="1"] .prompt-badge').click();
    await new Promise((resolve) => documentObject.defaultView.setTimeout(resolve, 60));
    assert.deepEqual(scrolledGroupIds, []);
    assert.equal(documentObject.documentElement.scrollTop, 640);

    documentObject.querySelector('[data-option-group-id="2"] .prompt-badge').click();
    await new Promise((resolve) => documentObject.defaultView.setTimeout(resolve, 60));
    assert.deepEqual(scrolledGroupIds, []);
    assert.equal(documentObject.documentElement.scrollTop, 640);

    documentObject.querySelector('[data-option-group-id="2"] .prompt-badge').click();
    assert.deepEqual(scrolledGroupIds, []);
});

test('項目を追加・編集した後もそれぞれの操作前のページ位置を維持する', async () => {
    const documentObject = createDocument();
    const requests = [];
    let currentGroups = groups;
    const fetcher = async (url, options = {}) => {
        if (options.method === 'POST') {
            const request = JSON.parse(options.body);
            requests.push(request);
            documentObject.documentElement.scrollTop = 1400;
            currentGroups = currentGroups.map((group) =>
                group.id === 1
                    ? {
                          ...group,
                          options: [
                              ...group.options,
                              { id: 12, position: 3, name: request.name, content: 'angry,' },
                          ],
                      }
                    : group,
            );
            return { ok: true, status: 201, json: async () => ({}) };
        }
        if (options.method === 'PUT') {
            const request = JSON.parse(options.body);
            requests.push(request);
            documentObject.documentElement.scrollTop = 1300;
            currentGroups = currentGroups.map((group) => ({
                ...group,
                options: group.options.map((option) =>
                    option.id === 12 ? { ...option, name: request.name } : option,
                ),
            }));
            return { ok: true, json: async () => ({}) };
        }
        return { ok: true, json: async () => ({ groups: currentGroups }) };
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
    documentObject.documentElement.scrollTop = 900;
    const itemDialog = documentObject.querySelector('[data-category-dialog]');
    const closeItemDialog = itemDialog.close;
    itemDialog.close = () => {
        closeItemDialog();
        documentObject.defaultView.setTimeout(() => {
            documentObject.documentElement.scrollTop = 5000;
        }, 0);
    };
    const addButton = [
        ...documentObject.querySelectorAll('[data-group-id="1"] .small-button'),
    ].find((button) => button.textContent === '項目追加');
    addButton.click();
    documentObject.querySelector('[data-category-name]').value = '怒り';
    documentObject.querySelector('[data-category-content]').value = 'angry,';
    documentObject.querySelector('[data-category-form]').requestSubmit();
    await flush();
    await new Promise((resolve) => documentObject.defaultView.setTimeout(resolve, 60));

    assert.deepEqual(requests[0], { groupId: 1, name: '怒り', content: 'angry,' });
    assert.equal(documentObject.documentElement.scrollTop, 900);

    documentObject.documentElement.scrollTop = 700;
    documentObject.querySelector('[data-option-id="12"] .badge-manage-toggle').click();
    documentObject.querySelector('[data-category-manage-edit]').click();
    documentObject.querySelector('[data-category-name]').value = '強い怒り';
    documentObject.querySelector('[data-category-form]').requestSubmit();
    await flush();
    await new Promise((resolve) => documentObject.defaultView.setTimeout(resolve, 60));

    assert.deepEqual(requests[1], { name: '強い怒り', content: 'angry,' });
    assert.equal(documentObject.documentElement.scrollTop, 700);
    assert.match(
        documentObject.querySelector('[data-option-id="12"] .prompt-badge').textContent,
        /強い怒り/,
    );
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

test('選択概要を通知し再取得失敗時は動的ナビゲーションを消す', async () => {
    const documentObject = createDocument();
    const snapshots = [];
    let succeeds = true;
    initializePromptCategoriesPage({
        page: documentObject.querySelector('main'),
        documentObject,
        fetcher: async () =>
            succeeds ? { ok: true, json: async () => ({ groups }) } : { ok: false },
        csrfToken: 'csrf',
        onLoadedChange: () => {},
        onSidebarSnapshotChange: (snapshot) => snapshots.push(snapshot),
        notify: () => {},
    });
    await flush();

    documentObject.querySelector('[data-group-id="1"] .prompt-badge').click();
    assert.deepEqual(snapshots.at(-1).navigationItems, [
        {
            key: 'option-group-1',
            label: '表情',
            target: '[data-option-group-id="1"]',
        },
        {
            key: 'option-group-2',
            label: '視線',
            target: '[data-option-group-id="2"]',
        },
    ]);
    assert.equal(typeof snapshots.at(-1).selectionGroups[0].items[0].onRemove, 'function');
    assert.deepEqual(
        snapshots.at(-1).selectionGroups[0].items.map((item) => ({
            label: item.label,
            meta: item.meta,
            details: item.details,
        })),
        [{ label: '笑顔', meta: '', details: [] }],
    );

    succeeds = false;
    documentObject.querySelector('[data-category-retry]').click();
    await flush();

    assert.deepEqual(snapshots.at(-1).navigationItems, []);
    assert.deepEqual(
        snapshots.at(-1).selectionGroups[0].items.map((item) => ({
            label: item.label,
            meta: item.meta,
            details: item.details,
        })),
        [{ label: '笑顔', meta: '', details: [] }],
    );
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
