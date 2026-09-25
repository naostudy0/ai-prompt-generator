import assert from 'node:assert/strict';
import test from 'node:test';
import { replaceModelFamilyDefault } from '../../resources/js/model-family-default-variant.js';

test('別系統のpositiveとnegativeデフォルトを置換して他の文面を残す', () => {
    const positive = 'ill quality,\n\n<lora:person:1>,\n\nstanding,';
    const negative = 'ill bad,\n\nmanual bad,';

    assert.equal(
        replaceModelFamilyDefault(positive, 'ill quality,', 'anima quality,', true),
        'anima quality,\n\n<lora:person:1>,\n\nstanding,',
    );
    assert.equal(
        replaceModelFamilyDefault(negative, 'ill bad,', 'anima bad,', true),
        'anima bad,\n\nmanual bad,',
    );
});

test('デフォルトが未選択なら挿入せず手編集済みのデフォルトは誤って置換しない', () => {
    assert.equal(replaceModelFamilyDefault('manual,', 'old,', 'new,', false), 'manual,');
    assert.throws(
        () => replaceModelFamilyDefault('edited old,\n\nmanual,', 'old,', 'new,', true),
        /特定できません/,
    );
});

test('デフォルト区間の前に追記した文面を保持する', () => {
    assert.equal(
        replaceModelFamilyDefault('custom,\n\nold,\n\nmanual,', 'old,', 'new,', true),
        'custom,\n\nnew,\n\nmanual,',
    );
});
