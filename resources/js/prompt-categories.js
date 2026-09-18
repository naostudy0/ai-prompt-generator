const isObject = (value) => typeof value === 'object' && value !== null && !Array.isArray(value);
const isNamedPromptCandidate = (value) =>
    isObject(value) &&
    Number.isInteger(value.id) &&
    typeof value.name === 'string' &&
    value.name.trim() !== '' &&
    typeof value.content === 'string' &&
    value.content.replace(/[\s,]/gu, '') !== '';

const assertResponse = (response) => {
    if (!response.ok) {
        throw new Error('The prompt category request failed.');
    }
};

export const requestPromptCategories = async (fetcher, characterUrl, sceneUrl, optionUrl) => {
    const [characterResponse, sceneResponse, optionResponse] = await Promise.all([
        fetcher(characterUrl, { headers: { Accept: 'application/json' } }),
        fetcher(sceneUrl, { headers: { Accept: 'application/json' } }),
        fetcher(optionUrl, { headers: { Accept: 'application/json' } }),
    ]);
    assertResponse(characterResponse);
    assertResponse(sceneResponse);
    assertResponse(optionResponse);
    const character = await characterResponse.json();
    const scene = await sceneResponse.json();
    const option = await optionResponse.json();

    if (
        !isObject(character) ||
        !Array.isArray(character.expressions) ||
        !character.expressions.every(isNamedPromptCandidate) ||
        !Array.isArray(character.gazes) ||
        !character.gazes.every(isNamedPromptCandidate) ||
        !isObject(scene) ||
        !Array.isArray(scene.locations) ||
        !scene.locations.every(isNamedPromptCandidate) ||
        !Array.isArray(scene.compositions) ||
        !scene.compositions.every(isNamedPromptCandidate) ||
        !Array.isArray(scene.actions) ||
        !scene.actions.every(isNamedPromptCandidate) ||
        !isObject(option) ||
        !Array.isArray(option.options) ||
        !option.options.every(isNamedPromptCandidate)
    ) {
        throw new Error('The prompt category response is invalid.');
    }

    return {
        expression: character.expressions,
        gaze: character.gazes,
        location: scene.locations,
        composition: scene.compositions,
        action: scene.actions,
        option: option.options,
    };
};

export const saveNamedPrompt = async (fetcher, url, csrfToken, id, values) => {
    const response = await fetcher(id === null ? url : `${url}/${id}`, {
        method: id === null ? 'POST' : 'PUT',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify(values),
    });
    assertResponse(response);
    return response.json();
};

export const deleteNamedPrompt = async (fetcher, url, csrfToken, id) => {
    const response = await fetcher(`${url}/${id}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
    });
    assertResponse(response);
};

export const filterOptionsKeepingSelection = (options, searchText, selectedIds) => {
    const search = searchText.trim().toLocaleLowerCase();
    return options.filter(
        (option) =>
            search === '' ||
            option.name.toLocaleLowerCase().includes(search) ||
            selectedIds.has(option.id),
    );
};

const splitPromptElements = (content) => {
    const elements = [];
    let current = '';
    let depth = 0;
    let escaped = false;

    for (const character of content) {
        if (escaped) {
            current += character;
            escaped = false;
            continue;
        }
        if (character === '\\') {
            current += character;
            escaped = true;
            continue;
        }
        if (character === '(') {
            depth++;
        } else if (character === ')') {
            if (depth === 0) {
                return null;
            }
            depth--;
        }
        if (character === ',' && depth === 0) {
            const element = current.trim();
            if (element !== '') {
                elements.push(element);
            }
            current = '';
            continue;
        }
        current += character;
    }

    if (depth !== 0) {
        return null;
    }
    const last = current.trim();
    if (last !== '') {
        elements.push(last);
    }
    return elements;
};

export const mergeUniquePromptContents = (contents) => {
    const elements = [];
    for (const content of contents) {
        const split = splitPromptElements(content);
        if (split === null) {
            return contents.join(' ');
        }
        elements.push(...split);
    }

    return [...new Set(elements)].join(', ') + (elements.length === 0 ? '' : ',');
};

export const createCategorySections = ({ options, selected }) => {
    const selectedContent = (type) =>
        options[type]
            .filter((option) => selected[type].has(option.id))
            .map((option) => option.content);
    const singleContent = (type) => selectedContent(type)[0] ?? '';
    const joinMultiple = (type) => selectedContent(type).join(' ');

    return {
        expression: singleContent('expression'),
        gaze: singleContent('gaze'),
        action: joinMultiple('action'),
        location: mergeUniquePromptContents(selectedContent('location')),
        composition: singleContent('composition'),
        option: mergeUniquePromptContents(selectedContent('option')),
    };
};
