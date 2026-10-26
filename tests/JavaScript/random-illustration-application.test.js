import assert from 'node:assert/strict';
import test from 'node:test';
import { createRandomIllustrationApplication } from '../../resources/js/random-illustration-application.js';

test('解析済み項目を復元してpositiveとnegativeを一度に置換する', () => {
    let restored = null;
    let applied = null;
    const apply = createRandomIllustrationApplication({
        hasOptionIds: (ids) => ids.every((id) => [1, 2].includes(id)),
        restoreOptions: (ids) => {
            restored = ids;
        },
        buildOutputPair: () => ({ positive: 'base,', negative: 'bad anatomy,' }),
        applyOutputPair: (outputs) => {
            applied = outputs;
        },
    });

    assert.equal(
        apply({
            optionIds: [1, 2],
            suggestions: [
                { polarity: 'positive', tags: 'sunlight,' },
                { polarity: 'negative', tags: 'text,' },
            ],
        }),
        true,
    );
    assert.deepEqual(restored, [1, 2]);
    assert.deepEqual(applied, {
        positive: 'base,\n\nsunlight,',
        negative: 'bad anatomy,\n\ntext,',
    });
});

test('未知の項目IDなら選択も出力も変更しない', () => {
    let changed = false;
    const apply = createRandomIllustrationApplication({
        hasOptionIds: () => false,
        restoreOptions: () => {
            changed = true;
        },
        buildOutputPair: () => ({ positive: '', negative: '' }),
        applyOutputPair: () => {
            changed = true;
        },
    });

    assert.equal(apply({ optionIds: [999], suggestions: [] }), false);
    assert.equal(changed, false);
});
