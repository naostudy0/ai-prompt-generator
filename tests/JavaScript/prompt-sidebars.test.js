import assert from 'node:assert/strict';
import test from 'node:test';
import { JSDOM } from 'jsdom';
import { initializePromptSidebars } from '../../resources/js/prompt-sidebars.js';

test('ナビゲーションと選択中の項目をスナップショットから表示する', () => {
    const dom = new JSDOM(`<!doctype html><main>
        <nav><div data-section-navigation-list></div></nav>
        <section data-target tabindex="-1"></section>
        <aside><div data-selection-summary-list></div></aside>
    </main>`);
    const { document } = dom.window;
    const target = document.querySelector('[data-target]');
    let scrollOptions;
    let removed = false;
    target.scrollIntoView = (options) => {
        scrollOptions = options;
    };
    const sidebars = initializePromptSidebars({
        page: document.querySelector('main'),
        documentObject: document,
    });

    sidebars.update('optionGroups', {
        navigationItems: [{ key: 'option-group-1', label: '表情', target: '[data-target]' }],
        selectionGroups: [
            {
                label: '表情',
                key: 'option-group-1',
                items: [
                    {
                        label: '笑顔',
                        meta: '',
                        details: [],
                        onRemove: () => {
                            removed = true;
                        },
                    },
                ],
            },
            { key: 'option-group-2', label: '視線', items: [] },
        ],
    });

    document.querySelector('.section-navigation__link').click();
    assert.deepEqual(scrollOptions, { behavior: 'auto', block: 'start' });
    assert.equal(document.activeElement, target);
    assert.equal(document.querySelector('.selection-summary__group h3').textContent, '表情');
    assert.equal(document.querySelector('.selection-summary__item-label').textContent, '笑顔');
    scrollOptions = undefined;
    document.querySelector('.selection-summary__section-link').click();
    assert.deepEqual(scrollOptions, { behavior: 'auto', block: 'start' });
    document.querySelector('.selection-summary__remove').click();
    assert.equal(removed, true);
    assert.equal(
        document.querySelector('.selection-summary__remove').getAttribute('aria-label'),
        '笑顔の選択を解除',
    );
    assert.equal(
        document.querySelector('[data-selection-summary-list]').textContent.includes('視線'),
        false,
    );
});

test('更新後の選択解除と表示順を反映する', () => {
    const dom = new JSDOM(`<!doctype html><main>
        <nav><div data-section-navigation-list></div></nav>
        <aside><div data-selection-summary-list></div></aside>
    </main>`);
    const { document } = dom.window;
    const sidebars = initializePromptSidebars({
        page: document.querySelector('main'),
        documentObject: document,
    });
    sidebars.update('optionGroups', {
        navigationItems: [{ key: 'option-group-1', label: '表情', target: '[data-expression]' }],
        selectionGroups: [
            {
                label: '表情',
                key: 'option-group-1',
                items: [{ label: '笑顔', meta: '', details: [] }],
            },
        ],
    });
    sidebars.update('default', {
        navigationItems: [{ key: 'default', label: 'デフォルト', target: '[data-default]' }],
        selectionGroups: [
            {
                label: 'デフォルト',
                key: 'default',
                items: [{ label: 'positive デフォルト', meta: '', details: [] }],
            },
        ],
    });
    sidebars.update('optionGroups', {
        navigationItems: [{ key: 'option-group-1', label: '表情', target: '[data-expression]' }],
        selectionGroups: [{ key: 'option-group-1', label: '表情', items: [] }],
    });

    assert.deepEqual(
        [...document.querySelectorAll('.section-navigation__link')].map((item) => item.textContent),
        ['デフォルト', '表情'],
    );
    assert.deepEqual(
        [...document.querySelectorAll('.selection-summary__group h3')].map(
            (item) => item.textContent,
        ),
        ['デフォルト'],
    );
});

test('同名セクションをキーで区別して対象へ即時移動する', () => {
    const dom = new JSDOM(`<!doctype html><main>
        <nav><div data-section-navigation-list></div></nav>
        <section data-group="1" tabindex="-1"></section>
        <section data-group="2" tabindex="-1"></section>
        <aside><div data-selection-summary-list></div></aside>
    </main>`);
    const { document } = dom.window;
    let secondScrollOptions;
    document.querySelector('[data-group="2"]').scrollIntoView = (options) => {
        secondScrollOptions = options;
    };
    const sidebars = initializePromptSidebars({
        page: document.querySelector('main'),
        documentObject: document,
    });
    sidebars.update('optionGroups', {
        navigationItems: [
            { key: 'option-group-1', label: '同名', target: '[data-group="1"]' },
            { key: 'option-group-2', label: '同名', target: '[data-group="2"]' },
            { key: 'option-group-3', label: '削除済み', target: '[data-group="3"]' },
        ],
        selectionGroups: [],
    });

    const links = document.querySelectorAll('.section-navigation__link');
    links[1].click();
    links[2].click();

    assert.equal(links[0].dataset.sectionKey, 'option-group-1');
    assert.equal(links[1].dataset.sectionKey, 'option-group-2');
    assert.deepEqual(secondScrollOptions, { behavior: 'auto', block: 'start' });
    assert.equal(document.activeElement, document.querySelector('[data-group="2"]'));
});

test('左メニューのオプションブロックをドラッグして保存用の順序を渡す', () => {
    const dom = new JSDOM(`<!doctype html><main>
        <nav><div data-section-navigation-list></div></nav>
        <aside><div data-selection-summary-list></div></aside>
    </main>`);
    const { document, Event } = dom.window;
    const moves = [];
    const sidebars = initializePromptSidebars({
        page: document.querySelector('main'),
        documentObject: document,
        onReorderOptionGroup: (id, beforeId) => moves.push([id, beforeId]),
    });
    sidebars.update('optionGroups', {
        navigationItems: [
            { key: 'option-group-1', label: '表情', target: '[data-first]' },
            { key: 'option-group-2', label: '視線', target: '[data-second]' },
            { key: 'option-group-3', label: '場所', target: '[data-third]' },
        ],
        selectionGroups: [],
    });
    const handles = document.querySelectorAll('.section-navigation__drag');
    const rows = document.querySelectorAll('.section-navigation__row');
    rows[1].getBoundingClientRect = () => ({ top: 0, height: 40 });

    handles[2].dispatchEvent(new Event('dragstart', { bubbles: true }));
    rows[1].dispatchEvent(new Event('dragover', { bubbles: true, cancelable: true }));
    const drop = new Event('drop', { bubbles: true, cancelable: true });
    Object.defineProperty(drop, 'clientY', { value: 10 });
    rows[1].dispatchEvent(drop);

    assert.deepEqual(moves, [[3, 2]]);
    assert.equal(drop.defaultPrevented, true);
});
