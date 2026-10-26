import assert from 'node:assert/strict';
import test from 'node:test';
import { setTimeout as delay } from 'node:timers/promises';
import { JSDOM } from 'jsdom';
import { initializeComfyUiImageRestoration } from '../../resources/js/comfyui-image-restoration.js';

const response = (body, status = 200) => ({
    ok: status >= 200 && status < 300,
    status,
    json: async () => body,
});

const result = {
    modelFamily: { id: 2, name: 'Pony' },
    positivePrompt: '1girl, blue eyes,',
    negativePrompt: 'low quality,',
    selectionSnapshot: { defaultPromptIds: [1], optionIds: [3] },
    review: [
        { kind: 'partial', polarity: 'positive', content: 'blue eyes' },
        { kind: 'unregistered', polarity: 'positive', content: 'long hair' },
    ],
    optionGroups: [{ id: 7, name: '追加タグ' }],
};

const fixture = () => {
    const dom =
        new JSDOM(`<!doctype html><main data-comfy-ui-image-analysis-url="/analyze" data-prompt-options-url="/prompt-options">
        <button data-comfyui-image-restoration-open>open</button>
        <dialog data-comfyui-image-restoration-dialog>
            <button data-comfyui-image-restoration-close>close</button>
            <form data-comfyui-image-restoration-form>
                <input type="file" data-comfyui-image-restoration-file>
                <button data-comfyui-image-restoration-analyze>analyze</button>
            </form>
            <p data-comfyui-image-restoration-status></p>
            <section data-comfyui-image-restoration-preview hidden>
                <p data-comfyui-image-restoration-family></p>
                <textarea data-comfyui-image-restoration-positive></textarea>
                <textarea data-comfyui-image-restoration-negative></textarea>
                <p data-comfyui-image-restoration-summary></p>
                <ul data-comfyui-image-restoration-review></ul>
                <section data-comfyui-image-restoration-save-section hidden>
                    <div data-comfyui-image-restoration-unmatched></div>
                    <input data-comfyui-image-restoration-name>
                    <select data-comfyui-image-restoration-group></select>
                    <button type="button" data-comfyui-image-restoration-save>save</button>
                    <p data-comfyui-image-restoration-save-status></p>
                </section>
                <button data-comfyui-image-restoration-apply>apply</button>
            </section>
        </dialog>
    </main>`);
    const { document } = dom.window;
    const dialog = document.querySelector('dialog');
    dialog.showModal = () => dialog.setAttribute('open', '');
    dialog.close = () => dialog.removeAttribute('open');
    return { dom, document, page: document.querySelector('main'), dialog };
};

const selectFile = (document) => {
    const input = document.querySelector('[data-comfyui-image-restoration-file]');
    Object.defineProperty(input, 'files', {
        configurable: true,
        value: [new document.defaultView.File(['png'], 'fictional.png', { type: 'image/png' })],
    });
};

test('開閉時にフォーカスを戻し、閉じるとファイル参照と表示結果を破棄する', () => {
    const { document, page, dialog } = fixture();
    initializeComfyUiImageRestoration({
        page,
        documentObject: document,
        fetcher: async () => response(result),
    });
    const open = document.querySelector('[data-comfyui-image-restoration-open]');
    open.focus();
    open.click();
    assert.equal(dialog.hasAttribute('open'), true);
    selectFile(document);
    document.querySelector('[data-comfyui-image-restoration-close]').click();
    assert.equal(dialog.hasAttribute('open'), false);
    assert.equal(document.querySelector('[data-comfyui-image-restoration-file]').value, '');
    assert.equal(document.querySelector('[data-comfyui-image-restoration-preview]').hidden, true);
    assert.equal(document.activeElement, open);
});

test('モーダル本体の外側を押したときだけ閉じる', () => {
    const { dom, document, page, dialog } = fixture();
    initializeComfyUiImageRestoration({
        page,
        documentObject: document,
        fetcher: async () => response(result),
    });
    dialog.getBoundingClientRect = () => ({ left: 100, top: 100, right: 500, bottom: 500 });
    document.querySelector('[data-comfyui-image-restoration-open]').click();

    dialog.dispatchEvent(
        new dom.window.MouseEvent('click', { bubbles: true, clientX: 200, clientY: 200 }),
    );
    assert.equal(dialog.hasAttribute('open'), true);

    dialog.dispatchEvent(
        new dom.window.MouseEvent('click', { bubbles: true, clientX: 50, clientY: 50 }),
    );
    assert.equal(dialog.hasAttribute('open'), false);
});

test('PNGを送信し、解析中は多重送信を防いで結果を分類表示する', async () => {
    const { document, page } = fixture();
    let request;
    let resolveRequest;
    const pending = new Promise((resolve) => {
        resolveRequest = resolve;
    });
    initializeComfyUiImageRestoration({
        page,
        documentObject: document,
        csrfToken: 'token',
        fetcher: async (...args) => {
            request = args;
            return pending;
        },
    });
    selectFile(document);
    document.querySelector('[data-comfyui-image-restoration-form]').requestSubmit();
    await delay(0);
    assert.equal(document.querySelector('[data-comfyui-image-restoration-analyze]').disabled, true);
    assert.match(
        document.querySelector('[data-comfyui-image-restoration-status]').textContent,
        /解析中/,
    );
    resolveRequest(response(result));
    await delay(0);
    assert.equal(request[0], '/analyze');
    assert.equal(request[1].headers['X-CSRF-TOKEN'], 'token');
    assert.ok(request[1].body instanceof document.defaultView.FormData);
    assert.equal(
        document.querySelector('[data-comfyui-image-restoration-positive]').value,
        result.positivePrompt,
    );
    assert.equal(
        document.querySelector('[data-comfyui-image-restoration-negative]').value,
        result.negativePrompt,
    );
    assert.match(
        document.querySelector('[data-comfyui-image-restoration-family]').textContent,
        /Pony/,
    );
    assert.match(
        document.querySelector('[data-comfyui-image-restoration-summary]').textContent,
        /2件/,
    );
    assert.equal(document.querySelectorAll('[data-comfyui-image-restoration-review] li').length, 2);
});

test('安全なサーバーエラーを表示し、ファイルを変えず再試行できる', async () => {
    const { document, page } = fixture();
    let calls = 0;
    initializeComfyUiImageRestoration({
        page,
        documentObject: document,
        fetcher: async () => {
            calls += 1;
            return calls === 1
                ? response({ message: '対応していない画像です。' }, 422)
                : response(result);
        },
    });
    selectFile(document);
    const form = document.querySelector('[data-comfyui-image-restoration-form]');
    form.requestSubmit();
    await delay(0);
    assert.equal(
        document.querySelector('[data-comfyui-image-restoration-status]').textContent,
        '対応していない画像です。',
    );
    form.requestSubmit();
    await delay(0);
    assert.equal(calls, 2);
    assert.equal(document.querySelector('[data-comfyui-image-restoration-preview]').hidden, false);
});

test('APIのカテゴリ別確認項目を一つの一覧として表示する', async () => {
    const { document, page } = fixture();
    const categorized = {
        ...result,
        review: {
            partial: [{ kind: 'partial', label: '髪', content: 'long hair' }],
            conflicts: [],
            unregistered: [{ kind: 'unregistered', label: '目', content: 'blue eyes' }],
            loras: [{ kind: 'unknown_lora', label: 'unknown', content: '<lora:unknown:1>' }],
        },
    };
    initializeComfyUiImageRestoration({
        page,
        documentObject: document,
        fetcher: async () => response(categorized),
    });
    selectFile(document);
    document.querySelector('[data-comfyui-image-restoration-form]').requestSubmit();
    await delay(0);

    assert.match(
        document.querySelector('[data-comfyui-image-restoration-summary]').textContent,
        /3件/,
    );
    assert.equal(document.querySelectorAll('[data-comfyui-image-restoration-review] li').length, 3);
    assert.equal(
        document.querySelectorAll('[data-comfyui-image-restoration-unmatched] input').length,
        1,
    );
    const candidate = document.querySelector('[data-comfyui-image-restoration-unmatched] input');
    assert.equal(candidate.getAttribute('aria-label'), 'blue eyesを登録候補として選択');
    assert.equal(candidate.parentElement.classList.contains('restoration-candidate'), true);
    candidate.parentElement.click();
    assert.equal(candidate.checked, true);
});

test('明示的な反映と必要な上書き確認までは画面状態を変更しない', async () => {
    const { document, page } = fixture();
    const applied = [];
    let confirmations = 0;
    initializeComfyUiImageRestoration({
        page,
        documentObject: document,
        fetcher: async () => response(result),
        captureCurrentState: () => ({ hasOutput: true }),
        confirmAction: () => {
            confirmations += 1;
            return false;
        },
        applyRestoration: (value) => applied.push(value),
    });
    selectFile(document);
    document.querySelector('[data-comfyui-image-restoration-form]').requestSubmit();
    await delay(0);
    assert.deepEqual(applied, []);
    document.querySelector('[data-comfyui-image-restoration-apply]').click();
    assert.equal(confirmations, 1);
    assert.deepEqual(applied, []);
});

test('閉じた後に完了した解析結果は表示しない', async () => {
    const { document, page } = fixture();
    let finish;
    initializeComfyUiImageRestoration({
        page,
        documentObject: document,
        fetcher: async () =>
            new Promise((resolve) => {
                finish = resolve;
            }),
    });
    document.querySelector('[data-comfyui-image-restoration-open]').click();
    selectFile(document);
    document.querySelector('[data-comfyui-image-restoration-form]').requestSubmit();
    await delay(0);
    document.querySelector('[data-comfyui-image-restoration-close]').click();
    finish(response(result));
    await delay(0);

    assert.equal(document.querySelector('[data-comfyui-image-restoration-preview]').hidden, true);
    assert.equal(document.querySelector('[data-comfyui-image-restoration-positive]').value, '');
});

test('選んだ不足タグを元の順序で一つの項目として登録し再取得後に選択する', async () => {
    const { document, page } = fixture();
    const analyzed = JSON.parse(JSON.stringify(result));
    analyzed.review.push({ kind: 'unregistered', content: '(smile, open mouth:1.2)' });
    const requests = [];
    const reloaded = [];
    initializeComfyUiImageRestoration({
        page,
        documentObject: document,
        csrfToken: 'token',
        fetcher: async (url, options) => {
            requests.push({ url, options });
            return url === '/analyze' ? response(analyzed) : response({ id: 91 }, 201);
        },
        reloadPromptOptions: async (id) => reloaded.push(id),
    });
    selectFile(document);
    document.querySelector('[data-comfyui-image-restoration-form]').requestSubmit();
    await delay(0);
    const checks = document.querySelectorAll('[data-comfyui-image-restoration-unmatched] input');
    checks.forEach((check) => {
        check.checked = true;
    });
    document.querySelector('[data-comfyui-image-restoration-name]').value = '表情と髪';
    document.querySelector('[data-comfyui-image-restoration-save]').click();
    await delay(0);
    assert.equal(requests[1].url, '/prompt-options');
    assert.deepEqual(JSON.parse(requests[1].options.body), {
        groupId: 7,
        name: '表情と髪',
        content: 'long hair, (smile, open mouth:1.2),',
    });
    assert.deepEqual(reloaded, [91]);
    assert.equal(
        document.querySelector('[data-comfyui-image-restoration-positive]').value,
        analyzed.positivePrompt,
    );
});

test('不足タグの入力不足とAPIエラーでは候補を保持する', async () => {
    const { document, page } = fixture();
    initializeComfyUiImageRestoration({
        page,
        documentObject: document,
        fetcher: async (url) =>
            url === '/analyze'
                ? response(result)
                : response({ message: '同じ名前が登録されています。' }, 422),
    });
    selectFile(document);
    document.querySelector('[data-comfyui-image-restoration-form]').requestSubmit();
    await delay(0);
    document.querySelector('[data-comfyui-image-restoration-save]').click();
    assert.match(
        document.querySelector('[data-comfyui-image-restoration-save-status]').textContent,
        /指定/,
    );
    const check = document.querySelector('[data-comfyui-image-restoration-unmatched] input');
    check.checked = true;
    document.querySelector('[data-comfyui-image-restoration-name]').value = '重複';
    document.querySelector('[data-comfyui-image-restoration-save]').click();
    await delay(0);
    assert.equal(
        document.querySelector('[data-comfyui-image-restoration-save-status]').textContent,
        '同じ名前が登録されています。',
    );
    assert.equal(check.checked, true);
});
