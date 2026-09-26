import {
    createCharacterLoraVariant,
    getCharacterLoraCandidates,
} from './character-lora-variant.js';
import { writePromptToClipboard } from './default-prompts.js';
import { replaceModelFamilyDefault } from './model-family-default-variant.js';

const storageKey = 'comfyui-character-variant-unchecked-v1';
const candidateKey = (lora, trigger) => `lora:${lora.id}:trigger:${trigger?.id ?? 'none'}`;

export const initializeCharacterLoraVariantPage = ({
    page,
    documentObject,
    clipboard,
    getCandidates,
    getSource,
    getFamilyPrompts = () => null,
    notify,
    fetcher = fetch,
    csrfToken,
}) => {
    const view = documentObject.defaultView;
    const dialog = page.querySelector('[data-character-variant-dialog]');
    const openButton = page.querySelector('[data-character-variant-open]');
    const closeButton = page.querySelector('[data-character-variant-close]');
    const search = page.querySelector('[data-character-variant-search]');
    const list = page.querySelector('[data-character-variant-list]');
    const status = page.querySelector('[data-character-variant-status]');
    const count = page.querySelector('[data-character-variant-count]');
    const sendButton = page.querySelector('[data-character-variant-send]');
    if (!view || !(dialog instanceof view.HTMLDialogElement)) {
        return { updateAvailability: () => {} };
    }

    let snapshot = null;
    let unchecked = new Set();
    const candidateResults = new Map();
    try {
        const saved = JSON.parse(view.localStorage.getItem(storageKey) ?? '[]');
        if (Array.isArray(saved)) {
            unchecked = new Set(saved.filter((key) => typeof key === 'string'));
        }
    } catch {
        unchecked = new Set();
    }
    const setStatus = (message) => {
        if (status instanceof view.HTMLElement) {
            status.textContent = message;
        }
    };
    const persist = () => {
        try {
            view.localStorage.setItem(storageKey, JSON.stringify([...unchecked]));
        } catch {
            setStatus('チェック状態をブラウザーへ保存できません。');
        }
    };
    const allCandidates = () => (snapshot ? getCharacterLoraCandidates(snapshot.options, '') : []);
    const selectedCandidates = () =>
        allCandidates().filter(({ lora, trigger }) => !unchecked.has(candidateKey(lora, trigger)));
    const updateCount = () => {
        const total = selectedCandidates().length;
        if (count instanceof view.HTMLElement) {
            count.textContent = `${total}件選択`;
        }
        if (sendButton instanceof view.HTMLButtonElement) {
            sendButton.disabled = total === 0;
        }
    };
    const updateAvailability = () => {
        if (!(openButton instanceof view.HTMLButtonElement)) {
            return;
        }
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
    };
    const createOutput = (polarity, lora, trigger) => {
        let output =
            polarity === 'positive'
                ? createCharacterLoraVariant(snapshot.positive, snapshot.original, lora, trigger)
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
        return output;
    };
    const render = () => {
        if (!(list instanceof view.HTMLElement) || !snapshot) {
            return;
        }
        list.replaceChildren();
        const candidates = getCharacterLoraCandidates(snapshot.options, search?.value ?? '');
        setStatus(candidates.length === 0 ? '該当する人物LoRAがありません。' : '');
        candidates.forEach(({ lora, trigger }) => {
            const row = documentObject.createElement('div');
            row.className = 'character-variant-row';
            const name = trigger ? `${lora.name}・${trigger.name}` : lora.name;
            const key = candidateKey(lora, trigger);
            const checkbox = documentObject.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.checked = !unchecked.has(key);
            checkbox.setAttribute('aria-label', `${name}を一括送信の対象にする`);
            checkbox.addEventListener('change', () => {
                if (checkbox.checked) {
                    unchecked.delete(key);
                } else {
                    unchecked.add(key);
                }
                persist();
                updateCount();
            });
            const positiveButton = documentObject.createElement('button');
            positiveButton.type = 'button';
            positiveButton.className = 'character-variant-item';
            positiveButton.setAttribute('aria-label', `${name}のpositiveをコピー`);
            const strong = documentObject.createElement('strong');
            strong.textContent = lora.name;
            const details = documentObject.createElement('span');
            details.textContent = `${lora.fileName} · ${lora.modelFamilyName ?? 'Illustrious'} · 強度 ${lora.recommendedStrength} · ${trigger ? `トリガー ${trigger.name}` : 'トリガーなし'}`;
            positiveButton.append(strong, details);
            const negativeButton = documentObject.createElement('button');
            negativeButton.type = 'button';
            negativeButton.className = 'small-button';
            negativeButton.textContent = 'negativeをコピー';
            const singleSendButton = documentObject.createElement('button');
            singleSendButton.type = 'button';
            singleSendButton.className = 'small-button';
            singleSendButton.textContent = 'この1件を送信';
            singleSendButton.setAttribute('aria-label', `${name}をComfyUIへ送信`);
            const sendSelectionLabel = documentObject.createElement('label');
            sendSelectionLabel.className = 'character-variant-send-selection';
            const sendSelectionText = documentObject.createElement('span');
            sendSelectionText.textContent = '一括送信';
            sendSelectionLabel.append(checkbox, sendSelectionText);
            const actions = documentObject.createElement('div');
            actions.className = 'character-variant-row-actions';
            actions.append(negativeButton, singleSendButton, sendSelectionLabel);
            const result = documentObject.createElement('p');
            result.className = 'character-variant-result';
            result.setAttribute('role', 'status');
            result.textContent = candidateResults.get(key) ?? '';
            const setCandidateResult = (message) => {
                candidateResults.set(key, message);
                result.textContent = message;
            };
            const copy = async (polarity) => {
                positiveButton.disabled = true;
                negativeButton.disabled = true;
                try {
                    await writePromptToClipboard(
                        clipboard.writeText.bind(clipboard),
                        createOutput(polarity, lora, trigger),
                    );
                    notify(`${name}の${polarity}をコピーしました。`);
                } catch (error) {
                    const known = [
                        '元の人物LoRAタグを特定できません。',
                        '元の系統のデフォルト文面を特定できません。',
                        '系統のデフォルト文面を確認できません。',
                    ];
                    setStatus(
                        error instanceof Error && known.includes(error.message)
                            ? error.message
                            : 'コピーできませんでした。元の出力やクリップボードを確認してください。',
                    );
                } finally {
                    positiveButton.disabled = false;
                    negativeButton.disabled = false;
                }
            };
            positiveButton.addEventListener('click', () => void copy('positive'));
            negativeButton.addEventListener('click', () => void copy('negative'));
            singleSendButton.addEventListener('click', async () => {
                positiveButton.disabled = true;
                negativeButton.disabled = true;
                singleSendButton.disabled = true;
                setStatus(`${name}をComfyUIへ送信しています。`);
                try {
                    const response = await fetcher(page.dataset.comfyUiPromptsUrl, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            modelFamilyId: lora.modelFamilyId ?? 1,
                            positive: createOutput('positive', lora, trigger),
                            negative: createOutput('negative', lora, trigger),
                        }),
                    });
                    const body = await response.json();
                    if (!response.ok || typeof body.promptId !== 'string') {
                        throw new Error(body.message ?? '送信できませんでした。');
                    }
                    const message = `送信済み（ID: ${body.promptId}）`;
                    setCandidateResult(message);
                    setStatus(`${name}をキューへ追加しました（ID: ${body.promptId}）。`);
                    notify(`${name}をComfyUIのキューへ追加しました。`);
                } catch (error) {
                    const message =
                        error instanceof Error ? error.message : '送信できませんでした。';
                    setCandidateResult(`失敗：${message}`);
                    setStatus(message);
                } finally {
                    positiveButton.disabled = false;
                    negativeButton.disabled = false;
                    singleSendButton.disabled = false;
                }
            });
            row.append(positiveButton, actions, result);
            list.append(row);
        });
        updateCount();
    };

    openButton?.addEventListener('click', () => {
        const source = getSource();
        const options = getCandidates();
        if (!source || !options) {
            return;
        }
        snapshot = { ...source, options };
        const validKeys = new Set(
            allCandidates().map(({ lora, trigger }) => candidateKey(lora, trigger)),
        );
        unchecked = new Set([...unchecked].filter((key) => validKeys.has(key)));
        persist();
        if (search instanceof view.HTMLInputElement) {
            search.value = '';
        }
        render();
        dialog.showModal();
        search?.focus();
    });
    closeButton?.addEventListener('click', () => dialog.close());
    search?.addEventListener('input', render);
    page.querySelector('[data-character-variant-select-all]')?.addEventListener('click', () => {
        unchecked.clear();
        persist();
        render();
    });
    page.querySelector('[data-character-variant-clear-all]')?.addEventListener('click', () => {
        unchecked = new Set(
            allCandidates().map(({ lora, trigger }) => candidateKey(lora, trigger)),
        );
        persist();
        render();
    });
    sendButton?.addEventListener('click', async () => {
        const selected = selectedCandidates();
        if (selected.length === 0 || !view.confirm(`${selected.length}件をComfyUIへ送信します。`)) {
            return;
        }
        sendButton.disabled = true;
        setStatus('ComfyUIへ一括送信しています。');
        try {
            const items = [];
            const localFailures = [];
            for (const { lora, trigger } of selected) {
                const key = candidateKey(lora, trigger);
                try {
                    items.push({
                        candidateKey: key,
                        modelFamilyId: lora.modelFamilyId ?? 1,
                        positive: createOutput('positive', lora, trigger),
                        negative: createOutput('negative', lora, trigger),
                    });
                } catch (error) {
                    localFailures.push({
                        candidateKey: key,
                        message:
                            error instanceof Error
                                ? error.message
                                : 'プロンプトを作成できませんでした。',
                    });
                }
            }
            if (items.length === 0) {
                for (const failure of localFailures) {
                    candidateResults.set(failure.candidateKey, `失敗：${failure.message}`);
                }
                render();
                setStatus(`0件をキューへ追加、${localFailures.length}件が失敗しました。`);
                return;
            }
            const response = await fetcher(page.dataset.comfyUiPromptBatchUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ items }),
            });
            const body = await response.json();
            if (!response.ok || !Array.isArray(body.results)) {
                throw new Error('一括送信できませんでした。');
            }
            for (const result of body.results) {
                candidateResults.set(
                    result.candidateKey,
                    result.status === 'accepted'
                        ? `送信済み（ID: ${result.promptId}）`
                        : `失敗：${result.message ?? '送信できませんでした。'}`,
                );
            }
            for (const failure of localFailures) {
                candidateResults.set(failure.candidateKey, `失敗：${failure.message}`);
            }
            render();
            const accepted = body.results.filter((result) => result.status === 'accepted').length;
            const failed = body.results.length - accepted + localFailures.length;
            setStatus(`${accepted}件をキューへ追加、${failed}件が失敗しました。`);
            notify(`${accepted}件をComfyUIのキューへ追加しました。`);
        } catch (error) {
            const message = error instanceof Error ? error.message : '一括送信できませんでした。';
            setStatus(
                `${message} 一部が受理済みの可能性があるため、ComfyUIのキューを確認してください。`,
            );
        } finally {
            updateCount();
        }
    });
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });
    return { updateAvailability };
};
