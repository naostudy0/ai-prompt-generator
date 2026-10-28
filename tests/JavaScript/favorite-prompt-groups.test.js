import assert from 'node:assert/strict';
import test from 'node:test';

import {
    createFavoritePromptKey,
    findFavoritePromptGroupIndex,
    parseFavoritePromptChecks,
    parseFavoritePromptGroups,
    prepareFavoritePromptGroup,
    removeFavoritePromptGroup,
    replaceFavoritePromptGroup,
    resolveFavoritePromptGroup,
    serializeFavoritePromptChecks,
    serializeFavoritePromptGroups,
} from '../../resources/js/favorite-prompt-groups.js';

test('お気に入りIDを候補キーへ変換する', () => {
    assert.equal(createFavoritePromptKey(12), 'favorite:12');
    assert.throws(() => createFavoritePromptKey(0), /お気に入りID/);
    assert.throws(() => createFavoritePromptKey(1.5), /お気に入りID/);
});

test('有効なグループを順序どおり復元する', () => {
    const groups = parseFavoritePromptGroups(
        JSON.stringify({
            version: 1,
            groups: [
                { name: '屋外', favoriteKeys: ['favorite:12', 'favorite:3'] },
                { name: '室内', favoriteKeys: [] },
            ],
        }),
    );

    assert.deepEqual(groups, [
        { name: '屋外', favoriteKeys: ['favorite:12', 'favorite:3'] },
        { name: '室内', favoriteKeys: [] },
    ]);
});

test('不正なグループ保存値を使用しない', () => {
    for (const value of [
        null,
        '{',
        JSON.stringify({ version: 2, groups: [] }),
        JSON.stringify({ version: 1, groups: [{ name: '', favoriteKeys: [] }] }),
        JSON.stringify({ version: 1, groups: [{ name: 'x'.repeat(101), favoriteKeys: [] }] }),
        JSON.stringify({ version: 1, groups: [{ name: '屋外', favoriteKeys: [12] }] }),
        JSON.stringify({ version: 1, groups: [{ name: '屋外', favoriteKeys: ['favorite:0'] }] }),
        JSON.stringify({ version: 1, groups: [{ name: '屋外', favoriteKeys: ['favorite:01'] }] }),
    ]) {
        assert.deepEqual(parseFavoritePromptGroups(value), []);
    }
});

test('グループ名と候補キーを正規化する', () => {
    assert.deepEqual(
        prepareFavoritePromptGroup('  屋外  ', [
            'favorite:12',
            'favorite:3',
            'favorite:12',
        ]),
        { name: '屋外', favoriteKeys: ['favorite:12', 'favorite:3'] },
    );
    assert.throws(() => prepareFavoritePromptGroup('   ', []), /グループ名/);
    assert.throws(() => prepareFavoritePromptGroup('x'.repeat(101), []), /100文字/);
    assert.throws(() => prepareFavoritePromptGroup('屋外', ['invalid']), /候補キー/);
});

test('同名を大文字小文字を区別せず置換して削除する', () => {
    const groups = [{ name: 'Outdoor', favoriteKeys: ['favorite:1'] }];
    const replacement = { name: 'OUTDOOR', favoriteKeys: ['favorite:2'] };
    const index = findFavoritePromptGroupIndex(groups, ' outdoor ');

    assert.equal(index, 0);
    assert.deepEqual(replaceFavoritePromptGroup(groups, replacement, index), [replacement]);
    assert.deepEqual(replaceFavoritePromptGroup(groups, { name: '室内', favoriteKeys: [] }, -1), [
        ...groups,
        { name: '室内', favoriteKeys: [] },
    ]);
    assert.deepEqual(removeFavoritePromptGroup(groups, 0), []);
});

test('利用可能な候補だけでグループを解決する', () => {
    const result = resolveFavoritePromptGroup(
        ['favorite:12', 'favorite:3', 'favorite:99'],
        new Set(['favorite:3', 'favorite:12']),
    );

    assert.deepEqual([...result.selectedKeys], ['favorite:12', 'favorite:3']);
    assert.equal(result.unavailableCount, 1);
});

test('グループとチェックをバージョン付きで保存する', () => {
    const groups = [{ name: '屋外', favoriteKeys: ['favorite:12'] }];
    assert.equal(
        serializeFavoritePromptGroups(groups),
        '{"version":1,"groups":[{"name":"屋外","favoriteKeys":["favorite:12"]}]}',
    );
    assert.equal(
        serializeFavoritePromptChecks(new Set(['favorite:12', 'favorite:3'])),
        '{"version":1,"favoriteKeys":["favorite:12","favorite:3"]}',
    );
    assert.deepEqual(
        [...parseFavoritePromptChecks('{"version":1,"favoriteKeys":["favorite:12","favorite:3"]}')],
        ['favorite:12', 'favorite:3'],
    );
});

test('不正なチェック保存値を使用しない', () => {
    for (const value of [
        null,
        '{',
        '{"version":2,"favoriteKeys":[]}',
        '{"version":1,"favoriteKeys":[12]}',
        '{"version":1,"favoriteKeys":["favorite:-1"]}',
    ]) {
        assert.deepEqual([...parseFavoritePromptChecks(value)], []);
    }
});
