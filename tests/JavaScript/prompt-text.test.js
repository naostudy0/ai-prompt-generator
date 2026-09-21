import assert from 'node:assert/strict';
import test from 'node:test';

import { normalizePromptForComparison } from '../../resources/js/prompt-text.js';

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
