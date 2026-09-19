import { mergeUniquePromptGroups } from './prompt-text.js';

const isObject = (value) => typeof value === 'object' && value !== null && !Array.isArray(value);
const isLora = (value) =>
    isObject(value) &&
    Number.isInteger(value.id) &&
    typeof value.name === 'string' &&
    typeof value.fileName === 'string' &&
    Number.isFinite(value.recommendedStrength) &&
    Array.isArray(value.tags) &&
    value.tags.length === 11 &&
    value.tags.every((tag) => typeof tag === 'string');
const isTrigger = (value) =>
    isObject(value) &&
    Number.isInteger(value.id) &&
    Number.isInteger(value.loraId) &&
    typeof value.name === 'string' &&
    typeof value.content === 'string';

const assertSuccessfulResponse = (response) => {
    if (!response.ok) {
        throw new Error('The clothing LoRA request failed.');
    }
};

export const requestClothingLoraOptions = async (fetcher, url) => {
    const response = await fetcher(url, { headers: { Accept: 'application/json' } });
    assertSuccessfulResponse(response);
    const result = await response.json();
    if (
        !isObject(result) ||
        !Array.isArray(result.loras) ||
        !result.loras.every(isLora) ||
        !Array.isArray(result.triggers) ||
        !result.triggers.every(isTrigger)
    ) {
        throw new Error('The clothing LoRA response is invalid.');
    }
    return result;
};

const requestSaveResource = async (fetcher, url, csrfToken, id, values) => {
    const response = await fetcher(id === null ? url : `${url}/${id}`, {
        method: id === null ? 'POST' : 'PUT',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify(values),
    });
    assertSuccessfulResponse(response);
    return response.json();
};

const requestDeleteResource = async (fetcher, url, csrfToken, id) => {
    const response = await fetcher(`${url}/${id}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
    });
    assertSuccessfulResponse(response);
};

export const saveClothingLora = requestSaveResource;
export const saveClothingLoraTrigger = requestSaveResource;
export const deleteClothingLora = requestDeleteResource;
export const deleteClothingLoraTrigger = requestDeleteResource;

export const filterClothingLoras = (loras, searchText, selectedIds) => {
    const search = searchText.trim().toLocaleLowerCase();
    return [...loras]
        .filter(
            (lora) =>
                search === '' ||
                selectedIds.has(lora.id) ||
                lora.name.toLocaleLowerCase().includes(search) ||
                lora.fileName.toLocaleLowerCase().includes(search),
        )
        .sort((left, right) => {
            const selectedOrder =
                Number(selectedIds.has(right.id)) - Number(selectedIds.has(left.id));
            return selectedOrder || left.name.localeCompare(right.name) || left.id - right.id;
        });
};

export const createClothingLoraSections = (loras, triggers, selections) => {
    const selected = loras
        .filter((lora) => selections.has(lora.id))
        .sort((left, right) => left.name.localeCompare(right.name) || left.id - right.id);
    const loraTags = selected.map((lora) => {
        const strength = selections.get(lora.id)?.strength ?? lora.recommendedStrength;
        return lora.tags[Math.round(strength * 10)];
    });
    const triggerContents = selected
        .map((lora) => {
            const triggerId = selections.get(lora.id)?.triggerId ?? null;
            return triggers.find((trigger) => trigger.id === triggerId)?.content ?? '';
        })
        .filter((content) => content !== '');

    return {
        loras: loraTags.join(' '),
        triggers: mergeUniquePromptGroups([triggerContents])[0] ?? '',
    };
};
