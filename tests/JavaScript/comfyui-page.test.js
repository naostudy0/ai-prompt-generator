import assert from 'node:assert/strict';
import { setImmediate } from 'node:timers/promises';
import test from 'node:test';

import { JSDOM } from 'jsdom';

import {
    initializeComfyUiPage,
    initializeComfyUiWorkflowPage,
} from '../../resources/js/comfyui-page.js';

test('空のpositiveとnegativeを確認なしで現在の系統へ送信する', async () => {
    const dom = new JSDOM(`<main data-comfy-ui-prompts-url="/comfyui/prompts">
        <article data-output="positive"><textarea data-output-content></textarea></article>
        <article data-output="negative"><textarea data-output-content></textarea></article>
        <button data-comfyui-send>送信</button><p data-comfyui-send-status></p>
    </main>`);
    const page = dom.window.document.querySelector('main');
    const requests = [];
    initializeComfyUiPage({
        page,
        documentObject: dom.window.document,
        fetcher: async (url, options) => {
            requests.push({ url, options });
            return { ok: true, json: async () => ({ promptId: 'prompt-1', queueNumber: 1 }) };
        },
        csrfToken: 'csrf',
        getModelFamilyId: () => 2,
        notify: () => {},
    });

    page.querySelector('[data-comfyui-send]').click();
    await setImmediate();

    assert.equal(requests.length, 1);
    assert.deepEqual(JSON.parse(requests[0].options.body), {
        modelFamilyId: 2,
        positive: '',
        negative: '',
    });
    assert.match(page.querySelector('[data-comfyui-send-status]').textContent, /prompt-1/);
});

test('送信中は二重送信を防ぐ', async () => {
    const dom = new JSDOM(`<main data-comfy-ui-prompts-url="/comfyui/prompts">
        <article data-output="positive"><textarea data-output-content>positive</textarea></article>
        <article data-output="negative"><textarea data-output-content></textarea></article>
        <button data-comfyui-send>送信</button><p data-comfyui-send-status></p>
    </main>`);
    const page = dom.window.document.querySelector('main');
    let calls = 0;
    initializeComfyUiPage({
        page,
        documentObject: dom.window.document,
        fetcher: async () => {
            calls += 1;
            return new Promise(() => {});
        },
        csrfToken: 'csrf',
        getModelFamilyId: () => 1,
        notify: () => {},
    });
    const button = page.querySelector('[data-comfyui-send]');
    button.click();
    button.click();
    await setImmediate();

    assert.equal(calls, 1);
    assert.equal(button.disabled, true);
});

test('ワークフロー選択時に開発用JSON固有の入力位置を設定しない', async () => {
    const dom = new JSDOM(`<main data-model-families-url="/model-families">
        <select data-family-list><option value="1">系統</option></select>
        <input type="file" data-comfyui-workflow-file>
        <input data-comfyui-positive-node><input data-comfyui-positive-input>
        <input data-comfyui-negative-node><input data-comfyui-negative-input>
        <input data-comfyui-seed-node><input data-comfyui-seed-input>
        <p data-comfyui-workflow-status></p>
        <button data-comfyui-workflow-save></button>
        <button data-comfyui-workflow-delete></button>
    </main>`);
    const page = dom.window.document.querySelector('main');
    const fileInput = page.querySelector('[data-comfyui-workflow-file]');
    Object.defineProperty(fileInput, 'files', {
        value: [
            {
                text: async () =>
                    JSON.stringify({
                        37: { inputs: { prompt: '' } },
                        118: { inputs: { string: '' } },
                        39: { inputs: { value: 1 } },
                    }),
            },
        ],
    });

    initializeComfyUiWorkflowPage({
        page,
        documentObject: dom.window.document,
        fetcher: async () => ({ ok: true, json: async () => ({ configured: false }) }),
        csrfToken: 'csrf',
    });
    fileInput.dispatchEvent(new dom.window.Event('change'));
    await setImmediate();

    assert.equal(page.querySelector('[data-comfyui-positive-node]').value, '');
    assert.equal(page.querySelector('[data-comfyui-negative-node]').value, '');
    assert.equal(page.querySelector('[data-comfyui-seed-node]').value, '');
    assert.match(
        page.querySelector('[data-comfyui-workflow-status]').textContent,
        /入力位置を指定/,
    );
});
