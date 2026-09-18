import assert from 'node:assert/strict';
import test from 'node:test';

import {
    createLoraPromptSections,
    selectLoraTag,
    createPositivePromptOutput,
    deleteLoraPromptOption,
    filterLoras,
    orderOutfits,
    requestLoraPromptOptions,
    saveLoraPromptOption,
} from '../../resources/js/lora-options.js';

const createTags = (fileName) =>
    Array.from(
        { length: 11 },
        (_, step) => `<lora:${fileName}:${step === 10 ? '1' : (step / 10).toFixed(1)}>,`,
    );

test('登録名とファイル名を大文字小文字を区別せず部分一致で検索する', () => {
    const loras = [
        { id: 1, name: 'Character Alpha', fileName: 'alpha-v1.safetensors' },
        { id: 2, name: 'キャラクターBeta', fileName: 'BETA-v2.safetensors' },
    ];

    assert.deepEqual(filterLoras(loras, 'ALPHA'), [loras[0]]);
    assert.deepEqual(filterLoras(loras, 'beta-v2'), [loras[1]]);
    assert.deepEqual(filterLoras(loras, ''), loras);
});

test('選択したLoRAに紐づく服装を先頭にして他の服装も残す', () => {
    const outfits = [
        { id: 1, loraId: null, name: '汎用服' },
        { id: 2, loraId: 20, name: '別の服' },
        { id: 3, loraId: 10, name: '専用服B' },
        { id: 4, loraId: 10, name: '専用服A' },
    ];

    const actual = orderOutfits(outfits, 10);

    assert.deepEqual(
        actual.map((outfit) => outfit.id),
        [4, 3, 2, 1],
    );
});

test('強度1と0点8に対応するLoRAタグを取得する', () => {
    const lora = { tags: createTags('character.safetensors') };

    assert.equal(selectLoraTag(lora, 1), '<lora:character.safetensors:1>,');
    assert.equal(selectLoraTag(lora, 0.8), '<lora:character.safetensors:0.8>,');
});

test('LoRAとトリガーと服装をそれぞれ独立したセクションとして作る', () => {
    const input = {
        lora: { fileName: 'character.safetensors', tags: createTags('character.safetensors') },
        strength: 0.8,
        trigger: { content: 'character, long hair,' },
        outfit: { content: 'school uniform,' },
    };

    const actual = createLoraPromptSections(input);

    assert.deepEqual(actual, [
        '<lora:character.safetensors:0.8>,',
        'character, long hair,',
        'school uniform,',
    ]);
});

test('未選択のLoRAとトリガーと服装はセクションへ含めない', () => {
    const input = { lora: null, strength: 1, trigger: null, outfit: null };

    assert.deepEqual(createLoraPromptSections(input), []);
});

test('デフォルトとLoRAとトリガーと服装を空行一つで順番に結合する', () => {
    const defaultPrompt = 'masterpiece, best quality,';
    const loraSections = [
        '<lora:character.safetensors:0.8>,',
        'character, long hair,',
        'school uniform,',
    ];
    const expected = `${defaultPrompt}\n\n${loraSections[0]}\n\n${loraSections[1]}\n\n${loraSections[2]}`;

    assert.equal(createPositivePromptOutput(defaultPrompt, loraSections), expected);
});

test('LoRA選択肢を取得して応答形式を検証する', async () => {
    const expected = {
        loras: [
            {
                id: 1,
                name: 'A',
                fileName: 'a.safetensors',
                recommendedStrength: 0.8,
                tags: createTags('a.safetensors'),
            },
        ],
        triggers: [{ id: 2, loraId: 1, name: '標準', content: 'a,' }],
        outfits: [{ id: 3, loraId: 1, name: '制服', content: 'uniform,' }],
    };
    const fetcher = async () => ({ ok: true, json: async () => expected });

    const actual = await requestLoraPromptOptions(fetcher, '/lora-prompt-options');

    assert.deepEqual(actual, expected);
});

test('LoRA選択肢の応答形式が不正な場合は失敗する', async () => {
    const fetcher = async () => ({ ok: true, json: async () => ({ loras: [], triggers: [] }) });

    await assert.rejects(
        () => requestLoraPromptOptions(fetcher, '/lora-prompt-options'),
        new Error('The LoRA prompt option response is invalid.'),
    );
});

test('新規登録はPOSTで更新はPUTで送信する', async () => {
    const requests = [];
    const fetcher = async (url, options) => {
        requests.push({ url, options });
        return { ok: true, json: async () => ({ id: 1 }) };
    };
    const values = {
        name: 'キャラクター',
        fileName: 'character.safetensors',
        recommendedStrength: 1,
    };

    await saveLoraPromptOption(fetcher, '/loras', 'csrf', null, values);
    await saveLoraPromptOption(fetcher, '/loras', 'csrf', 1, values);

    assert.equal(requests[0].url, '/loras');
    assert.equal(requests[0].options.method, 'POST');
    assert.equal(requests[1].url, '/loras/1');
    assert.equal(requests[1].options.method, 'PUT');
    assert.equal(requests[1].options.body, JSON.stringify(values));
});

test('指定した登録をDELETEで削除する', async () => {
    let request;
    const fetcher = async (url, options) => {
        request = { url, options };
        return { ok: true };
    };

    await deleteLoraPromptOption(fetcher, '/outfits', 'csrf', 3);

    assert.equal(request.url, '/outfits/3');
    assert.equal(request.options.method, 'DELETE');
});
