import assert from 'node:assert/strict';
import { setImmediate } from 'node:timers/promises';
import test from 'node:test';
import { JSDOM } from 'jsdom';
import { initializePromptOptionsPage } from '../../resources/js/prompt-options-page.js';

const createDocument = () => {
    const dom =
        new JSDOM(`<!DOCTYPE html><main data-prompt-options-url="/options" data-prompt-option-groups-url="/groups">
      <section data-prompt-categories><p data-category-load-status></p><button data-category-retry></button><div data-option-groups></div><button data-option-group-add></button></section>
      <form data-option-category-form><input data-option-category-id><input data-option-category-name><p data-option-category-status></p><button type="submit">保存</button><button type="button" data-option-category-cancel></button></form>
      <div data-option-category-management-list></div>
      <div data-option-management-groups></div>
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
    const controller = initializePromptOptionsPage({
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
    initializePromptOptionsPage({
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
    initializePromptOptionsPage({
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

test('選択概要を通知し再取得失敗時は動的ナビゲーションを消す', async () => {
    const documentObject = createDocument();
    const snapshots = [];
    let succeeds = true;
    initializePromptOptionsPage({
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
            categoryKey: 'option-category-uncategorized',
            categoryLabel: '未分類',
            categoryId: null,
        },
        {
            key: 'option-group-2',
            label: '視線',
            target: '[data-option-group-id="2"]',
            categoryKey: 'option-category-uncategorized',
            categoryLabel: '未分類',
            categoryId: null,
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

test('通常画面は選択操作だけを表示する', async () => {
    const documentObject = createDocument();
    documentObject.querySelector('[data-option-management-groups]').remove();
    initializePromptOptionsPage({
        page: documentObject.querySelector('main'),
        documentObject,
        fetcher: async () => ({ ok: true, json: async () => ({ groups }) }),
        csrfToken: 'csrf',
        onLoadedChange: () => {},
        notify: () => {},
    });
    await flush();

    assert.equal(documentObject.querySelectorAll('.option-group--selection').length, 2);
    assert.equal(documentObject.querySelectorAll('.drag-handle').length, 0);
    assert.equal(documentObject.querySelectorAll('.badge-manage-toggle').length, 0);
});

test('カテゴリ配下にセクションをまとめ両方を折りたたみ表示する', async () => {
    const documentObject = createDocument();
    const page = documentObject.querySelector('main');
    page.dataset.promptOptionCategoriesUrl = '/categories';
    documentObject.querySelector('[data-option-management-groups]').remove();
    const categorizedGroups = [
        { ...groups[0], categoryId: 1, name: 'ポーズ・手' },
        { ...groups[1], categoryId: 1, name: 'ポーズ・足' },
    ];
    initializePromptOptionsPage({
        page,
        documentObject,
        fetcher: async (url) =>
            url === '/categories'
                ? {
                      ok: true,
                      json: async () => ({
                          categories: [{ id: 1, name: 'ポーズ', position: 1 }],
                      }),
                  }
                : { ok: true, json: async () => ({ groups: categorizedGroups }) },
        csrfToken: 'csrf',
        onLoadedChange: () => {},
        notify: () => {},
    });
    await flush();

    const category = documentObject.querySelector('[data-option-category-id="1"]');
    assert.equal(category.open, true);
    assert.equal(category.querySelector('.category-disclosure-marker').textContent, '▼');
    assert.equal(category.querySelector(':scope > summary').textContent, '▼ポーズ');
    assert.equal(category.querySelector('.option-category-section__summary'), null);
    const managementRow = documentObject.querySelector('.option-category-management-row');
    assert.equal(
        managementRow.querySelector('.option-category-management-row__name').textContent,
        'ポーズ',
    );
    assert.equal(
        managementRow.querySelector('.option-category-management-row__count').textContent,
        '2ブロック',
    );
    assert.ok(managementRow.querySelector('.option-category-management-row__actions'));
    category.open = false;
    category.dispatchEvent(new documentObject.defaultView.Event('toggle'));
    assert.equal(category.querySelector('.category-disclosure-marker').textContent, '▶');
    assert.deepEqual(
        [...category.querySelectorAll('.option-group__title')].map((item) => item.textContent),
        ['ポーズ・手', 'ポーズ・足'],
    );
    const section = category.querySelector('[data-option-group-id="1"]');
    assert.equal(section.querySelector('.section-disclosure-marker').textContent, '▶');
    section.open = true;
    section.dispatchEvent(new documentObject.defaultView.Event('toggle'));
    assert.equal(section.querySelector('.section-disclosure-marker').textContent, '▼');
    category.querySelector('[data-option-group-id="1"] .prompt-badge').click();
    assert.equal(
        documentObject.querySelector('[data-option-group-id="1"] .option-group__selected-summary')
            .textContent,
        '笑顔',
    );
});

test('制作画面でセクションにタグを追加してそのまま選択する', async () => {
    const documentObject = createDocument();
    documentObject.querySelector('[data-option-management-groups]').remove();
    let currentGroups = groups;
    const fetcher = async (url, options = {}) => {
        if (options.method === 'POST') {
            const input = JSON.parse(options.body);
            documentObject.documentElement.scrollTop = 1400;
            const saved = {
                id: 12,
                groupId: input.groupId,
                position: 3,
                name: input.name,
                content: input.content,
                formatSucceeded: true,
            };
            currentGroups = currentGroups.map((group) =>
                group.id === input.groupId
                    ? { ...group, options: [...group.options, saved] }
                    : group,
            );
            return { ok: true, status: 201, json: async () => saved };
        }
        return { ok: true, json: async () => ({ groups: currentGroups }) };
    };
    const controller = initializePromptOptionsPage({
        page: documentObject.querySelector('main'),
        documentObject,
        fetcher,
        csrfToken: 'csrf',
        onLoadedChange: () => {},
        notify: () => {},
    });
    await flush();
    documentObject.documentElement.scrollTop = 900;

    documentObject
        .querySelector('[data-option-group-id="1"] .option-group--selection__actions button')
        .click();
    documentObject.querySelector('[data-category-name]').value = '怒り';
    documentObject.querySelector('[data-category-content]').value = 'angry,';
    documentObject.querySelector('[data-category-form]').requestSubmit();
    await flush();
    await new Promise((resolve) => documentObject.defaultView.setTimeout(resolve, 60));

    assert.deepEqual(controller.getSelectionSnapshot(), [12]);
    assert.deepEqual(controller.getSections(), { optionGroups: ['angry,'] });
    assert.equal(documentObject.documentElement.scrollTop, 900);
});

test('スクロールしたセクションでタグを選択しても一覧位置を維持する', async () => {
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
    initializePromptOptionsPage({
        page: documentObject.querySelector('main'),
        documentObject,
        fetcher: async () => ({ ok: true, json: async () => ({ groups }) }),
        csrfToken: 'csrf',
        onLoadedChange: () => {},
        notify: () => {},
    });
    await flush();
    const optionList = documentObject.querySelector('[data-option-group-id="1"] .badge-options');
    optionList.scrollTop = 80;

    optionList.querySelectorAll('.prompt-badge')[1].click();

    assert.equal(
        documentObject.querySelector('[data-option-group-id="1"] .badge-options').scrollTop,
        80,
    );
});

test('カテゴリ保存後の一覧再取得失敗を保存失敗として再送可能にしない', async () => {
    const documentObject = createDocument();
    const page = documentObject.querySelector('main');
    page.dataset.promptOptionCategoriesUrl = '/categories';
    let categoryGetCount = 0;
    let saveCount = 0;
    const requests = [];
    const notifications = [];
    initializePromptOptionsPage({
        page,
        documentObject,
        fetcher: async (url, options = {}) => {
            requests.push([url, options.method ?? 'GET']);
            if (url === '/categories' && options.method === 'POST') {
                saveCount += 1;
                return {
                    ok: true,
                    status: 201,
                    json: async () => ({ id: 1, name: '人物表現', position: 1 }),
                };
            }
            if (url === '/categories') {
                categoryGetCount += 1;
                if (categoryGetCount > 1) {
                    throw new Error('reload failed');
                }
                return { ok: true, json: async () => ({ categories: [] }) };
            }
            return { ok: true, json: async () => ({ groups }) };
        },
        csrfToken: 'csrf',
        onLoadedChange: () => {},
        notify: (message) => notifications.push(message),
    });
    await flush();

    documentObject.querySelector('[data-option-category-name]').value = '人物表現';
    documentObject
        .querySelector('[data-option-category-form]')
        .dispatchEvent(
            new documentObject.defaultView.Event('submit', { bubbles: true, cancelable: true }),
        );
    await flush();

    assert.deepEqual(requests, [
        ['/options', 'GET'],
        ['/categories', 'GET'],
        ['/categories', 'POST'],
        ['/categories', 'GET'],
    ]);
    assert.equal(saveCount, 1);
    assert.equal(documentObject.querySelector('[data-option-category-name]').value, '');
    assert.equal(documentObject.querySelector('[data-option-category-status]').textContent, '');
    assert.match(notifications.at(-1), /保存済み/);
    assert.match(notifications.at(-1), /再読み込み/);
});
