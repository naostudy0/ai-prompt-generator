import assert from 'node:assert/strict';
import test from 'node:test';

import { adjustPromptWeight } from '../../resources/js/prompt-weight-adjustment.js';

test('通常のタグを0点05ずつ強調または弱調する', () => {
    assert.equal(adjustPromptWeight('eyes closed', 'increase'), '(eyes closed:1.05)');
    assert.equal(adjustPromptWeight('eyes closed', 'decrease'), '(eyes closed:0.95)');
});

test('既存の強度を変更して1になったときは括弧を外す', () => {
    assert.equal(adjustPromptWeight('(eyes closed:1.05)', 'decrease'), 'eyes closed');
    assert.equal(adjustPromptWeight('(eyes closed:0.95)', 'increase'), 'eyes closed');
    assert.equal(adjustPromptWeight('(eyes closed:1.05)', 'increase'), '(eyes closed:1.1)');
});

test('繰り返し変更しても浮動小数点由来の桁を出さない', () => {
    const first = adjustPromptWeight('(eyes closed:1.05)', 'increase');
    const second = adjustPromptWeight(first, 'increase');

    assert.equal(second, '(eyes closed:1.15)');
});

test('不正な強度構文は通常の文字列として囲む', () => {
    assert.equal(adjustPromptWeight('(eyes closed:high)', 'increase'), '((eyes closed:high):1.05)');
});

test('空の選択範囲と空の本文と不正な方向は変更しない', () => {
    assert.equal(adjustPromptWeight('', 'increase'), null);
    assert.equal(adjustPromptWeight('   ', 'decrease'), null);
    assert.equal(adjustPromptWeight('(:1.05)', 'increase'), null);
    assert.equal(adjustPromptWeight('(eyes closed:Infinity)', 'increase'), null);
    assert.equal(adjustPromptWeight('(eyes closed:NaN)', 'decrease'), null);
    assert.equal(adjustPromptWeight('eyes closed', 'unchanged'), null);
});
