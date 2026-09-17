import assert from 'node:assert/strict';
import { setImmediate } from 'node:timers/promises';
import test from 'node:test';

import { JSDOM } from 'jsdom';

import { initializePromptPreparationPage } from '../../resources/js/app.js';

const promptSetting = (polarity) => `
    <article data-default-prompt="${polarity}" data-update-url="/default-prompts/${polarity}">
        <button type="button" aria-pressed="true" data-select disabled>
            <span data-selection-marker>✓</span>
        </button>
        <button type="button" data-edit disabled>編集</button>
        <div data-editor hidden>
            <textarea data-editor-content></textarea>
            <p data-editor-status></p>
            <button type="button" data-save>保存</button>
            <button type="button" data-cancel>キャンセル</button>
        </div>
    </article>`;

const promptOutput = (polarity) => `
    <article data-output="${polarity}">
        <button type="button" data-copy disabled>コピー</button>
        <textarea data-output-content></textarea>
        <p data-copy-status></p>
    </article>`;

const createDocument = () => {
    const dom = new JSDOM(`
        <!DOCTYPE html>
        <html>
            <head><meta name="csrf-token" content="csrf-token"></head>
            <body>
                <main data-prompt-preparation data-default-prompts-url="/default-prompts">
                    <div class="load-state">
                        <p data-load-status></p>
                        <button type="button" data-retry hidden>再読み込み</button>
                    </div>
                    ${promptSetting('positive')}
                    ${promptSetting('negative')}
                    <button type="button" data-display disabled>プロンプトを表示</button>
                    <section data-output-section>
                        ${promptOutput('positive')}
                        ${promptOutput('negative')}
                    </section>
                    <div data-toast hidden></div>
                </main>
            </body>
        </html>`);

    return dom.window.document;
};

const successfulResponse = (body) => ({
    ok: true,
    json: async () => body,
});

const flushAsyncEvents = async () => {
    await setImmediate();
    await setImmediate();
};

test('取得したデフォルト文面を選択して出力し直接編集した内容をコピーする', async () => {
    const documentObject = createDocument();
    const fetcher = async () =>
        successfulResponse({
            positive: 'masterpiece, best quality, highres,',
            negative: 'bad anatomy, bad hands,',
        });
    let resolveCopy;
    let copiedContent;
    const copyCompleted = new Promise((resolve) => {
        resolveCopy = resolve;
    });
    const clipboard = {
        writeText: async (content) => {
            copiedContent = content;
            await copyCompleted;
        },
    };
    let scrollOptions;
    documentObject.querySelector('[data-output-section]').scrollIntoView = (options) => {
        scrollOptions = options;
    };

    initializePromptPreparationPage({
        documentObject,
        fetcher,
        clipboard,
        schedule: () => 1,
    });
    await flushAsyncEvents();

    const displayButton = documentObject.querySelector('[data-display]');
    const negativeSetting = documentObject.querySelector('[data-default-prompt="negative"]');
    assert.equal(displayButton.disabled, false);
    negativeSetting.querySelector('[data-select]').click();
    displayButton.click();

    const positiveOutput = documentObject.querySelector('[data-output="positive"]');
    const negativeOutput = documentObject.querySelector('[data-output="negative"]');
    const outputContent = positiveOutput.querySelector('[data-output-content]');
    const copyButton = positiveOutput.querySelector('[data-copy]');
    assert.equal(outputContent.value, 'masterpiece, best quality, highres,');
    assert.equal(negativeOutput.querySelector('[data-output-content]').value, '');
    assert.equal(
        negativeSetting.querySelector('[data-select]').getAttribute('aria-pressed'),
        'false',
    );
    assert.deepEqual(scrollOptions, { behavior: 'smooth', block: 'start' });

    outputContent.value = 'masterpiece, landscape,';
    outputContent.dispatchEvent(new documentObject.defaultView.Event('input'));
    copyButton.click();
    await setImmediate();

    assert.equal(copiedContent, 'masterpiece, landscape,');
    assert.equal(copyButton.disabled, true);
    assert.equal(
        positiveOutput.querySelector('[data-copy-status]').textContent,
        'コピーしています。',
    );

    resolveCopy();
    await flushAsyncEvents();

    assert.equal(copyButton.disabled, false);
    assert.equal(positiveOutput.querySelector('[data-copy-status]').textContent, '');
    assert.equal(
        documentObject.querySelector('[data-toast]').textContent,
        'positiveをコピーしました。',
    );
    assert.equal(documentObject.querySelector('[data-toast]').hidden, false);
});

test('取得失敗後に再読み込みし編集したデフォルト文面を保存する', async () => {
    const documentObject = createDocument();
    const responses = [
        { ok: false },
        successfulResponse({ positive: 'masterpiece,', negative: 'bad anatomy,' }),
        successfulResponse({
            polarity: 'positive',
            content: 'masterpiece, best quality,',
            formatSucceeded: true,
        }),
    ];
    const fetcher = async () => responses.shift();
    const schedule = (callback) => callback();

    initializePromptPreparationPage({ documentObject, fetcher, schedule });
    await flushAsyncEvents();

    const loadStatus = documentObject.querySelector('[data-load-status]');
    const retryButton = documentObject.querySelector('[data-retry]');
    const displayButton = documentObject.querySelector('[data-display]');
    assert.equal(loadStatus.dataset.state, 'error');
    assert.equal(retryButton.hidden, false);
    assert.equal(displayButton.disabled, true);

    retryButton.click();
    await flushAsyncEvents();

    const positiveSetting = documentObject.querySelector('[data-default-prompt="positive"]');
    const editButton = positiveSetting.querySelector('[data-edit]');
    assert.equal(displayButton.disabled, false);
    assert.equal(retryButton.hidden, true);
    assert.equal(loadStatus.textContent, '');
    assert.equal(loadStatus.parentElement.hidden, true);

    editButton.click();
    const editor = positiveSetting.querySelector('[data-editor]');
    const editorContent = positiveSetting.querySelector('[data-editor-content]');
    assert.equal(editor.hidden, false);
    assert.equal(editorContent.value, 'masterpiece,');

    editorContent.value = 'masterpiece\nbest quality';
    positiveSetting.querySelector('[data-save]').click();
    await flushAsyncEvents();

    assert.equal(editor.hidden, true);
    assert.equal(documentObject.activeElement, editButton);
    editButton.click();
    assert.equal(editorContent.value, 'masterpiece, best quality,');
});

test('保存とコピーに失敗した場合は入力内容を保持して手動操作を案内する', async () => {
    const documentObject = createDocument();
    const fetcher = async (url, options) => {
        if (options.method === 'PUT') {
            return { ok: false };
        }

        return successfulResponse({ positive: 'masterpiece,', negative: 'bad anatomy,' });
    };
    const clipboard = {
        writeText: async () => {
            throw new Error('Clipboard unavailable.');
        },
    };

    initializePromptPreparationPage({ documentObject, fetcher, clipboard });
    await flushAsyncEvents();

    const positiveSetting = documentObject.querySelector('[data-default-prompt="positive"]');
    positiveSetting.querySelector('[data-edit]').click();
    const editorContent = positiveSetting.querySelector('[data-editor-content]');
    editorContent.value = 'masterpiece, portrait,';
    positiveSetting.querySelector('[data-save]').click();
    await flushAsyncEvents();

    assert.equal(editorContent.value, 'masterpiece, portrait,');
    assert.equal(positiveSetting.querySelector('[data-editor]').hidden, false);
    assert.equal(
        positiveSetting.querySelector('[data-editor-status]').textContent,
        '保存できませんでした。入力内容を保持しています。再度お試しください。',
    );

    positiveSetting.querySelector('[data-cancel]').click();
    documentObject.querySelector('[data-display]').click();
    const positiveOutput = documentObject.querySelector('[data-output="positive"]');
    const outputContent = positiveOutput.querySelector('[data-output-content]');
    positiveOutput.querySelector('[data-copy]').click();
    await flushAsyncEvents();

    assert.equal(documentObject.activeElement, outputContent);
    assert.equal(
        positiveOutput.querySelector('[data-copy-status]').textContent,
        'コピーできませんでした。文面を選択して手動でコピーしてください。',
    );
});
