import assert from 'node:assert/strict';
import test from 'node:test';

import {
    findCharacterLoraVariantGroupIndex,
    parseCharacterLoraVariantGroups,
    prepareCharacterLoraVariantGroup,
    removeCharacterLoraVariantGroup,
    replaceCharacterLoraVariantGroup,
    resolveCharacterLoraVariantGroup,
    serializeCharacterLoraVariantGroups,
} from '../../resources/js/character-lora-variant-groups.js';

test('バージョン1のグループだけを保存形式から復元する', () => {
    const serialized = JSON.stringify({
        version: 1,
        groups: [{ name: '目を描写しない', candidateKeys: ['lora:1:trigger:2'] }],
    });

    assert.deepEqual(parseCharacterLoraVariantGroups(serialized), [
        { name: '目を描写しない', candidateKeys: ['lora:1:trigger:2'] },
    ]);
    assert.equal(
        serializeCharacterLoraVariantGroups([
            { name: '目を描写しない', candidateKeys: ['lora:1:trigger:2'] },
        ]),
        serialized,
    );
});

test('壊れたJSONと未知のバージョンと不正なグループは使用しない', () => {
    const invalidValues = [
        '{',
        JSON.stringify({ version: 2, groups: [] }),
        JSON.stringify({ version: 1, groups: [{ name: 1, candidateKeys: [] }] }),
        JSON.stringify({ version: 1, groups: [{ name: 'group', candidateKeys: [1] }] }),
    ];

    for (const value of invalidValues) {
        assert.deepEqual(parseCharacterLoraVariantGroups(value), []);
    }
});

test('グループ名を整形し候補キーの重複を除いて順序を保つ', () => {
    assert.deepEqual(
        prepareCharacterLoraVariantGroup('  構図用  ', [
            'lora:2:trigger:none',
            'lora:1:trigger:4',
            'lora:2:trigger:none',
        ]),
        {
            name: '構図用',
            candidateKeys: ['lora:2:trigger:none', 'lora:1:trigger:4'],
        },
    );
    assert.equal(prepareCharacterLoraVariantGroup('a'.repeat(100), []).name.length, 100);
    assert.throws(() => prepareCharacterLoraVariantGroup('   ', []), /グループ名/);
    assert.throws(() => prepareCharacterLoraVariantGroup('a'.repeat(101), []), /100文字/);
});

test('前後空白と大文字小文字を無視して同名グループを探す', () => {
    const groups = [{ name: 'Portrait', candidateKeys: [] }];

    assert.equal(findCharacterLoraVariantGroupIndex(groups, ' portrait '), 0);
    assert.equal(findCharacterLoraVariantGroupIndex(groups, 'other'), -1);
});

test('追加と上書きと削除で元のグループ配列を変更しない', () => {
    const original = [{ name: 'A', candidateKeys: ['lora:1:trigger:none'] }];
    const added = replaceCharacterLoraVariantGroup(
        original,
        { name: 'B', candidateKeys: [] },
        -1,
    );
    const replaced = replaceCharacterLoraVariantGroup(
        original,
        { name: 'A new', candidateKeys: ['lora:2:trigger:3'] },
        0,
    );
    const removed = removeCharacterLoraVariantGroup(original, 0);

    assert.deepEqual(original, [{ name: 'A', candidateKeys: ['lora:1:trigger:none'] }]);
    assert.deepEqual(added, [original[0], { name: 'B', candidateKeys: [] }]);
    assert.deepEqual(replaced, [{ name: 'A new', candidateKeys: ['lora:2:trigger:3'] }]);
    assert.deepEqual(removed, []);
});

test('利用できる候補だけを選択して不足件数を返す', () => {
    const result = resolveCharacterLoraVariantGroup(
        ['lora:1:trigger:2', 'lora:9:trigger:none'],
        new Set(['lora:1:trigger:2', 'lora:3:trigger:none']),
    );

    assert.deepEqual([...result.selectedKeys], ['lora:1:trigger:2']);
    assert.equal(result.unavailableCount, 1);
});
