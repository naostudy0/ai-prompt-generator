import assert from 'node:assert/strict';
import test from 'node:test';

import { prepareFavoriteCharacterLoraQueueItem } from '../../resources/js/favorite-character-lora-application.js';

const originalLora = {
    id: 1,
    name: '元人物',
    fileName: 'original.safetensors',
    recommendedStrength: 1,
    modelFamilyId: 1,
    tags: Array.from(
        { length: 11 },
        (_, step) => `<lora:original.safetensors:${step === 10 ? '1' : (step / 10).toFixed(1)}>,`,
    ),
};
const selectedLora = {
    id: 2,
    name: '選択人物',
    fileName: 'selected.safetensors',
    recommendedStrength: 0.8,
    modelFamilyId: 4,
    tags: Array.from(
        { length: 11 },
        (_, step) => `<lora:selected.safetensors:${step === 10 ? '1' : (step / 10).toFixed(1)}>,`,
    ),
};
const originalTrigger = { id: 11, loraId: 1, name: '元標準', content: 'brown hair,' };
const selectedTrigger = { id: 22, loraId: 2, name: '選択標準', content: 'silver hair,' };
const options = {
    loras: [originalLora, selectedLora],
    triggers: [originalTrigger, selectedTrigger],
    outfits: [],
};
const application = {
    options,
    selection: { loraId: 2, strength: 0.7, triggerId: 22 },
    selectedSections: { lora: '<lora:selected.safetensors:0.7>,', trigger: 'silver hair,' },
};

const favorite = (overrides = {}) => ({
    positivePrompt:
        'masterpiece,\n\n<lora:original.safetensors:1>,\n\nbrown hair,\n\n<lora:dress:0.9>,\n\npark, custom,',
    negativePrompt: 'bad anatomy,',
    selectionSnapshot: {
        defaultSections: { positive: 'masterpiece,', negative: '' },
        lora: { loraId: 1, strength: 1, triggerId: 11, outfitId: null },
    },
    ...overrides,
});

test('お気に入りの人物LoRAだけを現在選択へ差し替えてキュー項目を作る', () => {
    const saved = favorite();
    const result = prepareFavoriteCharacterLoraQueueItem({
        favorite: saved,
        key: 'favorite:7',
        ...application,
    });

    assert.deepEqual(result, {
        candidateKey: 'favorite:7',
        modelFamilyId: 4,
        loraId: 2,
        positive:
            'masterpiece,\n\n<lora:selected.safetensors:0.7>,\n\nsilver hair,\n\n<lora:dress:0.9>,\n\npark, custom,',
        negative: 'bad anatomy,',
    });
    assert.equal(saved.positivePrompt.includes('original.safetensors'), true);
    assert.equal(application.selection.strength, 0.7);
});

test('人物LoRAなしのお気に入りへデフォルト直後に現在選択を挿入する', () => {
    const result = prepareFavoriteCharacterLoraQueueItem({
        favorite: favorite({
            positivePrompt: 'masterpiece,\n\n<lora:dress:0.9>,\n\npark, custom,',
            selectionSnapshot: {
                defaultSections: { positive: 'masterpiece,', negative: '' },
                lora: { loraId: null, strength: 1, triggerId: null, outfitId: null },
            },
        }),
        key: 'favorite:8',
        ...application,
    });

    assert.equal(
        result.positive,
        'masterpiece,\n\n<lora:selected.safetensors:0.7>,\n\nsilver hair,\n\n<lora:dress:0.9>,\n\npark, custom,',
    );
});

test('デフォルトが空の人物LoRAなしお気に入りでは先頭へ挿入する', () => {
    const result = prepareFavoriteCharacterLoraQueueItem({
        favorite: favorite({
            positivePrompt: '<lora:dress:0.9>,\n\npark,',
            selectionSnapshot: {
                defaultSections: { positive: '', negative: '' },
                lora: { loraId: null, strength: 1, triggerId: null, outfitId: null },
            },
        }),
        key: 'favorite:9',
        ...application,
    });

    assert.equal(
        result.positive,
        '<lora:selected.safetensors:0.7>,\n\nsilver hair,\n\n<lora:dress:0.9>,\n\npark,',
    );
});

test('保存済みLoRAまたはトリガーが削除済みなら準備しない', () => {
    assert.throws(
        () =>
            prepareFavoriteCharacterLoraQueueItem({
                favorite: favorite(),
                key: 'favorite:7',
                ...application,
                options: { ...options, loras: [selectedLora] },
            }),
        /保存時の人物LoRA/,
    );
    assert.throws(
        () =>
            prepareFavoriteCharacterLoraQueueItem({
                favorite: favorite(),
                key: 'favorite:7',
                ...application,
                options: { ...options, triggers: [selectedTrigger] },
            }),
        /保存時の人物LoRAトリガー/,
    );
});

test('現在選択と所属トリガーが不整合なら準備しない', () => {
    assert.throws(
        () =>
            prepareFavoriteCharacterLoraQueueItem({
                favorite: favorite(),
                key: 'favorite:7',
                ...application,
                selection: { loraId: 2, strength: 0.7, triggerId: 11 },
            }),
        /現在選択中の人物LoRAトリガー/,
    );
});
