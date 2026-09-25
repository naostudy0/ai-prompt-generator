const isObject = (value) => typeof value === 'object' && value !== null && !Array.isArray(value);

const isLora = (value) =>
    isObject(value) &&
    Number.isInteger(value.id) &&
    typeof value.name === 'string' &&
    typeof value.fileName === 'string' &&
    (value.modelFamilyId === undefined || Number.isInteger(value.modelFamilyId)) &&
    Number.isFinite(value.recommendedStrength) &&
    value.recommendedStrength >= 0 &&
    value.recommendedStrength <= 1 &&
    Number.isInteger(Math.round(value.recommendedStrength * 10)) &&
    Math.abs(value.recommendedStrength * 10 - Math.round(value.recommendedStrength * 10)) <
        Number.EPSILON * 10 &&
    Array.isArray(value.tags) &&
    value.tags.length === 11 &&
    value.tags.every((tag) => typeof tag === 'string');

const isTrigger = (value) =>
    isObject(value) &&
    Number.isInteger(value.id) &&
    Number.isInteger(value.loraId) &&
    typeof value.name === 'string' &&
    typeof value.content === 'string';

const isOutfit = (value) =>
    isObject(value) &&
    Number.isInteger(value.id) &&
    Number.isInteger(value.loraId) &&
    typeof value.name === 'string' &&
    typeof value.content === 'string';

const assertSuccessfulResponse = (response) => {
    if (!response.ok) {
        throw new Error('The LoRA prompt option request failed.');
    }
};

export const requestLoraPromptOptions = async (fetcher, url) => {
    const response = await fetcher(url, { headers: { Accept: 'application/json' } });
    assertSuccessfulResponse(response);
    const result = await response.json();

    if (
        !isObject(result) ||
        !Array.isArray(result.loras) ||
        !result.loras.every(isLora) ||
        !Array.isArray(result.triggers) ||
        !result.triggers.every(isTrigger) ||
        !Array.isArray(result.outfits) ||
        !result.outfits.every(isOutfit)
    ) {
        throw new Error('The LoRA prompt option response is invalid.');
    }

    return result;
};

export const saveLoraPromptOption = async (fetcher, url, csrfToken, id, values) => {
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

export const deleteLoraPromptOption = async (fetcher, url, csrfToken, id) => {
    const response = await fetcher(`${url}/${id}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
    });
    assertSuccessfulResponse(response);
};

export const filterLoras = (loras, searchText) => {
    const normalizedSearch = searchText.trim().toLocaleLowerCase();

    if (normalizedSearch === '') {
        return loras;
    }

    return loras.filter(
        (lora) =>
            lora.name.toLocaleLowerCase().includes(normalizedSearch) ||
            lora.fileName.toLocaleLowerCase().includes(normalizedSearch),
    );
};

export const orderOutfits = (outfits, selectedLoraId) =>
    [...outfits].sort((left, right) => {
        const leftLinked = left.loraId === selectedLoraId ? 0 : 1;
        const rightLinked = right.loraId === selectedLoraId ? 0 : 1;

        return (
            leftLinked - rightLinked || left.name.localeCompare(right.name) || left.id - right.id
        );
    });

export const selectLoraTag = (lora, strength) => lora.tags[Math.round(strength * 10)];

export const createLoraPromptSections = ({ lora, strength, trigger, outfit }) => {
    const sections = [];

    if (lora !== null) {
        sections.push(selectLoraTag(lora, strength));
    }

    if (trigger !== null) {
        sections.push(trigger.content);
    }

    if (outfit !== null) {
        sections.push(outfit.content);
    }

    return sections;
};

export const createPositivePromptOutput = (defaultPrompt, loraSections) =>
    [defaultPrompt, ...loraSections].filter((section) => section !== '').join('\n\n');
