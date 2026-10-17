import assert from 'node:assert/strict';
import test from 'node:test';

import { JSDOM } from 'jsdom';

import {
    initializePromptClips,
    parsePromptClips,
    PROMPT_CLIPS_STORAGE_KEY,
} from '../../resources/js/prompt-clips.js';

const createStorage = (initial = {}) => {
    const values = new Map(Object.entries(initial));

    return {
        getItem: (key) => values.get(key) ?? null,
        setItem: (key, value) => values.set(key, value),
    };
};

const createPage = () => {
    const dom = new JSDOM(`
        <main>
            <section data-prompt-clips>
                <form data-prompt-clip-form>
                    <input data-prompt-clip-name>
                    <button type="submit">保存</button>
                </form>
                <p data-prompt-clip-empty></p>
                <ul data-prompt-clip-list></ul>
                <p data-prompt-clip-status></p>
            </section>
        </main>
    `);

    return dom.window.document;
};

test('複数のクリップを保存して個別に呼び出す', () => {
    const documentObject = createPage();
    const page = documentObject.querySelector('main');
    const storage = createStorage();
    let outputs = { positive: 'first positive,', negative: 'first negative,' };
    let restored;
    let nextId = 1;
    initializePromptClips({
        page,
        documentObject,
        storage,
        getOutputs: () => outputs,
        restoreOutputs: (value) => {
            restored = value;
        },
        createId: () => String(nextId++),
    });

    const name = page.querySelector('[data-prompt-clip-name]');
    name.value = '最初の案';
    page.querySelector('[data-prompt-clip-form]').requestSubmit();
    outputs = { positive: 'second positive,', negative: '' };
    name.value = '次の案';
    page.querySelector('[data-prompt-clip-form]').requestSubmit();

    assert.deepEqual(
        [...page.querySelectorAll('.prompt-clip__name')].map((element) => element.textContent),
        ['最初の案', '次の案'],
    );
    page.querySelectorAll('.prompt-clip__restore')[1].click();
    assert.deepEqual(restored, {
        id: '2',
        name: '次の案',
        positivePrompt: 'second positive,',
        negativePrompt: '',
    });
    assert.equal(JSON.parse(storage.getItem(PROMPT_CLIPS_STORAGE_KEY)).length, 2);
});

test('同名のクリップを別のIDで保存して片方だけ削除する', () => {
    const documentObject = createPage();
    const page = documentObject.querySelector('main');
    const storage = createStorage();
    let nextId = 1;
    initializePromptClips({
        page,
        documentObject,
        storage,
        getOutputs: () => ({ positive: 'prompt,', negative: '' }),
        restoreOutputs: () => {},
        createId: () => String(nextId++),
    });

    const name = page.querySelector('[data-prompt-clip-name]');
    name.value = '同じ名前';
    page.querySelector('[data-prompt-clip-form]').requestSubmit();
    name.value = '同じ名前';
    page.querySelector('[data-prompt-clip-form]').requestSubmit();
    page.querySelector('.prompt-clip__delete').click();

    assert.equal(page.querySelectorAll('.prompt-clip').length, 1);
    assert.deepEqual(JSON.parse(storage.getItem(PROMPT_CLIPS_STORAGE_KEY)), [
        {
            id: '2',
            name: '同じ名前',
            positivePrompt: 'prompt,',
            negativePrompt: '',
        },
    ]);
});

test('保存済みクリップを再初期化時に読み込む', () => {
    const saved = [
        {
            id: 'clip-1',
            name: '再読み込み後',
            positivePrompt: 'positive,',
            negativePrompt: 'negative,',
        },
    ];
    const documentObject = createPage();
    const page = documentObject.querySelector('main');
    initializePromptClips({
        page,
        documentObject,
        storage: createStorage({ [PROMPT_CLIPS_STORAGE_KEY]: JSON.stringify(saved) }),
        getOutputs: () => ({ positive: '', negative: '' }),
        restoreOutputs: () => {},
    });

    assert.equal(page.querySelector('.prompt-clip__name').textContent, '再読み込み後');
    assert.equal(page.querySelector('[data-prompt-clip-empty]').hidden, true);
});

test('人物LoRA差し替え用の出力元情報を保存して呼び出す', () => {
    const documentObject = createPage();
    const page = documentObject.querySelector('main');
    const storage = createStorage();
    const variantSource = {
        original: { lora: '', defaultPositive: 'masterpiece,' },
        family: { modelFamilyId: 2 },
    };
    let restored;
    initializePromptClips({
        page,
        documentObject,
        storage,
        getOutputs: () => ({ positive: 'masterpiece,', negative: '', variantSource }),
        restoreOutputs: (clip) => {
            restored = clip;
        },
        createId: () => 'clip-1',
    });

    page.querySelector('[data-prompt-clip-form]').requestSubmit();
    page.querySelector('.prompt-clip__restore').click();

    assert.deepEqual(restored.variantSource, variantSource);
    assert.deepEqual(
        JSON.parse(storage.getItem(PROMPT_CLIPS_STORAGE_KEY))[0].variantSource,
        variantSource,
    );
});

test('空の出力や不正な名前は保存しない', () => {
    const documentObject = createPage();
    const page = documentObject.querySelector('main');
    const storage = createStorage();
    initializePromptClips({
        page,
        documentObject,
        storage,
        getOutputs: () => ({ positive: '', negative: '' }),
        restoreOutputs: () => {},
    });

    page.querySelector('[data-prompt-clip-name]').value = '一時案';
    page.querySelector('[data-prompt-clip-form]').requestSubmit();
    assert.match(page.querySelector('[data-prompt-clip-status]').textContent, /出力が必要/);
    assert.equal(storage.getItem(PROMPT_CLIPS_STORAGE_KEY), null);

    page.querySelector('[data-prompt-clip-name]').value = '   ';
    page.querySelector('[data-prompt-clip-form]').requestSubmit();
    assert.match(page.querySelector('[data-prompt-clip-status]').textContent, /名前/);
});

test('名前の前後空白を除き100文字まで保存して101文字は拒否する', () => {
    const documentObject = createPage();
    const page = documentObject.querySelector('main');
    const storage = createStorage();
    let nextId = 1;
    initializePromptClips({
        page,
        documentObject,
        storage,
        getOutputs: () => ({ positive: 'prompt,', negative: '' }),
        restoreOutputs: () => {},
        createId: () => String(nextId++),
    });

    const name = page.querySelector('[data-prompt-clip-name]');
    name.value = `  ${'名'.repeat(100)}  `;
    page.querySelector('[data-prompt-clip-form]').requestSubmit();
    assert.equal(page.querySelector('.prompt-clip__name').textContent, '名'.repeat(100));

    name.value = '名'.repeat(101);
    page.querySelector('[data-prompt-clip-form]').requestSubmit();
    assert.equal(page.querySelectorAll('.prompt-clip').length, 1);
    assert.match(page.querySelector('[data-prompt-clip-status]').textContent, /100文字以内/);
});

test('ストレージへの保存に失敗した場合は一覧へ追加しない', () => {
    const documentObject = createPage();
    const page = documentObject.querySelector('main');
    initializePromptClips({
        page,
        documentObject,
        storage: {
            getItem: () => null,
            setItem: () => {
                throw new Error('quota exceeded');
            },
        },
        getOutputs: () => ({ positive: 'prompt,', negative: '' }),
        restoreOutputs: () => {},
        createId: () => 'clip-1',
    });

    page.querySelector('[data-prompt-clip-form]').requestSubmit();

    assert.equal(page.querySelectorAll('.prompt-clip').length, 0);
    assert.match(page.querySelector('[data-prompt-clip-status]').textContent, /保存できません/);
});

test('クリップIDの生成に失敗した場合は一覧へ追加しない', () => {
    const documentObject = createPage();
    const page = documentObject.querySelector('main');
    const storage = createStorage();
    initializePromptClips({
        page,
        documentObject,
        storage,
        getOutputs: () => ({ positive: 'prompt,', negative: '' }),
        restoreOutputs: () => {},
        createId: () => {
            throw new Error('ID generation failed');
        },
    });

    page.querySelector('[data-prompt-clip-form]').requestSubmit();

    assert.equal(page.querySelectorAll('.prompt-clip').length, 0);
    assert.equal(storage.getItem(PROMPT_CLIPS_STORAGE_KEY), null);
    assert.match(page.querySelector('[data-prompt-clip-status]').textContent, /保存できません/);
});

test('削除の保存に失敗した場合は一覧と保存内容を維持する', () => {
    const documentObject = createPage();
    const page = documentObject.querySelector('main');
    const values = new Map();
    let failWrite = false;
    const storage = {
        getItem: (key) => values.get(key) ?? null,
        setItem: (key, value) => {
            if (failWrite) {
                throw new Error('write failed');
            }
            values.set(key, value);
        },
    };
    initializePromptClips({
        page,
        documentObject,
        storage,
        getOutputs: () => ({ positive: 'prompt,', negative: '' }),
        restoreOutputs: () => {},
        createId: () => 'clip-1',
    });
    page.querySelector('[data-prompt-clip-form]').requestSubmit();
    const saved = storage.getItem(PROMPT_CLIPS_STORAGE_KEY);
    failWrite = true;

    page.querySelector('.prompt-clip__delete').click();

    assert.equal(page.querySelectorAll('.prompt-clip').length, 1);
    assert.equal(storage.getItem(PROMPT_CLIPS_STORAGE_KEY), saved);
    assert.match(page.querySelector('[data-prompt-clip-status]').textContent, /削除できません/);
});

test('不正な保存データを読み込まない', () => {
    assert.throws(() => parsePromptClips('{invalid'));
    assert.throws(() => parsePromptClips('[{"id":1}]'), /形式が不正/);

    const documentObject = createPage();
    const page = documentObject.querySelector('main');
    initializePromptClips({
        page,
        documentObject,
        storage: createStorage({ [PROMPT_CLIPS_STORAGE_KEY]: '[{"id":1}]' }),
        getOutputs: () => ({ positive: '', negative: '' }),
        restoreOutputs: () => {},
    });

    assert.equal(page.querySelectorAll('.prompt-clip').length, 0);
    assert.match(page.querySelector('[data-prompt-clip-status]').textContent, /読み込めません/);
});
