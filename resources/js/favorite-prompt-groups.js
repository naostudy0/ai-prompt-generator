const storageVersion = 1;
const favoriteKeyPattern = /^favorite:[1-9]\d*$/;

const isFavoriteKey = (key) => typeof key === 'string' && favoriteKeyPattern.test(key);

const isGroup = (group) =>
    group !== null &&
    typeof group === 'object' &&
    typeof group.name === 'string' &&
    group.name.length >= 1 &&
    group.name.length <= 100 &&
    Array.isArray(group.favoriteKeys) &&
    group.favoriteKeys.every(isFavoriteKey);

export const createFavoritePromptKey = (id) => {
    if (!Number.isInteger(id) || id < 1) {
        throw new Error('お気に入りIDが不正です。');
    }
    return `favorite:${id}`;
};

export const parseFavoritePromptGroups = (serialized) => {
    try {
        const value = JSON.parse(serialized);
        if (
            value === null ||
            typeof value !== 'object' ||
            value.version !== storageVersion ||
            !Array.isArray(value.groups) ||
            !value.groups.every(isGroup)
        ) {
            return [];
        }
        return value.groups.map((group) => ({
            name: group.name,
            favoriteKeys: [...group.favoriteKeys],
        }));
    } catch {
        return [];
    }
};

export const serializeFavoritePromptGroups = (groups) =>
    JSON.stringify({ version: storageVersion, groups });

export const parseFavoritePromptChecks = (serialized) => {
    try {
        const value = JSON.parse(serialized);
        if (
            value === null ||
            typeof value !== 'object' ||
            value.version !== storageVersion ||
            !Array.isArray(value.favoriteKeys) ||
            !value.favoriteKeys.every(isFavoriteKey)
        ) {
            return new Set();
        }
        return new Set(value.favoriteKeys);
    } catch {
        return new Set();
    }
};

export const serializeFavoritePromptChecks = (keys) =>
    JSON.stringify({ version: storageVersion, favoriteKeys: [...keys] });

export const prepareFavoritePromptGroup = (name, favoriteKeys) => {
    const normalizedName = name.trim();
    if (normalizedName.length === 0) {
        throw new Error('グループ名を入力してください。');
    }
    if (normalizedName.length > 100) {
        throw new Error('グループ名は100文字以下で入力してください。');
    }
    const uniqueKeys = [...new Set(favoriteKeys)];
    if (!uniqueKeys.every(isFavoriteKey)) {
        throw new Error('お気に入り候補キーが不正です。');
    }
    return { name: normalizedName, favoriteKeys: uniqueKeys };
};

export const findFavoritePromptGroupIndex = (groups, name) => {
    const normalizedName = name.trim().toLocaleLowerCase();
    return groups.findIndex((group) => group.name.toLocaleLowerCase() === normalizedName);
};

export const replaceFavoritePromptGroup = (groups, group, index) =>
    index < 0
        ? [...groups, group]
        : groups.map((current, currentIndex) => (currentIndex === index ? group : current));

export const removeFavoritePromptGroup = (groups, index) =>
    groups.filter((_, currentIndex) => currentIndex !== index);

export const resolveFavoritePromptGroup = (favoriteKeys, availableKeys) => {
    const selectedKeys = new Set(favoriteKeys.filter((key) => availableKeys.has(key)));
    return {
        selectedKeys,
        unavailableCount: favoriteKeys.length - selectedKeys.size,
    };
};
