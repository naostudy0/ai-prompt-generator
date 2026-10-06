const isObject = (value) => typeof value === 'object' && value !== null && !Array.isArray(value);
const isOption = (value) =>
    isObject(value) &&
    Number.isInteger(value.id) &&
    Number.isInteger(value.position) &&
    value.position > 0 &&
    typeof value.name === 'string' &&
    value.name.trim() !== '' &&
    typeof value.content === 'string' &&
    value.content.replace(/[\s,]/gu, '') !== '';

const assertResponse = (response) => {
    if (!response.ok) {
        throw new Error('The prompt option request failed.');
    }
};

export const requestPromptOptions = async (fetcher, url) => {
    const response = await fetcher(url, { headers: { Accept: 'application/json' } });
    assertResponse(response);
    const result = await response.json();
    if (
        !isObject(result) ||
        !Array.isArray(result.groups) ||
        !result.groups.every(
            (group) =>
                isObject(group) &&
                Number.isInteger(group.id) &&
                Number.isInteger(group.position) &&
                group.position > 0 &&
                (group.categoryId === undefined ||
                    group.categoryId === null ||
                    Number.isInteger(group.categoryId)) &&
                typeof group.name === 'string' &&
                group.name.trim() !== '' &&
                ['single', 'multiple'].includes(group.selectionMode) &&
                Array.isArray(group.options) &&
                group.options.every(isOption),
        )
    ) {
        throw new Error('The prompt option response is invalid.');
    }
    return result.groups;
};

const requestJson = async (fetcher, url, csrfToken, method, values) => {
    const response = await fetcher(url, {
        method,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify(values),
    });
    assertResponse(response);
    return response.status === 204 ? null : response.json();
};

export const saveOptionGroup = (fetcher, url, csrfToken, id, values) =>
    requestJson(
        fetcher,
        id === null ? url : `${url}/${id}`,
        csrfToken,
        id === null ? 'POST' : 'PUT',
        values,
    );

export const saveOption = (fetcher, url, csrfToken, id, values) =>
    requestJson(
        fetcher,
        id === null ? url : `${url}/${id}`,
        csrfToken,
        id === null ? 'POST' : 'PUT',
        values,
    );

export const moveOptionGroup = (fetcher, url, csrfToken, id, beforeGroupId) =>
    requestJson(fetcher, `${url}/${id}/position`, csrfToken, 'PATCH', { beforeGroupId });

export const moveOption = (fetcher, url, csrfToken, id, targetGroupId, beforeOptionId) =>
    requestJson(fetcher, `${url}/${id}/position`, csrfToken, 'PATCH', {
        targetGroupId,
        beforeOptionId,
    });

export const deleteOption = async (fetcher, url, csrfToken, id) => {
    const response = await fetcher(`${url}/${id}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
    });
    assertResponse(response);
};

export const requestOptionCategories = async (fetcher, url) => {
    const response = await fetcher(url, { headers: { Accept: 'application/json' } });
    assertResponse(response);
    const result = await response.json();
    if (
        !isObject(result) ||
        !Array.isArray(result.categories) ||
        !result.categories.every(
            (category) =>
                isObject(category) &&
                Number.isInteger(category.id) &&
                Number.isInteger(category.position) &&
                typeof category.name === 'string' &&
                category.name.trim() !== '',
        )
    ) {
        throw new Error('The option category response is invalid.');
    }
    return result.categories;
};

export const saveOptionCategory = (fetcher, url, csrfToken, id, name) =>
    requestJson(
        fetcher,
        id === null ? url : `${url}/${id}`,
        csrfToken,
        id === null ? 'POST' : 'PUT',
        { name },
    );

export const deleteOptionCategory = (fetcher, url, csrfToken, id) =>
    requestJson(fetcher, `${url}/${id}`, csrfToken, 'DELETE', {});

export const moveOptionCategory = (fetcher, url, csrfToken, id, beforeCategoryId) =>
    requestJson(fetcher, `${url}/${id}/position`, csrfToken, 'PATCH', { beforeCategoryId });

export const changeOptionGroupCategory = (fetcher, url, csrfToken, id, categoryId) =>
    requestJson(fetcher, `${url}/${id}/category`, csrfToken, 'PATCH', { categoryId });

export const filterOptionsKeepingSelection = (options, searchText, selectedIds) => {
    const search = searchText.trim().toLocaleLowerCase();
    return options.filter(
        (option) =>
            search === '' ||
            option.name.toLocaleLowerCase().includes(search) ||
            selectedIds.has(option.id),
    );
};

export const createOptionSections = (groups, selectedIds) =>
    mergeUniquePromptGroups(
        groups.map((group) =>
            group.options
                .filter((option) => selectedIds.has(option.id))
                .map((option) => option.content),
        ),
    );

export { mergeUniquePromptGroups } from './prompt-text.js';
import { mergeUniquePromptGroups } from './prompt-text.js';
