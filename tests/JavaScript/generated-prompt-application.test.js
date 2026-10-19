import assert from 'node:assert/strict';
import test from 'node:test';
import { createGeneratedPromptApplication } from '../../resources/js/generated-prompt-application.js';

test('デフォルトと人物LoRAを合成して出力元情報とともに反映する', () => {
    let applied = null;
    const apply = createGeneratedPromptApplication({
        getDefaultPrompts: () => ({ positive: 'default positive', negative: 'default negative' }),
        getSelectedCharacterLora: () => ({ lora: '<lora:hero:1>,', trigger: 'hero,', loraId: 8 }),
        getActiveFamilySource: () => ({
            modelFamilyId: 3,
            defaults: { positive: true, negative: true },
        }),
        applyOutputPair: (value) => {
            applied = value;
        },
    });

    assert.equal(
        apply({ positivePrompt: 'scene,', negativePrompt: 'bad,', includeDefaultPrompts: true }),
        true,
    );
    assert.equal(applied.positive, 'default positive\n\nscene,\n\n<lora:hero:1>,\n\nhero,');
    assert.equal(applied.negative, 'default negative\n\nbad,');
    assert.equal(applied.variantFamily.loraId, 8);
});

test('空のpositiveは出力へ反映しない', () => {
    let called = false;
    const apply = createGeneratedPromptApplication({
        getDefaultPrompts: () => ({ positive: '', negative: '' }),
        getSelectedCharacterLora: () => ({ lora: '', trigger: '', loraId: null }),
        getActiveFamilySource: () => ({
            modelFamilyId: 1,
            defaults: { positive: false, negative: false },
        }),
        applyOutputPair: () => {
            called = true;
        },
    });
    assert.equal(
        apply({ positivePrompt: '   ', negativePrompt: '', includeDefaultPrompts: false }),
        false,
    );
    assert.equal(called, false);
});
