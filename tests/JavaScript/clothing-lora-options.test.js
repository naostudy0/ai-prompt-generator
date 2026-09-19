import assert from 'node:assert/strict';
import test from 'node:test';
import {
    createClothingLoraSections,
    filterClothingLoras,
    requestClothingLoraOptions,
} from '../../resources/js/clothing-lora-options.js';

const lora = (id, name, strength) => ({
    id,
    name,
    fileName: `${name}.safetensors`,
    recommendedStrength: strength,
    tags: Array.from(
        { length: 11 },
        (_, step) => `<lora:${name}.safetensors:${step === 10 ? '1' : (step / 10).toFixed(1)}>,`,
    ),
});

test('選択した衣装LoRAを登録名順で出力しトリガーの重複を取り除く', () => {
    const loras = [lora(2, 'B', 1), lora(1, 'A', 0.8)];
    const triggers = [
        { id: 11, loraId: 1, name: 'A標準', content: 'dress, ribbon,' },
        { id: 12, loraId: 2, name: 'B標準', content: 'ribbon, jacket,' },
    ];
    const selections = new Map([
        [2, { strength: 0.9, triggerId: 12 }],
        [1, { strength: 0.8, triggerId: 11 }],
    ]);

    assert.deepEqual(createClothingLoraSections(loras, triggers, selections), {
        loras: '<lora:A.safetensors:0.8>, <lora:B.safetensors:0.9>,',
        triggers: 'dress, ribbon, jacket,',
    });
});

test('検索に一致しない選択中の衣装LoRAを一覧の先頭に残す', () => {
    const loras = [lora(1, 'Alpha', 1), lora(2, 'Beta', 1), lora(3, 'Gamma', 1)];

    assert.deepEqual(
        filterClothingLoras(loras, 'alpha', new Set([3])).map((item) => item.id),
        [3, 1],
    );
});

test('衣装LoRAとトリガーの取得結果を検証する', async () => {
    const result = await requestClothingLoraOptions(
        async () => ({
            ok: true,
            json: async () => ({
                loras: [lora(1, 'Dress', 0.8)],
                triggers: [{ id: 2, loraId: 1, name: '標準', content: 'dress,' }],
            }),
        }),
        '/clothing-lora-options',
    );

    assert.equal(result.loras[0].name, 'Dress');
    assert.equal(result.triggers[0].loraId, 1);
});
