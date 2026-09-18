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

const addLoraControls = (documentObject) => {
    const page = documentObject.querySelector('main');
    page.dataset.loraOptionsUrl = '/lora-prompt-options';
    page.dataset.lorasUrl = '/loras';
    page.dataset.loraTriggersUrl = '/lora-triggers';
    page.dataset.outfitsUrl = '/outfits';
    page.insertAdjacentHTML(
        'beforeend',
        `<section data-lora-options>
            <p data-option-load-status></p><button data-option-retry hidden></button>
            <input data-lora-search><select data-lora-list></select>
            <select data-lora-strength><option value="0.8">0.8</option><option value="1">1</option></select>
            <select data-trigger-list></select><select data-outfit-list></select>
            ${['lora', 'trigger', 'outfit'].map((type) => `<button data-option-add="${type}"></button><button data-option-edit="${type}"></button><button data-option-delete="${type}"></button><button data-option-clear="${type}"></button>`).join('')}
        </section>
        <dialog data-option-dialog><form data-option-form>
            <h2 data-option-dialog-title></h2><input data-option-id><input data-option-name>
            <input data-option-file-name><select data-option-strength><option value="1">1</option></select>
            <textarea data-option-content></textarea><select data-option-lora></select>
            <div data-lora-field></div><div data-strength-field></div><div data-content-field></div>
            <div data-association-field></div><p data-option-form-status></p>
            <button data-option-save></button><button type="button" data-option-cancel></button>
        </form></dialog>
        <dialog data-delete-dialog><form data-delete-form><p data-delete-message></p>
            <p data-delete-status></p><button data-delete-confirm></button>
            <button type="button" data-delete-cancel></button></form></dialog>`,
    );
};

const addCategoryControls = (documentObject) => {
    const page = documentObject.querySelector('main');
    page.dataset.characterDirectionsUrl = '/character-directions';
    page.dataset.sceneDirectionsUrl = '/scene-directions';
    for (const type of ['expressions', 'gazes', 'actions', 'locations', 'compositions']) {
        page.dataset[`${type}Url`] = `/${type}`;
    }
    page.insertAdjacentHTML(
        'beforeend',
        `<section data-prompt-categories><p data-category-load-status></p><button data-category-retry hidden></button>
            ${[
                ['expression', false],
                ['gaze', false],
                ['action', true],
                ['location', true],
                ['composition', false],
            ]
                .map(
                    ([
                        type,
                        multiple,
                    ]) => `<div data-prompt-category="${type}" data-multiple="${multiple}">
                <button data-category-add></button>${type === 'expression' ? '<input data-category-search>' : ''}
                ${multiple ? '<div data-category-badges></div>' : '<select data-category-list></select><button data-category-clear></button><button data-category-edit></button><button data-category-delete></button>'}
            </div>`,
                )
                .join('')}
        </section>
        <dialog data-category-dialog><form data-category-form><h2 data-category-dialog-title></h2>
            <p data-category-editing-id hidden></p><input data-category-id><input data-category-name>
            <textarea data-category-content></textarea><p data-category-form-status></p>
            <button data-category-save></button><button type="button" data-category-cancel></button>
        </form></dialog>
        <dialog data-category-delete-dialog><form data-category-delete-form><p data-category-delete-message></p>
            <p data-category-delete-status></p><button data-category-delete-confirm></button>
            <button type="button" data-category-delete-cancel></button></form></dialog>`,
    );
};

test('全カテゴリを確定順でpositiveへ出力しnegativeは変えない', async () => {
    const documentObject = createDocument();
    addLoraControls(documentObject);
    addCategoryControls(documentObject);
    const fileName = 'character.safetensors';
    const tags = Array.from(
        { length: 11 },
        (_, step) => `<lora:${fileName}:${step === 10 ? '1' : (step / 10).toFixed(1)}>,`,
    );
    const fetcher = async (url) => {
        if (url === '/default-prompts') {
            return successfulResponse({ positive: 'masterpiece,', negative: 'bad anatomy,' });
        }
        if (url === '/character-directions') {
            return successfulResponse({
                expressions: [{ id: 4, name: '笑顔', content: 'smile,' }],
                gazes: [{ id: 5, name: 'カメラ目線', content: 'looking at viewer,' }],
            });
        }
        if (url === '/scene-directions') {
            return successfulResponse({
                actions: [{ id: 6, name: '座る', content: 'sitting,' }],
                locations: [{ id: 7, name: '公園', content: 'park,' }],
                compositions: [{ id: 8, name: '正面', content: 'from front,' }],
            });
        }
        return successfulResponse({
            loras: [
                {
                    id: 1,
                    name: 'キャラクター',
                    fileName,
                    recommendedStrength: 0.8,
                    tags,
                },
            ],
            triggers: [{ id: 2, loraId: 1, name: '標準', content: 'character, long hair,' }],
            outfits: [{ id: 3, loraId: 1, name: '制服', content: 'school uniform,' }],
        });
    };

    initializePromptPreparationPage({ documentObject, fetcher });
    await flushAsyncEvents();

    const loraList = documentObject.querySelector('[data-lora-list]');
    loraList.value = '1';
    loraList.dispatchEvent(new documentObject.defaultView.Event('change'));
    const triggerList = documentObject.querySelector('[data-trigger-list]');
    triggerList.value = '2';
    triggerList.dispatchEvent(new documentObject.defaultView.Event('change'));
    const outfitList = documentObject.querySelector('[data-outfit-list]');
    outfitList.value = '3';
    outfitList.dispatchEvent(new documentObject.defaultView.Event('change'));
    for (const [type, id] of [
        ['expression', 4],
        ['gaze', 5],
        ['composition', 8],
    ]) {
        const list = documentObject.querySelector(
            `[data-prompt-category="${type}"] [data-category-list]`,
        );
        list.value = String(id);
        list.dispatchEvent(new documentObject.defaultView.Event('change'));
    }
    documentObject.querySelector('[data-prompt-category="action"] .prompt-badge').click();
    documentObject.querySelector('[data-prompt-category="location"] .prompt-badge').click();
    documentObject.querySelector('[data-display]').click();

    assert.equal(
        documentObject.querySelector('[data-output="positive"] [data-output-content]').value,
        'masterpiece,\n\n<lora:character.safetensors:0.8>,\n\ncharacter, long hair,\n\nsmile,\n\nlooking at viewer,\n\nschool uniform,\n\nsitting,\n\npark,\n\nfrom front,',
    );
    assert.equal(
        documentObject.querySelector('[data-output="negative"] [data-output-content]').value,
        'bad anatomy,',
    );
});

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

test('出力内容に合わせて基準サイズ以上で高さを伸縮する', async () => {
    const documentObject = createDocument();
    const fetcher = async () =>
        successfulResponse({ positive: 'masterpiece,', negative: 'bad anatomy,' });
    const positiveContent = documentObject.querySelector(
        '[data-output="positive"] [data-output-content]',
    );
    positiveContent.style.minHeight = '160px';
    let contentHeight = 240;
    Object.defineProperty(positiveContent, 'scrollHeight', {
        configurable: true,
        get: () => contentHeight,
    });

    initializePromptPreparationPage({ documentObject, fetcher });
    await flushAsyncEvents();

    documentObject.querySelector('[data-display]').click();
    assert.equal(positiveContent.style.height, '260px');

    contentHeight = 80;
    documentObject.querySelector('[data-display]').click();
    assert.equal(positiveContent.style.height, '160px');

    contentHeight = 300;
    positiveContent.value = '手入力した長いプロンプト';
    positiveContent.dispatchEvent(new documentObject.defaultView.Event('input'));
    assert.equal(positiveContent.style.height, '320px');
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
