import assert from 'node:assert/strict';
import test from 'node:test';

import {
    createLinkedNegativePrompt,
    normalizePromptForComparison,
} from '../../resources/js/prompt-text.js';

test('重複除去と区切りの整形による差を同じプロンプトとして比較する', () => {
    const generated = 'masterpiece, park, park,';
    const saved = 'masterpiece,\n\npark,';

    assert.equal(normalizePromptForComparison(generated), normalizePromptForComparison(saved));
});

test('異なるタグを含むプロンプトは一致させない', () => {
    const generated = 'masterpiece, park,';
    const saved = 'masterpiece, beach,';

    assert.notEqual(normalizePromptForComparison(generated), normalizePromptForComparison(saved));
});

test('丸括弧内のカンマを分割せずに比較する', () => {
    const generated = '(smile, open mouth:1.2), park,';
    const saved = '(smile, open mouth:1.2),\n\npark,';

    assert.equal(normalizePromptForComparison(generated), normalizePromptForComparison(saved));
});

test('連動negativeを由来ごとの順序と境界を保って結合する', () => {
    assert.equal(
        createLinkedNegativePrompt('default,', [
            ['pants,', 'shirt,'],
            ['casual clothes,'],
            ['bad hands,', 'bad hands,', '(extra, fingers:1.2),'],
        ]),
        'default,\n\npants,\n\nshirt,\n\ncasual clothes,\n\nbad hands, (extra, fingers:1.2),',
    );
});

test('共通negativeが空でも選択項目のnegativeを出力する', () => {
    assert.equal(createLinkedNegativePrompt('', [[], ['casual clothes,'], []]), 'casual clothes,');
});
