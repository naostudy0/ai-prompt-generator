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
                    <nav><div data-section-navigation-list></div></nav>
                    <div class="load-state">
                        <p data-load-status></p>
                        <button type="button" data-retry hidden>再読み込み</button>
                    </div>
                    <div data-default-prompts-section tabindex="-1">
                        ${promptSetting('positive')}
                        ${promptSetting('negative')}
                    </div>
                    <button type="button" data-display disabled>プロンプトを表示</button>
                    <button type="button" data-reset disabled>リセット</button>
                    <section data-output-section>
                        ${promptOutput('positive')}
                        ${promptOutput('negative')}
                    </section>
                    <div data-toast hidden></div>
                    <aside>
                        <div data-selection-summary-list></div>
                        <button type="button" data-sidebar-display disabled>プロンプトを作成</button>
                    </aside>
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

const pendingResponse = () => new Promise(() => {});

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

const addClothingLoraControls = (documentObject) => {
    const page = documentObject.querySelector('main');
    page.dataset.clothingLoraOptionsUrl = '/clothing-lora-options';
    page.dataset.clothingLorasUrl = '/clothing-loras';
    page.dataset.clothingLoraTriggersUrl = '/clothing-lora-triggers';
    page.insertAdjacentHTML(
        'beforeend',
        `<section data-clothing-lora-options><p data-clothing-lora-status></p>
            <button data-clothing-lora-retry hidden></button><input data-clothing-lora-search>
            <button data-clothing-lora-add></button><div data-clothing-lora-list></div></section>
        <dialog data-clothing-option-dialog><form data-clothing-option-form>
            <h2 data-clothing-option-title></h2><div data-clothing-lora-fields></div>
            <input data-clothing-option-file-name><select data-clothing-option-strength><option value="1">1</option></select>
            <input data-clothing-lora-name><div data-clothing-trigger-fields><input data-clothing-trigger-name><textarea data-clothing-option-content></textarea></div>
            <p data-clothing-option-status></p><button type="submit"></button><button type="button" data-clothing-option-cancel></button>
        </form></dialog><dialog data-clothing-delete-dialog><form data-clothing-delete-form>
            <p data-clothing-delete-message></p><p data-clothing-delete-status></p>
            <button type="submit"></button><button type="button" data-clothing-delete-cancel></button>
        </form></dialog>`,
    );
};

const addCategoryControls = (documentObject) => {
    const page = documentObject.querySelector('main');
    page.dataset.promptOptionsUrl = '/prompt-options';
    page.dataset.promptOptionGroupsUrl = '/prompt-option-groups';
    page.insertAdjacentHTML(
        'beforeend',
        `<section data-prompt-categories><p data-category-load-status></p><button data-category-retry hidden></button>
            <div data-option-groups></div><button data-option-group-add></button>
        </section>
        <dialog data-option-group-dialog><form data-option-group-form><h2 data-option-group-dialog-title></h2>
            <input data-option-group-name><select data-option-group-mode><option value="single">single</option><option value="multiple">multiple</option></select>
            <p data-option-group-status></p><button data-option-group-save></button><button type="button" data-option-group-cancel></button></form></dialog>
        <dialog data-category-dialog><form data-category-form><h2 data-category-dialog-title></h2>
            <input data-category-id><input data-category-name><textarea data-category-content></textarea>
            <p data-category-form-status></p><button data-category-save></button><button type="button" data-category-cancel></button>
        </form></dialog>
        <dialog data-category-manage-dialog><h2 data-category-manage-title></h2>
            <button data-category-manage-edit></button><button data-category-manage-delete></button>
            <button data-category-manage-cancel></button></dialog>
        <dialog data-category-delete-dialog><form data-category-delete-form><p data-category-delete-message></p>
            <p data-category-delete-status></p><button data-category-delete-confirm></button>
            <button type="button" data-category-delete-cancel></button></form></dialog>`,
    );
};

test('全カテゴリの選択概要を表示して確定順でpositiveへ出力する', async () => {
    const documentObject = createDocument();
    addLoraControls(documentObject);
    addClothingLoraControls(documentObject);
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
        if (url === '/prompt-options') {
            return successfulResponse({
                groups: [
                    {
                        id: 1,
                        name: '表情',
                        selectionMode: 'multiple',
                        position: 1,
                        options: [{ id: 4, position: 1, name: '笑顔', content: 'smile,' }],
                    },
                    {
                        id: 2,
                        name: '視線',
                        selectionMode: 'single',
                        position: 2,
                        options: [
                            {
                                id: 5,
                                position: 1,
                                name: 'カメラ目線',
                                content: 'looking at viewer,',
                            },
                        ],
                    },
                    {
                        id: 3,
                        name: '動作',
                        selectionMode: 'multiple',
                        position: 3,
                        options: [{ id: 6, position: 1, name: '座る', content: 'sitting,' }],
                    },
                    {
                        id: 4,
                        name: '場所',
                        selectionMode: 'multiple',
                        position: 4,
                        options: [{ id: 7, position: 1, name: '公園', content: 'park,' }],
                    },
                    {
                        id: 5,
                        name: '構図',
                        selectionMode: 'single',
                        position: 5,
                        options: [{ id: 8, position: 1, name: '正面', content: 'from front,' }],
                    },
                    {
                        id: 6,
                        name: '画質',
                        selectionMode: 'multiple',
                        position: 6,
                        options: [
                            {
                                id: 9,
                                position: 1,
                                name: '高精細',
                                content: 'detailed, sharp focus,',
                            },
                            {
                                id: 10,
                                position: 2,
                                name: '精密',
                                content: 'sharp focus, intricate,',
                            },
                        ],
                    },
                ],
            });
        }
        if (url === '/clothing-lora-options') {
            return successfulResponse({
                loras: [
                    {
                        id: 20,
                        name: '衣装追加',
                        fileName: 'clothing.safetensors',
                        recommendedStrength: 0.9,
                        tags: Array.from(
                            { length: 11 },
                            (_, step) =>
                                `<lora:clothing.safetensors:${step === 10 ? '1' : (step / 10).toFixed(1)}>,`,
                        ),
                    },
                ],
                triggers: [{ id: 21, loraId: 20, name: '標準', content: 'clothing trigger,' }],
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
    documentObject
        .querySelectorAll('[data-option-groups] .prompt-badge')
        .forEach((badge) => badge.click());
    documentObject.querySelector('.clothing-lora-toggle').click();

    assert.deepEqual(
        [...documentObject.querySelectorAll('.section-navigation__link')].map(
            (item) => item.textContent,
        ),
        [
            'デフォルト',
            '人物・キャラクターLoRA',
            '衣装LoRA',
            '表情',
            '視線',
            '動作',
            '場所',
            '構図',
            '画質',
        ],
    );
    assert.deepEqual(
        [...documentObject.querySelectorAll('.selection-summary__group h3')].map(
            (item) => item.textContent,
        ),
        [
            'デフォルト',
            '人物・キャラクターLoRA',
            '衣装LoRA',
            '表情',
            '視線',
            '動作',
            '場所',
            '構図',
            '画質',
        ],
    );
    assert.match(
        documentObject.querySelector('[data-selection-summary-list]').textContent,
        /キャラクター/,
    );
    assert.match(
        documentObject.querySelector('[data-selection-summary-list]').textContent,
        /強度 0.8/,
    );
    assert.match(documentObject.querySelector('[data-selection-summary-list]').textContent, /笑顔/);

    documentObject.querySelector('[data-display]').click();

    assert.equal(
        documentObject.querySelector('[data-output="positive"] [data-output-content]').value,
        'masterpiece,\n\n<lora:character.safetensors:0.8>,\n\ncharacter, long hair,\n\n<lora:clothing.safetensors:0.9>,\n\nclothing trigger,\n\nschool uniform,\n\nsmile,\n\nlooking at viewer,\n\nsitting,\n\npark,\n\nfrom front,\n\ndetailed, sharp focus, intricate,',
    );
    assert.equal(
        documentObject.querySelector('[data-output="negative"] [data-output-content]').value,
        'bad anatomy,',
    );
});

test('リセットでデフォルトを選択したまま今回の追加選択と出力を空にしてLoRAへ戻る', async () => {
    const documentObject = createDocument();
    addLoraControls(documentObject);
    addCategoryControls(documentObject);
    const loraSection = documentObject.querySelector('[data-lora-options]');
    let scrollOptions;
    loraSection.scrollIntoView = (options) => {
        scrollOptions = options;
    };
    const fetcher = async (url) => {
        if (url === '/default-prompts') {
            return successfulResponse({ positive: 'masterpiece,', negative: 'bad anatomy,' });
        }
        if (url === '/prompt-options') {
            return successfulResponse({
                groups: [
                    {
                        id: 1,
                        name: '表情',
                        selectionMode: 'multiple',
                        position: 1,
                        options: [{ id: 4, position: 1, name: '笑顔', content: 'smile,' }],
                    },
                ],
            });
        }
        return successfulResponse({
            loras: [
                {
                    id: 1,
                    name: '人物',
                    fileName: 'person.safetensors',
                    recommendedStrength: 0.8,
                    tags: Array.from(
                        { length: 11 },
                        (_, step) =>
                            `<lora:person.safetensors:${step === 10 ? '1' : (step / 10).toFixed(1)}>,`,
                    ),
                },
            ],
            triggers: [],
            outfits: [],
        });
    };

    initializePromptPreparationPage({ documentObject, fetcher });
    await flushAsyncEvents();
    const loraList = documentObject.querySelector('[data-lora-list]');
    loraList.value = '1';
    loraList.dispatchEvent(new documentObject.defaultView.Event('change'));
    documentObject.querySelector('[data-option-groups] .prompt-badge').click();
    documentObject.querySelector('[data-display]').click();
    documentObject.querySelector('[data-output="positive"] [data-output-content]').value +=
        ' edited';
    documentObject.querySelector('[data-reset]').click();

    assert.equal(loraList.selectedIndex, -1);
    assert.equal(
        documentObject
            .querySelector('[data-option-groups] .prompt-badge')
            .getAttribute('aria-pressed'),
        'false',
    );
    assert.equal(
        documentObject.querySelector('[data-output="positive"] [data-output-content]').value,
        '',
    );
    assert.equal(
        documentObject.querySelector('[data-output="negative"] [data-output-content]').value,
        '',
    );
    assert.equal(
        documentObject
            .querySelector('[data-default-prompt="positive"] [data-select]')
            .getAttribute('aria-pressed'),
        'true',
    );
    assert.equal(
        documentObject
            .querySelector('[data-default-prompt="negative"] [data-select]')
            .getAttribute('aria-pressed'),
        'true',
    );
    assert.deepEqual(scrollOptions, { behavior: 'smooth', block: 'start' });
    assert.deepEqual(
        [...documentObject.querySelectorAll('.selection-summary__group h3')].map(
            (item) => item.textContent,
        ),
        ['デフォルト'],
    );
    assert.deepEqual(
        [...documentObject.querySelectorAll('.selection-summary__item-label')].map(
            (item) => item.textContent,
        ),
        ['positive デフォルト', 'negative デフォルト'],
    );
});

test('右メニューからデフォルト文面を出力し直接編集した内容をコピーする', async () => {
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
    const sidebarDisplayButton = documentObject.querySelector('[data-sidebar-display]');
    const negativeSetting = documentObject.querySelector('[data-default-prompt="negative"]');
    assert.equal(displayButton.disabled, false);
    assert.equal(sidebarDisplayButton.disabled, false);
    negativeSetting.querySelector('[data-select]').click();
    sidebarDisplayButton.click();

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

test('他カテゴリの取得中でも選択済みのデフォルト文面を出力する', async () => {
    const documentObject = createDocument();
    addLoraControls(documentObject);
    addClothingLoraControls(documentObject);
    addCategoryControls(documentObject);
    const fetcher = async (url) => {
        if (url === '/default-prompts') {
            return successfulResponse({ positive: 'masterpiece,', negative: 'bad anatomy,' });
        }

        return pendingResponse();
    };

    initializePromptPreparationPage({ documentObject, fetcher });
    await flushAsyncEvents();

    const displayButton = documentObject.querySelector('[data-display]');
    const sidebarDisplayButton = documentObject.querySelector('[data-sidebar-display]');
    assert.equal(displayButton.disabled, false);
    assert.equal(sidebarDisplayButton.disabled, false);

    sidebarDisplayButton.click();

    assert.equal(
        documentObject.querySelector('[data-output="positive"] [data-output-content]').value,
        'masterpiece,',
    );
    assert.equal(
        documentObject.querySelector('[data-output="negative"] [data-output-content]').value,
        'bad anatomy,',
    );

    documentObject.querySelector('[data-default-prompt="positive"] [data-select]').click();
    documentObject.querySelector('[data-default-prompt="negative"] [data-select]').click();

    assert.equal(displayButton.disabled, true);
    assert.equal(sidebarDisplayButton.disabled, true);
});

test('デフォルト取得中でもLoRAの選択後は他カテゴリの完了順に関係なく出力できる', async () => {
    const documentObject = createDocument();
    addLoraControls(documentObject);
    addCategoryControls(documentObject);
    let resolvePromptOptions;
    const promptOptionsResponse = new Promise((resolve) => {
        resolvePromptOptions = resolve;
    });
    const fetcher = async (url) => {
        if (url === '/default-prompts') {
            return pendingResponse();
        }
        if (url === '/prompt-options') {
            return promptOptionsResponse;
        }

        return successfulResponse({
            loras: [
                {
                    id: 1,
                    name: 'キャラクター',
                    fileName: 'character.safetensors',
                    recommendedStrength: 1,
                    tags: Array.from(
                        { length: 11 },
                        (_, step) =>
                            `<lora:character.safetensors:${step === 10 ? '1' : (step / 10).toFixed(1)}>,`,
                    ),
                },
            ],
            triggers: [],
            outfits: [],
        });
    };

    initializePromptPreparationPage({ documentObject, fetcher });
    await flushAsyncEvents();

    const loraList = documentObject.querySelector('[data-lora-list]');
    const displayButton = documentObject.querySelector('[data-display]');
    const sidebarDisplayButton = documentObject.querySelector('[data-sidebar-display]');
    loraList.value = '1';
    loraList.dispatchEvent(new documentObject.defaultView.Event('change'));

    assert.equal(displayButton.disabled, false);
    assert.equal(sidebarDisplayButton.disabled, false);

    resolvePromptOptions(successfulResponse({ groups: [] }));
    await flushAsyncEvents();

    assert.equal(displayButton.disabled, false);
    assert.equal(sidebarDisplayButton.disabled, false);
    sidebarDisplayButton.click();
    assert.equal(
        documentObject.querySelector('[data-output="positive"] [data-output-content]').value,
        '<lora:character.safetensors:1>,',
    );
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
    const sidebarDisplayButton = documentObject.querySelector('[data-sidebar-display]');
    assert.equal(loadStatus.dataset.state, 'error');
    assert.equal(retryButton.hidden, false);
    assert.equal(displayButton.disabled, true);
    assert.equal(sidebarDisplayButton.disabled, true);

    retryButton.click();
    await flushAsyncEvents();

    const positiveSetting = documentObject.querySelector('[data-default-prompt="positive"]');
    const editButton = positiveSetting.querySelector('[data-edit]');
    assert.equal(displayButton.disabled, false);
    assert.equal(sidebarDisplayButton.disabled, false);
    assert.equal(retryButton.hidden, true);
    assert.equal(loadStatus.textContent, '');
    assert.equal(loadStatus.parentElement.hidden, true);

    editButton.click();
    const editor = positiveSetting.querySelector('[data-editor]');
    const editorContent = positiveSetting.querySelector('[data-editor-content]');
    assert.equal(editor.hidden, false);
    assert.equal(editorContent.value, 'masterpiece,');
    assert.equal(displayButton.disabled, true);
    assert.equal(sidebarDisplayButton.disabled, true);

    editorContent.value = 'masterpiece\nbest quality';
    positiveSetting.querySelector('[data-save]').click();
    assert.equal(displayButton.disabled, true);
    assert.equal(sidebarDisplayButton.disabled, true);
    await flushAsyncEvents();

    assert.equal(editor.hidden, true);
    assert.equal(displayButton.disabled, false);
    assert.equal(sidebarDisplayButton.disabled, false);
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
