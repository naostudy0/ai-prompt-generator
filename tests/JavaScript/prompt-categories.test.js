import assert from 'node:assert/strict';
import test from 'node:test';
import {
    createOptionSections,
    filterOptionsKeepingSelection,
    moveOption,
    requestPromptOptions,
    saveOptionGroup,
} from '../../resources/js/prompt-categories.js';

const groupsResponse = {
    groups: [
        {
            id: 1,
            name: '表情',
            selectionMode: 'multiple',
            position: 1,
            options: [
                { id: 10, position: 1, name: '笑顔', content: 'smile, open mouth,' },
                { id: 11, position: 2, name: '微笑み', content: 'smile,' },
            ],
        },
    ],
};

test('名前と選択方式と順序を持つオプションブロックを取得する', async () => {
    const fetcher = async () => ({ ok: true, json: async () => groupsResponse });
    assert.deepEqual(await requestPromptOptions(fetcher, '/prompt-options'), groupsResponse.groups);
});

test('選択した項目をブロック単位で重複を除いて出力する', () => {
    assert.deepEqual(createOptionSections(groupsResponse.groups, new Set([10, 11])), [
        'smile, open mouth,',
    ]);
});

test('検索に一致しない選択中の項目は元の位置へ残す', () => {
    assert.deepEqual(
        filterOptionsKeepingSelection(
            groupsResponse.groups[0].options,
            '微笑み',
            new Set([10]),
        ).map((option) => option.id),
        [10, 11],
    );
});

test('ブロック保存と項目移動を契約どおり送信する', async () => {
    const requests = [];
    const fetcher = async (url, options) => {
        requests.push([url, options.method, JSON.parse(options.body)]);
        return { ok: true, status: options.method === 'PATCH' ? 204 : 201, json: async () => ({}) };
    };
    await saveOptionGroup(fetcher, '/groups', 'csrf', null, {
        name: '画質',
        selectionMode: 'single',
    });
    await moveOption(fetcher, '/options', 'csrf', 10, 2, 20);
    assert.deepEqual(requests, [
        ['/groups', 'POST', { name: '画質', selectionMode: 'single' }],
        ['/options/10/position', 'PATCH', { targetGroupId: 2, beforeOptionId: 20 }],
    ]);
});
