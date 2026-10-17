const storageVersion = 1;

const isGroup = (group) =>
    group !== null &&
    typeof group === 'object' &&
    typeof group.name === 'string' &&
    group.name.length >= 1 &&
    group.name.length <= 100 &&
    Array.isArray(group.candidateKeys) &&
    group.candidateKeys.every((key) => typeof key === 'string');

export const parseCharacterLoraVariantGroups = (serialized) => {
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
            candidateKeys: [...group.candidateKeys],
        }));
    } catch {
        return [];
    }
};

export const serializeCharacterLoraVariantGroups = (groups) =>
    JSON.stringify({ version: storageVersion, groups });

export const prepareCharacterLoraVariantGroup = (name, candidateKeys) => {
    const normalizedName = name.trim();
    if (normalizedName.length === 0) {
        throw new Error('グループ名を入力してください。');
    }
    if (normalizedName.length > 100) {
        throw new Error('グループ名は100文字以下で入力してください。');
    }
    return {
        name: normalizedName,
        candidateKeys: [...new Set(candidateKeys)],
    };
};

export const findCharacterLoraVariantGroupIndex = (groups, name) => {
    const normalizedName = name.trim().toLocaleLowerCase();
    return groups.findIndex((group) => group.name.toLocaleLowerCase() === normalizedName);
};

export const replaceCharacterLoraVariantGroup = (groups, group, index) => {
    if (index < 0) {
        return [...groups, group];
    }
    return groups.map((current, currentIndex) => (currentIndex === index ? group : current));
};

export const removeCharacterLoraVariantGroup = (groups, index) =>
    groups.filter((_, currentIndex) => currentIndex !== index);

export const resolveCharacterLoraVariantGroup = (candidateKeys, availableKeys) => {
    const selectedKeys = new Set(candidateKeys.filter((key) => availableKeys.has(key)));
    return {
        selectedKeys,
        unavailableCount: candidateKeys.length - selectedKeys.size,
    };
};
