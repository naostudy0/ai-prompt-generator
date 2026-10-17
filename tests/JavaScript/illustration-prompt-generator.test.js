import assert from 'node:assert/strict';
import test from 'node:test';
import { setTimeout as delay } from 'node:timers/promises';
import { JSDOM } from 'jsdom';
import { initializeIllustrationPromptGenerator } from '../../resources/js/illustration-prompt-generator.js';

const jsonResponse = (body, { status = 200 } = {}) => ({
    ok: status >= 200 && status < 300,
    status,
    json: async () => body,
});

const fixture = () => {
    const dom =
        new JSDOM(`<!doctype html><main data-prompt-preparation data-illustration-prompt-generation-url="/generate">
        <button data-illustration-prompt-open>open</button>
        <dialog data-illustration-prompt-dialog>
            <button data-illustration-prompt-close>close</button>
            <textarea data-illustration-prompt-description></textarea>
            <select data-illustration-prompt-style><option value="danbooru_tags">tags</option><option value="natural_language">natural</option></select>
            <label><input type="checkbox" data-illustration-prompt-include-defaults checked>defaults</label>
            <button data-illustration-prompt-generate>generate</button>
            <p data-illustration-prompt-status></p>
            <section data-illustration-prompt-preview hidden>
                <textarea data-illustration-prompt-positive></textarea>
                <textarea data-illustration-prompt-negative></textarea>
                <section data-illustration-prompt-defaults hidden>
                    <textarea data-illustration-prompt-default-positive readonly></textarea>
                    <textarea data-illustration-prompt-default-negative readonly></textarea>
                </section>
                <p data-illustration-prompt-metadata></p>
                <button data-illustration-prompt-apply>apply</button>
            </section>
        </dialog>
    </main>`);
    const { document } = dom.window;
    const dialog = document.querySelector('dialog');
    dialog.showModal = () => dialog.setAttribute('open', '');
    dialog.close = () => dialog.removeAttribute('open');

    return { dom, document, page: document.querySelector('main'), dialog };
};

test('生成後は編集可能なプレビューを表示し明示的な反映だけで出力を変更する', async () => {
    const { document, page, dialog } = fixture();
    const requests = [];
    const applied = [];
    const fetcher = async (url, options) => {
        requests.push({ url, options });
        return jsonResponse({
            positivePrompt: 'positive',
            negativePrompt: '',
            model: 'model-a',
            promptStyle: 'danbooru_tags',
            generationTimeMs: 25,
        });
    };
    initializeIllustrationPromptGenerator({
        page,
        documentObject: document,
        fetcher,
        csrfToken: 'token',
        getDefaultPrompts: () => ({ positive: 'best quality,', negative: 'low quality,' }),
        applyPrompt: (value) => applied.push(value),
    });

    document.querySelector('[data-illustration-prompt-open]').click();
    assert.equal(dialog.hasAttribute('open'), true);
    assert.equal(
        document.activeElement,
        document.querySelector('[data-illustration-prompt-description]'),
    );
    document.querySelector('[data-illustration-prompt-description]').value = 'blue eyes';
    document.querySelector('[data-illustration-prompt-generate]').click();
    await delay(0);

    assert.equal(requests[0].url, '/generate');
    assert.equal(requests[0].options.method, 'POST');
    assert.equal(requests[0].options.headers['X-CSRF-TOKEN'], 'token');
    assert.deepEqual(JSON.parse(requests[0].options.body), {
        description: 'blue eyes',
        promptStyle: 'danbooru_tags',
    });
    assert.equal(applied.length, 0);
    assert.equal(document.querySelector('[data-illustration-prompt-positive]').value, 'positive');
    assert.equal(
        document.querySelector('[data-illustration-prompt-default-positive]').value,
        'best quality,',
    );
    assert.equal(
        document.querySelector('[data-illustration-prompt-default-negative]').value,
        'low quality,',
    );
    assert.match(
        document.querySelector('[data-illustration-prompt-metadata]').textContent,
        /model-a.*25/,
    );

    document.querySelector('[data-illustration-prompt-positive]').value = 'edited';
    document.querySelector('[data-illustration-prompt-apply]').click();
    assert.deepEqual(applied, [
        {
            positivePrompt: 'edited',
            negativePrompt: '',
            includeDefaultPrompts: true,
        },
    ]);
    assert.equal(dialog.hasAttribute('open'), false);
});

test('デフォルトを含めない選択を反映処理へ渡し追加予定の表示を隠す', async () => {
    const { document, page } = fixture();
    const applied = [];
    initializeIllustrationPromptGenerator({
        page,
        documentObject: document,
        fetcher: async () =>
            jsonResponse({
                positivePrompt: 'positive',
                negativePrompt: 'negative',
                model: 'model-a',
                promptStyle: 'danbooru_tags',
                generationTimeMs: 25,
            }),
        csrfToken: 'token',
        getDefaultPrompts: () => ({ positive: 'best quality,', negative: 'low quality,' }),
        applyPrompt: (value) => applied.push(value),
    });
    const includeDefaults = document.querySelector('[data-illustration-prompt-include-defaults]');
    includeDefaults.checked = false;
    document.querySelector('[data-illustration-prompt-description]').value = 'blue eyes';

    document.querySelector('[data-illustration-prompt-generate]').click();
    await delay(0);
    assert.equal(document.querySelector('[data-illustration-prompt-defaults]').hidden, true);
    document.querySelector('[data-illustration-prompt-apply]').click();

    assert.deepEqual(applied, [
        {
            positivePrompt: 'positive',
            negativePrompt: 'negative',
            includeDefaultPrompts: false,
        },
    ]);
});

test('空入力と上限超過は送信せずpositiveが空白なら反映しない', async () => {
    const { document, page } = fixture();
    let calls = 0;
    const fetcher = async () => {
        calls += 1;
        return jsonResponse({
            positivePrompt: 'ok',
            negativePrompt: '',
            model: 'm',
            promptStyle: 'danbooru_tags',
            generationTimeMs: 1,
        });
    };
    const applied = [];
    initializeIllustrationPromptGenerator({
        page,
        documentObject: document,
        fetcher,
        csrfToken: 'token',
        applyPrompt: (value) => applied.push(value),
    });
    const description = document.querySelector('[data-illustration-prompt-description]');
    const generate = document.querySelector('[data-illustration-prompt-generate]');

    generate.click();
    description.value = 'a'.repeat(2001);
    generate.click();
    assert.equal(calls, 0);
    description.value = 'ok';
    generate.click();
    await delay(0);
    document.querySelector('[data-illustration-prompt-positive]').value = '   ';
    document.querySelector('[data-illustration-prompt-apply]').click();
    assert.equal(applied.length, 0);
});

test('エラー後に入力を保ったまま再試行でき二重送信を防ぐ', async () => {
    const { document, page } = fixture();
    let resolveRequest;
    let calls = 0;
    const fetcher = () => {
        calls += 1;
        return new Promise((resolve) => {
            resolveRequest = resolve;
        });
    };
    initializeIllustrationPromptGenerator({
        page,
        documentObject: document,
        fetcher,
        csrfToken: 'token',
        applyPrompt: () => {},
    });
    document.querySelector('[data-illustration-prompt-description]').value = 'kept';
    const generate = document.querySelector('[data-illustration-prompt-generate]');
    generate.click();
    generate.click();
    assert.equal(calls, 1);
    assert.equal(generate.disabled, true);
    resolveRequest({
        ok: false,
        status: 503,
        json: async () => {
            throw new Error('invalid json');
        },
    });
    await delay(0);
    assert.equal(document.querySelector('[data-illustration-prompt-description]').value, 'kept');
    assert.equal(generate.disabled, false);
    assert.match(document.querySelector('[data-illustration-prompt-status]').textContent, /再試行/);
});

test('サービス利用不可では入力内容ではなく外部サービスの確認を案内する', async () => {
    const { document, page } = fixture();
    initializeIllustrationPromptGenerator({
        page,
        documentObject: document,
        fetcher: async () =>
            jsonResponse(
                { message: 'プロンプト生成サービスを現在利用できません。' },
                { status: 503 },
            ),
        csrfToken: 'token',
        applyPrompt: () => {},
    });
    document.querySelector('[data-illustration-prompt-description]').value = 'kept';

    document.querySelector('[data-illustration-prompt-generate]').click();
    await delay(0);

    const message = document.querySelector('[data-illustration-prompt-status]').textContent;
    assert.match(message, /外部LLMサービスの接続状態や設定を確認/);
    assert.doesNotMatch(message, /入力内容を確認/);
});

test('閉じた後の遅延応答はプレビューを更新しない', async () => {
    const { document, page } = fixture();
    let resolveRequest;
    const fetcher = () =>
        new Promise((resolve) => {
            resolveRequest = resolve;
        });
    const controller = initializeIllustrationPromptGenerator({
        page,
        documentObject: document,
        fetcher,
        csrfToken: 'token',
        applyPrompt: () => assert.fail(),
    });
    document.querySelector('[data-illustration-prompt-description]').value = 'description';
    document.querySelector('[data-illustration-prompt-generate]').click();
    controller.abort();
    resolveRequest(
        jsonResponse({
            positivePrompt: 'late',
            negativePrompt: '',
            model: 'm',
            promptStyle: 'danbooru_tags',
            generationTimeMs: 1,
        }),
    );
    await delay(0);
    assert.equal(document.querySelector('[data-illustration-prompt-preview]').hidden, true);
});
