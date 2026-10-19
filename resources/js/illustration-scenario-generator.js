export const moveScenarioScene = (scenes, sourceIndex, targetIndex) => {
    if (sourceIndex < 0 || targetIndex < 0 || sourceIndex >= scenes.length || targetIndex >= scenes.length) return [...scenes];
    const result = [...scenes];
    const [scene] = result.splice(sourceIndex, 1);
    result.splice(targetIndex, 0, scene);
    return result;
};

const json = async (response) => { try { return await response.json(); } catch { return null; } };

export const initializeIllustrationScenarioGenerator = ({ page, documentObject, fetcher, csrfToken, confirmAction, createId, applyPrompt }) => {
    const q = (selector) => page.querySelector(selector);
    const dialog = q('[data-scenario-dialog]'); const open = q('[data-scenario-open]'); const close = q('[data-scenario-close]');
    const description = q('[data-scenario-description]'); const count = q('[data-scenario-count]'); const style = q('[data-scenario-style]');
    const planButton = q('[data-scenario-plan]'); const status = q('[data-scenario-status]'); const edit = q('[data-scenario-edit]');
    const continuity = q('[data-scenario-continuity]'); const list = q('[data-scenario-scenes]'); const add = q('[data-scenario-add]');
    const generate = q('[data-scenario-generate]'); const results = q('[data-scenario-results]'); const resultList = q('[data-scenario-result-list]');
    if ([dialog, open, close, description, count, style, planButton, status, edit, continuity, list, add, generate, results, resultList].some((value) => !value)) return { abort: () => {} };
    let scenes = []; let prompts = []; let controller = null; let token = 0; let dirty = false;
    const headers = () => ({ Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken });
    const syncScenes = () => [...list.querySelectorAll('[data-scenario-scene]')].map((card) => ({ id: card.dataset.id, title: card.querySelector('[data-scene-title]').value.trim(), description: card.querySelector('[data-scene-description]').value.trim(), changesFromPrevious: card.querySelector('[data-scene-changes]').value.trim() }));
    const renderScenes = () => {
        list.replaceChildren(...scenes.map((scene, index) => {
            const card = documentObject.createElement('article'); card.dataset.scenarioScene = ''; card.dataset.id = scene.id;
            const title = documentObject.createElement('input'); title.dataset.sceneTitle = ''; title.value = scene.title; title.maxLength = 100;
            const detail = documentObject.createElement('textarea'); detail.dataset.sceneDescription = ''; detail.value = scene.description; detail.maxLength = 1000;
            const changes = documentObject.createElement('textarea'); changes.dataset.sceneChanges = ''; changes.value = scene.changesFromPrevious; changes.maxLength = 1000;
            const up = documentObject.createElement('button'); up.type = 'button'; up.textContent = '上へ'; up.disabled = index === 0; up.addEventListener('click', () => { scenes = moveScenarioScene(syncScenes(), index, index - 1); renderScenes(); dirty = true; });
            const down = documentObject.createElement('button'); down.type = 'button'; down.textContent = '下へ'; down.disabled = index === scenes.length - 1; down.addEventListener('click', () => { scenes = moveScenarioScene(syncScenes(), index, index + 1); renderScenes(); dirty = true; });
            const remove = documentObject.createElement('button'); remove.type = 'button'; remove.textContent = '削除'; remove.disabled = scenes.length <= 3; remove.addEventListener('click', () => { scenes = syncScenes().filter((_, current) => current !== index); renderScenes(); dirty = true; });
            [title, detail, changes].forEach((input) => input.addEventListener('input', () => { dirty = true; }));
            card.append(title, detail, changes, up, down, remove); return card;
        }));
        add.disabled = scenes.length >= 10;
    };
    const renderResults = () => {
        resultList.replaceChildren(...prompts.map((prompt) => {
            const card = documentObject.createElement('article'); card.dataset.scenarioResult = ''; card.dataset.id = prompt.id;
            const positive = documentObject.createElement('textarea'); positive.dataset.scenePositive = ''; positive.value = prompt.positivePrompt;
            const negative = documentObject.createElement('textarea'); negative.dataset.sceneNegative = ''; negative.value = prompt.negativePrompt;
            const instruction = documentObject.createElement('input'); instruction.dataset.sceneRegenerationInstruction = '';
            const regenerate = documentObject.createElement('button'); regenerate.type = 'button'; regenerate.dataset.scenarioRegenerate = ''; regenerate.textContent = 'この場面を再生成';
            regenerate.addEventListener('click', async () => {
                const response = await fetcher(page.dataset.illustrationScenarioScenePromptUrl, { method: 'POST', headers: headers(), body: JSON.stringify({ description: description.value.trim(), continuityNotes: continuity.value.trim(), scenes: syncScenes(), currentPrompts: prompts, targetId: prompt.id, instruction: instruction.value.trim(), promptStyle: style.value }) });
                const body = await json(response); if (!response.ok || !body?.scene) { status.textContent = body?.message ?? '再生成に失敗しました。'; return; }
                prompts = prompts.map((item) => item.id === prompt.id ? body.scene : item); renderResults(); dirty = true;
            });
            const apply = documentObject.createElement('button'); apply.type = 'button'; apply.dataset.scenarioApply = ''; apply.textContent = '既存出力へ反映'; apply.addEventListener('click', () => applyPrompt({ positivePrompt: positive.value, negativePrompt: negative.value, includeDefaultPrompts: true }));
            card.append(positive, negative, instruction, regenerate, apply); return card;
        }));
    };
    open.addEventListener('click', () => dialog.showModal());
    close.addEventListener('click', () => { if (!dirty || confirmAction('シナリオの編集・生成結果を破棄しますか？')) { controller?.abort(); dialog.close(); } });
    planButton.addEventListener('click', async () => {
        const text = description.value.trim(); const sceneCount = Number(count.value);
        if (text === '' || text.length > 2000 || !Number.isInteger(sceneCount) || sceneCount < 3 || sceneCount > 10) { status.textContent = '説明と3〜10の場面数を確認してください。'; return; }
        controller?.abort(); controller = new AbortController(); const current = ++token; planButton.disabled = true;
        try { const response = await fetcher(page.dataset.illustrationScenarioPlanUrl, { method: 'POST', headers: headers(), body: JSON.stringify({ description: text, sceneCount }), signal: controller.signal }); const body = await json(response); if (current !== token) return; if (!response.ok || !Array.isArray(body?.scenes)) { status.textContent = body?.message ?? 'シナリオ案の生成に失敗しました。'; return; } continuity.value = body.continuityNotes; scenes = body.scenes; renderScenes(); edit.hidden = false; results.hidden = true; dirty = true; status.textContent = 'シナリオ案を編集できます。'; } catch (error) { if (error?.name !== 'AbortError') status.textContent = 'シナリオ案の生成に失敗しました。'; } finally { if (current === token) planButton.disabled = false; }
    });
    add.addEventListener('click', () => { scenes = syncScenes(); if (scenes.length >= 10) return; scenes.push({ id: createId(), title: '新しい場面', description: '', changesFromPrevious: '' }); renderScenes(); dirty = true; });
    generate.addEventListener('click', async () => {
        scenes = syncScenes(); if (scenes.some((scene) => scene.title === '' || scene.description === '')) { status.textContent = '各場面のタイトルと説明を入力してください。'; return; }
        const response = await fetcher(page.dataset.illustrationScenarioPromptsUrl, { method: 'POST', headers: headers(), body: JSON.stringify({ description: description.value.trim(), continuityNotes: continuity.value.trim(), scenes, promptStyle: style.value }) });
        const body = await json(response); if (!response.ok || !Array.isArray(body?.scenes) || body.scenes.length !== scenes.length || body.scenes.some((item, index) => item.id !== scenes[index].id || typeof item.positivePrompt !== 'string' || item.positivePrompt.trim() === '')) { status.textContent = body?.message ?? '場面プロンプトの生成に失敗しました。'; return; }
        prompts = body.scenes; renderResults(); results.hidden = false; dirty = true; status.textContent = '各場面を確認して反映できます。';
    });
    return { abort: () => { token += 1; controller?.abort(); } };
};
