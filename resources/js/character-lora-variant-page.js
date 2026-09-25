import {
    createCharacterLoraVariant,
    getCharacterLoraCandidates,
} from './character-lora-variant.js';
import { writePromptToClipboard } from './default-prompts.js';
import { replaceModelFamilyDefault } from './model-family-default-variant.js';

export const initializeCharacterLoraVariantPage = ({
    page,
    documentObject,
    clipboard,
    getCandidates,
    getSource,
    getFamilyPrompts = () => null,
    notify,
}) => {
    const view = documentObject.defaultView;
    const dialog = page.querySelector('[data-character-variant-dialog]');
    const openButton = page.querySelector('[data-character-variant-open]');
    const closeButton = page.querySelector('[data-character-variant-close]');
    const search = page.querySelector('[data-character-variant-search]');
    const list = page.querySelector('[data-character-variant-list]');
    const status = page.querySelector('[data-character-variant-status]');
    if (!view || !(dialog instanceof view.HTMLDialogElement)) {
        return { updateAvailability: () => {} };
    }

    let snapshot = null;
    const updateAvailability = () => {
        if (openButton instanceof view.HTMLButtonElement) {
            const source = getSource();
            const options = getCandidates();
            openButton.disabled = !source || !options || options.loras.length === 0;
            openButton.title = !source
                ? '先にpositiveを出力してください。'
                : !options
                  ? '人物LoRAの読み込み後に利用できます。'
                  : options.loras.length === 0
                    ? '登録済みの人物LoRAがありません。'
                    : '';
        }
    };

    const setStatus = (message) => {
        if (status instanceof view.HTMLElement) {
            status.textContent = message;
        }
    };

    const render = () => {
        if (!(list instanceof view.HTMLElement) || !snapshot) {
            return;
        }
        list.replaceChildren();
        const candidates = getCharacterLoraCandidates(snapshot.options, search?.value ?? '');
        if (candidates.length === 0) {
            setStatus('該当する人物LoRAがありません。');
            return;
        }
        setStatus('');
        candidates.forEach(({ lora, trigger }) => {
            const row = documentObject.createElement('div');
            row.className = 'character-variant-row';
            const button = documentObject.createElement('button');
            button.type = 'button';
            button.className = 'character-variant-item';
            const candidateName = trigger ? `${lora.name}・${trigger.name}` : lora.name;
            button.setAttribute('aria-label', `${candidateName}のpositiveをコピー`);
            const name = documentObject.createElement('strong');
            name.textContent = lora.name;
            const details = documentObject.createElement('span');
            details.textContent = `${lora.fileName} · ${lora.modelFamilyName ?? 'Illustrious'} · 強度 ${lora.recommendedStrength} · ${trigger ? `トリガー ${trigger.name}` : 'トリガーなし'}`;
            button.append(name, details);
            const negativeButton = documentObject.createElement('button');
            negativeButton.type = 'button';
            negativeButton.className = 'small-button';
            negativeButton.textContent = 'negativeをコピー';
            negativeButton.setAttribute('aria-label', `${candidateName}のnegativeをコピー`);
            const copy = async (polarity) => {
                button.disabled = true;
                negativeButton.disabled = true;
                try {
                    let output =
                        polarity === 'positive'
                            ? createCharacterLoraVariant(
                                  snapshot.positive,
                                  snapshot.original,
                                  lora,
                                  trigger,
                              )
                            : (snapshot.negative ?? '');
                    const sourceFamilyId = snapshot.family?.modelFamilyId ?? 1;
                    const candidateFamilyId = lora.modelFamilyId ?? 1;
                    if (sourceFamilyId !== candidateFamilyId) {
                        const destination = getFamilyPrompts(candidateFamilyId);
                        const defaults = snapshot.family?.defaultSections;
                        if (!destination || !defaults) {
                            throw new Error('系統のデフォルト文面を確認できません。');
                        }
                        output = replaceModelFamilyDefault(
                            output,
                            defaults[polarity],
                            destination[polarity],
                            snapshot.family.defaults[polarity],
                        );
                    }
                    await writePromptToClipboard(clipboard.writeText.bind(clipboard), output);
                    setStatus('');
                    notify(`${candidateName}の${polarity}をコピーしました。`);
                } catch (error) {
                    setStatus(
                        error instanceof Error &&
                            (error.message === '元の人物LoRAタグを特定できません。' ||
                                error.message === '元の系統のデフォルト文面を特定できません。' ||
                                error.message === '系統のデフォルト文面を確認できません。')
                            ? error.message
                            : 'コピーできませんでした。元の出力やクリップボードを確認してください。',
                    );
                } finally {
                    button.disabled = false;
                    negativeButton.disabled = false;
                }
            };
            button.addEventListener('click', () => void copy('positive'));
            negativeButton.addEventListener('click', () => void copy('negative'));
            row.append(button, negativeButton);
            list.append(row);
        });
    };

    openButton?.addEventListener('click', () => {
        const source = getSource();
        const options = getCandidates();
        if (!source || !options) {
            return;
        }
        snapshot = { ...source, options };
        if (search instanceof view.HTMLInputElement) {
            search.value = '';
        }
        render();
        dialog.showModal();
        search?.focus();
    });
    closeButton?.addEventListener('click', () => dialog.close());
    search?.addEventListener('input', render);
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });
    return { updateAvailability };
};
