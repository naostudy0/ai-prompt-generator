import assert from 'node:assert/strict';
import test from 'node:test';

import {
    createCategorySections,
    deleteNamedPrompt,
    filterOptionsKeepingSelection,
    mergeUniquePromptContents,
    requestPromptCategories,
    saveNamedPrompt,
} from '../../resources/js/prompt-categories.js';
import { createPositivePromptSections } from '../../resources/js/positive-prompt-sections.js';

test('表情検索では選択中候補を元の位置に残す', () => {
    const options = [
        { id: 1, name: '笑顔', content: 'smile,' },
        { id: 2, name: '怒り顔', content: 'angry,' },
    ];

    const actual = filterOptionsKeepingSelection(options, '笑顔', new Set([2]));

    assert.deepEqual(
        actual.map((option) => option.id),
        [1, 2],
    );
});

test('場所の完全一致するタグだけを重複除去して括弧付きタグを残す', () => {
    assert.equal(
        mergeUniquePromptContents(['park, outdoors,', 'bench, outdoors, (outdoors), (sky, blue),']),
        'park, outdoors, bench, (outdoors), (sky, blue),',
    );
});

test('括弧の対応が壊れた場所は内容を変更せず結合する', () => {
    assert.equal(
        mergeUniquePromptContents(['park, outdoors,', '(bench, outdoors,']),
        'park, outdoors, (bench, outdoors,',
    );
});

test('オプションは完全一致するタグだけを除き括弧付きと大文字の違いを残す', () => {
    const options = {
        expression: [],
        gaze: [],
        action: [],
        location: [],
        composition: [],
        option: [
            { id: 1, name: '高精細', content: 'detailed, sharp focus,' },
            { id: 2, name: '精密', content: 'sharp focus, (detailed), Detailed,' },
        ],
    };
    const selected = {
        expression: new Set(),
        gaze: new Set(),
        action: new Set(),
        location: new Set(),
        composition: new Set(),
        option: new Set([2, 1]),
    };

    assert.equal(
        createCategorySections({ options, selected }).option,
        'detailed, sharp focus, (detailed), Detailed,',
    );
});

test('括弧の対応が壊れたオプションは内容を変更せず結合する', () => {
    const options = {
        expression: [],
        gaze: [],
        action: [],
        location: [],
        composition: [],
        option: [
            { id: 1, name: '通常', content: 'detailed, sharp focus,' },
            { id: 2, name: '特殊記法', content: '(lighting, dramatic,' },
        ],
    };
    const selected = Object.fromEntries(
        Object.keys(options).map((type) => [type, new Set(type === 'option' ? [1, 2] : [])]),
    );

    assert.equal(
        createCategorySections({ options, selected }).option,
        'detailed, sharp focus, (lighting, dramatic,',
    );
});

test('positiveの各カテゴリを確定した順序で並べる', () => {
    const lora = { lora: 'LoRA', trigger: 'トリガー', outfit: '服装' };
    const categories = {
        expression: '表情',
        gaze: '視線',
        action: '動作',
        location: '場所',
        composition: '構図',
        option: 'オプション',
    };

    assert.deepEqual(createPositivePromptSections(lora, categories), [
        'LoRA',
        'トリガー',
        '表情',
        '視線',
        '服装',
        '動作',
        '場所',
        '構図',
        'オプション',
    ]);
});

test('単一選択と複数選択をカテゴリ別のセクションにする', () => {
    const options = {
        expression: [{ id: 1, name: '笑顔', content: 'smile, open mouth,' }],
        gaze: [{ id: 2, name: 'カメラ目線', content: 'looking at viewer,' }],
        action: [
            { id: 3, name: '座る', content: 'sitting,' },
            { id: 4, name: '読む', content: 'reading,' },
        ],
        location: [
            { id: 5, name: '公園', content: 'park,' },
            { id: 6, name: 'ベンチ', content: 'bench,' },
        ],
        composition: [{ id: 7, name: '正面', content: 'from front,' }],
        option: [
            { id: 8, name: '高精細', content: 'detailed, sharp focus,' },
            { id: 9, name: '精密', content: 'sharp focus, intricate,' },
        ],
    };
    const selected = {
        expression: new Set([1]),
        gaze: new Set([2]),
        action: new Set([3, 4]),
        location: new Set([5, 6]),
        composition: new Set([7]),
        option: new Set([8, 9]),
    };

    assert.deepEqual(createCategorySections({ options, selected }), {
        expression: 'smile, open mouth,',
        gaze: 'looking at viewer,',
        action: 'sitting, reading,',
        location: 'park, bench,',
        composition: 'from front,',
        option: 'detailed, sharp focus, intricate,',
    });
});

test('表情・視線・場所・構図・動作・オプションを同時に取得する', async () => {
    const responses = {
        '/character-directions': { expressions: [], gazes: [] },
        '/scene-directions': { locations: [], compositions: [], actions: [] },
        '/prompt-options': { options: [] },
    };
    const fetcher = async (url) => ({ ok: true, json: async () => responses[url] });

    const actual = await requestPromptCategories(
        fetcher,
        '/character-directions',
        '/scene-directions',
        '/prompt-options',
    );

    assert.deepEqual(actual, {
        expression: [],
        gaze: [],
        location: [],
        composition: [],
        action: [],
        option: [],
    });
});

test('オプション取得が失敗した場合はカテゴリ取得を失敗として扱う', async () => {
    const fetcher = async (url) => ({
        ok: !url.includes('prompt-options'),
        json: async () =>
            url.includes('character')
                ? { expressions: [], gazes: [] }
                : url.includes('scene')
                  ? { locations: [], compositions: [], actions: [] }
                  : { options: [] },
    });

    await assert.rejects(
        requestPromptCategories(
            fetcher,
            '/character-directions',
            '/scene-directions',
            '/prompt-options',
        ),
        /failed/u,
    );
});

test('空の登録名や文面を含む取得レスポンスは形式不正として失敗する', async () => {
    const fetcher = async (url) => ({
        ok: true,
        json: async () =>
            url.includes('character')
                ? { expressions: [{ id: 1, name: ' ', content: 'smile,' }], gazes: [] }
                : url.includes('scene')
                  ? { locations: [], compositions: [], actions: [] }
                  : { options: [] },
    });

    await assert.rejects(
        requestPromptCategories(
            fetcher,
            '/character-directions',
            '/scene-directions',
            '/prompt-options',
        ),
        /invalid/u,
    );
});

test('新規登録と更新はIDの有無に応じたURLとHTTPメソッドで送信する', async () => {
    const requests = [];
    const fetcher = async (url, options) => {
        requests.push({ url, options });
        return { ok: true, json: async () => ({ id: 9 }) };
    };
    const values = { name: '笑顔', content: 'smile,' };

    await saveNamedPrompt(fetcher, '/expressions', 'csrf', null, values);
    await saveNamedPrompt(fetcher, '/expressions', 'csrf', 9, values);

    assert.deepEqual(
        requests.map(({ url, options }) => [
            url,
            options.method,
            options.headers['X-CSRF-TOKEN'],
            options.body,
        ]),
        [
            ['/expressions', 'POST', 'csrf', JSON.stringify(values)],
            ['/expressions/9', 'PUT', 'csrf', JSON.stringify(values)],
        ],
    );
});

test('削除は対象IDとCSRFトークンを指定して送信する', async () => {
    let request;
    const fetcher = async (url, options) => {
        request = { url, options };
        return { ok: true };
    };

    await deleteNamedPrompt(fetcher, '/actions', 'csrf', 7);

    assert.equal(request.url, '/actions/7');
    assert.equal(request.options.method, 'DELETE');
    assert.equal(request.options.headers['X-CSRF-TOKEN'], 'csrf');
});
