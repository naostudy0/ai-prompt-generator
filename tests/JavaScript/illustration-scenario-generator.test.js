import assert from 'node:assert/strict';
import test from 'node:test';
import { JSDOM } from 'jsdom';
import { initializeIllustrationScenarioGenerator, moveScenarioScene } from '../../resources/js/illustration-scenario-generator.js';

const setup = () => {
    const dom = new JSDOM(`<!doctype html><main data-illustration-scenario-plan-url="/plan" data-illustration-scenario-prompts-url="/prompts" data-illustration-scenario-scene-prompt-url="/one">
    <button data-scenario-open>open</button><dialog data-scenario-dialog><button data-scenario-close>close</button>
    <section data-scenario-input><textarea data-scenario-description></textarea><input data-scenario-count value="5"><select data-scenario-style><option value="danbooru_tags">tags</option></select><button data-scenario-plan>plan</button></section>
    <p data-scenario-status></p><section data-scenario-edit hidden><textarea data-scenario-continuity></textarea><div data-scenario-scenes></div><button data-scenario-add>add</button><button data-scenario-generate>generate</button></section>
    <section data-scenario-results hidden><div data-scenario-result-list></div></section></dialog></main>`);
    const dialog = dom.window.document.querySelector('dialog'); dialog.showModal = () => { dialog.open = true; }; dialog.close = () => { dialog.open = false; };
    return { dom, page: dom.window.document.querySelector('main') };
};

test('初期値5で案を作り編集後の全場面を生成して一件を反映する', async () => {
    const { dom, page } = setup(); const requests = []; let applied = null;
    const fetcher = async (url, options) => {
        requests.push([url, JSON.parse(options.body)]);
        if (url === '/plan') return { ok: true, json: async () => ({ continuityNotes: 'same', scenes: [1,2,3].map((n) => ({ id: `s${n}`, title: `t${n}`, description: `d${n}`, changesFromPrevious: '' })), model: 'm', generationTimeMs: 1 }) };
        return { ok: true, json: async () => ({ scenes: requests[1][1].scenes.map((s) => ({ id: s.id, positivePrompt: `p-${s.id}`, negativePrompt: '' })), model: 'm', promptStyle: 'danbooru_tags', generationTimeMs: 2 }) };
    };
    initializeIllustrationScenarioGenerator({ page, documentObject: dom.window.document, fetcher, csrfToken: 'token', confirmAction: () => true, createId: () => 'new', applyPrompt: (value) => { applied = value; } });
    page.querySelector('[data-scenario-open]').click();
    assert.equal(page.querySelector('[data-scenario-count]').value, '5');
    page.querySelector('[data-scenario-description]').value = '登山'; page.querySelector('[data-scenario-plan]').click(); await new Promise(setImmediate);
    assert.equal(page.querySelectorAll('[data-scenario-scene]').length, 3);
    page.querySelector('[data-scenario-generate]').click(); await new Promise(setImmediate);
    assert.equal(page.querySelectorAll('[data-scenario-result]').length, 3);
    page.querySelector('[data-scenario-result] [data-scenario-apply]').click();
    assert.equal(applied.positivePrompt, 'p-s1');
    assert.equal(applied.includeDefaultPrompts, true);
});

test('場面移動は元配列を変えずIDを維持する', () => {
    const source = [{ id: 'a' }, { id: 'b' }, { id: 'c' }];
    assert.deepEqual(moveScenarioScene(source, 0, 2).map((s) => s.id), ['b', 'c', 'a']);
    assert.deepEqual(source.map((s) => s.id), ['a', 'b', 'c']);
});
