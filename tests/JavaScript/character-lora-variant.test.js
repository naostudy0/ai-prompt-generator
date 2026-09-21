import assert from 'node:assert/strict';
import test from 'node:test';

import {
    createCharacterLoraSourceMetadata,
    createCharacterLoraVariant,
    getCharacterLoraCandidates,
    locateCharacterLoraSourceMetadata,
    trackCharacterLoraSourceEdit,
} from '../../resources/js/character-lora-variant.js';

const candidate = {
    id: 2,
    name: '別人物',
    fileName: 'other.safetensors',
    recommendedStrength: 0.8,
    tags: Array.from({ length: 11 }, (_, step) => `<lora:other.safetensors:${step / 10}>,`),
};
const original = createCharacterLoraSourceMetadata(
    'masterpiece,',
    '<lora:original.safetensors:1>,',
    'brown hair,',
);
const trigger = { id: 3, loraId: 2, name: '標準', content: 'blue hair,' };

test('手編集と衣装LoRAを保ったまま人物LoRAと一致するトリガーだけを差し替える', () => {
    const positive =
        'masterpiece,\n\n<lora:original.safetensors:1>,\n\nbrown hair,\n\n<lora:dress.safetensors:0.9>,\n\npark, custom tag,';
    const expected =
        'masterpiece,\n\n<lora:other.safetensors:0.8>,\n\nblue hair,\n\n<lora:dress.safetensors:0.9>,\n\npark, custom tag,';

    assert.equal(createCharacterLoraVariant(positive, original, candidate, trigger), expected);
});

test('一部手編集した元トリガーは残して選んだ候補のトリガーを追加する', () => {
    const generated = 'masterpiece,\n\n<lora:original.safetensors:1>,\n\nbrown hair,\n\npark,';
    const positive =
        'masterpiece,\n\n<lora:original.safetensors:1>,\n\nbrown hair, smiling,\n\npark,';
    const expected =
        'masterpiece,\n\n<lora:other.safetensors:0.8>,\n\nblue hair,\n\nbrown hair, smiling,\n\npark,';

    const edited = trackCharacterLoraSourceEdit(original, generated, positive);
    assert.equal(createCharacterLoraVariant(positive, edited, candidate, trigger), expected);
});

test('人物タグと元トリガーの間に追加した文面を残して元トリガーを差し替える', () => {
    const generated = 'masterpiece,\n\n<lora:original.safetensors:1>,\n\nbrown hair,\n\npark,';
    const positive =
        'masterpiece,\n\n<lora:original.safetensors:1>,\n\nsmile,\n\nbrown hair,\n\npark,';
    const expected =
        'masterpiece,\n\n<lora:other.safetensors:0.8>,\n\nsmile,\n\nblue hair,\n\npark,';
    const edited = trackCharacterLoraSourceEdit(original, generated, positive);

    assert.equal(createCharacterLoraVariant(positive, edited, candidate, trigger), expected);
});

test('候補にトリガーがなければ元の人物トリガーを取り除く', () => {
    const positive = 'masterpiece,\n\n<lora:original.safetensors:1>,\n\nbrown hair,\n\npark,';
    const expected = 'masterpiece,\n\n<lora:other.safetensors:0.8>,\n\npark,';

    assert.equal(createCharacterLoraVariant(positive, original, candidate, null), expected);
});

test('元の人物LoRAがないときはデフォルトと衣装LoRAの間に候補を入れる', () => {
    const positive = 'masterpiece,\n\n<lora:dress.safetensors:1>,\n\npark,';
    const expected =
        'masterpiece,\n\n<lora:other.safetensors:0.8>,\n\nblue hair,\n\n<lora:dress.safetensors:1>,\n\npark,';

    assert.equal(
        createCharacterLoraVariant(
            positive,
            createCharacterLoraSourceMetadata('masterpiece,', '', ''),
            candidate,
            trigger,
        ),
        expected,
    );
});

test('元の人物LoRAタグを特定できないときはコピー文面を作らない', () => {
    const positive = 'masterpiece,\n\n<lora:edited.safetensors:1>,\n\npark,';

    assert.throws(
        () => createCharacterLoraVariant(positive, original, candidate, trigger),
        /元の人物LoRAタグを特定できません/,
    );
});

test('保存済み文面を復元した直後に人物LoRAタグを特定して差し替える', () => {
    const saved =
        'custom,\n\nmasterpiece,\n\n<lora:original.safetensors:1>,\n\nbrown hair,\n\n<lora:dress:1>,';
    const metadata = locateCharacterLoraSourceMetadata(saved, original);
    const expected =
        'custom,\n\nmasterpiece,\n\n<lora:other.safetensors:0.8>,\n\nblue hair,\n\n<lora:dress:1>,';

    assert.equal(createCharacterLoraVariant(saved, metadata, candidate, trigger), expected);
});

test('検索に一致するLoRAと全トリガーの組み合わせを候補にする', () => {
    const options = {
        loras: [candidate, { ...candidate, id: 5, name: 'トリガーなし', fileName: 'empty' }],
        triggers: [trigger, { id: 4, loraId: 2, name: '別髪型', content: 'short hair,' }],
    };

    const candidates = getCharacterLoraCandidates(options, 'other');
    assert.deepEqual(
        candidates.map((item) => item.trigger?.id),
        [3, 4],
    );
    assert.equal(getCharacterLoraCandidates(options, 'empty')[0].trigger, null);
    assert.equal(getCharacterLoraCandidates(options, 'missing').length, 0);
});

test('同じLoRAタグが別セクションにあっても生成時の人物セクションだけを差し替える', () => {
    const positive =
        'masterpiece,\n\n<lora:original.safetensors:1>,\n\nbrown hair,\n\n<lora:original.safetensors:1>, park,';
    const expected =
        'masterpiece,\n\n<lora:other.safetensors:0.8>,\n\nblue hair,\n\n<lora:original.safetensors:1>, park,';

    assert.equal(createCharacterLoraVariant(positive, original, candidate, trigger), expected);
});

test('デフォルト内の空行より後に人物LoRAを挿入する', () => {
    const defaultPositive = 'masterpiece,\n\nbest quality,';
    const positive = `${defaultPositive}\n\n<lora:dress:1>,`;
    const metadata = createCharacterLoraSourceMetadata(defaultPositive, '', '');

    assert.equal(
        createCharacterLoraVariant(positive, metadata, candidate, null),
        `${defaultPositive}\n\n<lora:other.safetensors:0.8>,\n\n<lora:dress:1>,`,
    );
});

test('デフォルトを手編集で削除した場合も衣装LoRAの前に人物LoRAを挿入する', () => {
    const generated = 'masterpiece,\n\n<lora:dress:1>,';
    const positive = '<lora:dress:1>,';
    const metadata = trackCharacterLoraSourceEdit(
        createCharacterLoraSourceMetadata('masterpiece,', '', ''),
        generated,
        positive,
    );

    assert.equal(
        createCharacterLoraVariant(positive, metadata, candidate, null),
        '<lora:other.safetensors:0.8>,\n\n<lora:dress:1>,',
    );
});
