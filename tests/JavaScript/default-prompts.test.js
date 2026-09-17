import assert from 'node:assert/strict';
import test from 'node:test';

import {
    createPromptOutputs,
    joinPromptSections,
    requestDefaultPrompts,
    requestSaveDefaultPrompt,
    writePromptToClipboard,
} from '../../resources/js/default-prompts.js';

test('positiveとnegativeのデフォルト文面を取得する', async () => {
    const url = '/default-prompts';
    const expected = {
        positive: 'masterpiece,',
        negative: 'bad anatomy,',
    };
    let requestedUrl;
    let receivedOptions;
    const fetcher = async (requestUrl, options) => {
        requestedUrl = requestUrl;
        receivedOptions = options;

        return {
            ok: true,
            json: async () => expected,
        };
    };

    const prompts = await requestDefaultPrompts(fetcher, url);

    assert.deepEqual(prompts, expected);
    assert.equal(requestedUrl, url);
    assert.deepEqual(receivedOptions, {
        headers: { Accept: 'application/json' },
    });
});

test('編集したpositive文面とCSRFトークンで保存をリクエストして応答を返す', async () => {
    const url = '/default-prompts/positive';
    const content = 'abc, def,';
    const csrfToken = 'csrf-token';
    const expected = {
        polarity: 'positive',
        content,
        formatSucceeded: true,
    };
    let requestedUrl;
    let receivedOptions;
    const fetcher = async (requestUrl, options) => {
        requestedUrl = requestUrl;
        receivedOptions = options;

        return {
            ok: true,
            json: async () => expected,
        };
    };

    const result = await requestSaveDefaultPrompt(fetcher, url, csrfToken, 'positive', content);

    assert.deepEqual(result, expected);
    assert.equal(requestedUrl, url);
    assert.deepEqual(receivedOptions, {
        method: 'PUT',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({ content }),
    });
});

test('編集したnegative文面の保存をリクエストして応答を返す', async () => {
    const url = '/default-prompts/negative';
    const content = 'bad anatomy, bad hands,';
    const expected = {
        polarity: 'negative',
        content,
        formatSucceeded: true,
    };
    const fetcher = async () => ({
        ok: true,
        json: async () => expected,
    });

    const result = await requestSaveDefaultPrompt(fetcher, url, 'csrf-token', 'negative', content);

    assert.deepEqual(result, expected);
});

test('取得レスポンスのpositiveが文字列でない場合は形式不正として失敗する', async () => {
    const fetcher = async () => ({
        ok: true,
        json: async () => ({ positive: null, negative: '' }),
    });

    await assert.rejects(
        () => requestDefaultPrompts(fetcher, '/default-prompts'),
        new Error('The default prompt response is invalid.'),
    );
});

test('取得レスポンスがnullの場合は形式不正として失敗する', async () => {
    const fetcher = async () => ({
        ok: true,
        json: async () => null,
    });

    await assert.rejects(
        () => requestDefaultPrompts(fetcher, '/default-prompts'),
        new Error('The default prompt response is invalid.'),
    );
});

test('取得レスポンスのnegativeが文字列でない場合は形式不正として失敗する', async () => {
    const fetcher = async () => ({
        ok: true,
        json: async () => ({ positive: '', negative: null }),
    });

    await assert.rejects(
        () => requestDefaultPrompts(fetcher, '/default-prompts'),
        new Error('The default prompt response is invalid.'),
    );
});

test('保存レスポンスのformatSucceededが真偽値でない場合は形式不正として失敗する', async () => {
    const fetcher = async () => ({
        ok: true,
        json: async () => ({
            polarity: 'positive',
            content: 'abc,',
            formatSucceeded: 'true',
        }),
    });

    await assert.rejects(
        () =>
            requestSaveDefaultPrompt(
                fetcher,
                '/default-prompts/positive',
                'token',
                'positive',
                'abc',
            ),
        new Error('The default prompt save response is invalid.'),
    );
});

test('保存レスポンスがnullの場合は形式不正として失敗する', async () => {
    const fetcher = async () => ({
        ok: true,
        json: async () => null,
    });

    await assert.rejects(
        () =>
            requestSaveDefaultPrompt(
                fetcher,
                '/default-prompts/positive',
                'token',
                'positive',
                'abc',
            ),
        new Error('The default prompt save response is invalid.'),
    );
});

test('保存レスポンスの種別が要求した種別と異なる場合は形式不正として失敗する', async () => {
    const fetcher = async () => ({
        ok: true,
        json: async () => ({
            polarity: 'negative',
            content: 'abc,',
            formatSucceeded: true,
        }),
    });

    await assert.rejects(
        () =>
            requestSaveDefaultPrompt(
                fetcher,
                '/default-prompts/positive',
                'token',
                'positive',
                'abc',
            ),
        new Error('The default prompt save response is invalid.'),
    );
});

test('保存レスポンスのcontentが文字列でない場合は形式不正として失敗する', async () => {
    const fetcher = async () => ({
        ok: true,
        json: async () => ({
            polarity: 'positive',
            content: null,
            formatSucceeded: true,
        }),
    });

    await assert.rejects(
        () =>
            requestSaveDefaultPrompt(
                fetcher,
                '/default-prompts/positive',
                'token',
                'positive',
                'abc',
            ),
        new Error('The default prompt save response is invalid.'),
    );
});

test('取得リクエストが失敗した場合は失敗として扱う', async () => {
    const fetcher = async () => ({ ok: false });

    await assert.rejects(
        () => requestDefaultPrompts(fetcher, '/default-prompts'),
        new Error('The default prompt request failed.'),
    );
});

test('保存リクエストが失敗した場合は失敗として扱う', async () => {
    const fetcher = async () => ({ ok: false });

    await assert.rejects(
        () =>
            requestSaveDefaultPrompt(
                fetcher,
                '/default-prompts/positive',
                'token',
                'positive',
                'abc',
            ),
        new Error('The default prompt request failed.'),
    );
});

test('取得中に通信が失敗した場合はその失敗を呼び出し元へ返す', async () => {
    const expectedError = new Error('Network unavailable.');
    const fetcher = async () => {
        throw expectedError;
    };

    await assert.rejects(() => requestDefaultPrompts(fetcher, '/default-prompts'), expectedError);
});

test('保存中に通信が失敗した場合はその失敗を呼び出し元へ返す', async () => {
    const expectedError = new Error('Network unavailable.');
    const fetcher = async () => {
        throw expectedError;
    };

    await assert.rejects(
        () =>
            requestSaveDefaultPrompt(
                fetcher,
                '/default-prompts/positive',
                'token',
                'positive',
                'abc',
            ),
        expectedError,
    );
});

test('選択中のpositiveとnegativeからそれぞれ出力を作る', () => {
    const prompts = {
        positive: 'masterpiece, best quality,',
        negative: 'bad anatomy, bad hands,',
    };
    const selected = { positive: true, negative: true };
    const expected = {
        positive: 'masterpiece, best quality,',
        negative: 'bad anatomy, bad hands,',
    };

    const outputs = createPromptOutputs(prompts, selected);

    assert.deepEqual(outputs, expected);
});

test('positiveの選択を解除した場合はpositiveだけを空文字列にする', () => {
    const prompts = {
        positive: 'masterpiece, best quality,',
        negative: 'bad anatomy, bad hands,',
    };
    const selected = { positive: false, negative: true };
    const expected = {
        positive: '',
        negative: 'bad anatomy, bad hands,',
    };

    const outputs = createPromptOutputs(prompts, selected);

    assert.deepEqual(outputs, expected);
});

test('negativeの選択を解除した場合はnegativeだけを空文字列にする', () => {
    const prompts = {
        positive: 'masterpiece, best quality,',
        negative: 'bad anatomy, bad hands,',
    };
    const selected = { positive: true, negative: false };
    const expected = {
        positive: 'masterpiece, best quality,',
        negative: '',
    };

    const outputs = createPromptOutputs(prompts, selected);

    assert.deepEqual(outputs, expected);
});

test('両方の選択を解除した場合は両方を空文字列にする', () => {
    const prompts = {
        positive: 'masterpiece, best quality,',
        negative: 'bad anatomy, bad hands,',
    };
    const selected = { positive: false, negative: false };
    const expected = { positive: '', negative: '' };

    const outputs = createPromptOutputs(prompts, selected);

    assert.deepEqual(outputs, expected);
});

test('空の文面は選択中でも空文字列として出力する', () => {
    const prompts = { positive: '', negative: '' };
    const selected = { positive: true, negative: true };
    const expected = { positive: '', negative: '' };

    const outputs = createPromptOutputs(prompts, selected);

    assert.deepEqual(outputs, expected);
});

test('片側の文面が空でももう片側の文面を保持する', () => {
    const prompts = {
        positive: '',
        negative: 'bad anatomy, bad hands,',
    };
    const selected = { positive: true, negative: true };
    const expected = {
        positive: '',
        negative: 'bad anatomy, bad hands,',
    };

    const outputs = createPromptOutputs(prompts, selected);

    assert.deepEqual(outputs, expected);
});

test('複数の空でないセクションを空行一つで区切る', () => {
    const sections = ['abc, def,', '', 'ghi, jkl,'];
    const expected = 'abc, def,\n\nghi, jkl,';

    const output = joinPromptSections(sections);

    assert.equal(output, expected);
});

test('コピー時点の出力文面をクリップボードへ渡す', async () => {
    const content = 'masterpiece, best quality,\n\nhighres,';
    let copiedContent;
    const writeText = async (value) => {
        copiedContent = value;
    };

    await writePromptToClipboard(writeText, content);

    assert.equal(copiedContent, content);
});

test('クリップボードへの書き込みが失敗した場合は失敗として扱う', async () => {
    const expectedError = new Error('Clipboard unavailable.');
    const writeText = async () => {
        throw expectedError;
    };

    await assert.rejects(() => writePromptToClipboard(writeText, 'abc,'), expectedError);
});
