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
        }
        if (character === ')') {
            if (depth === 0) {
                return null;
            }
            depth--;
        }
        if (character === ',' && depth === 0) {
            if (current.trim() !== '') {
                elements.push(current.trim());
            }
            current = '';
            continue;
        }
        current += character;
    }
    if (depth !== 0) {
        return null;
    }
    if (current.trim() !== '') {
        elements.push(current.trim());
    }
    return elements;
};

export const mergeUniquePromptGroups = (groups) => {
    const splitGroups = groups.map((contents) => contents.map(splitPromptElements));
    if (splitGroups.some((group) => group.some((elements) => elements === null))) {
        return groups.map((contents) => contents.join(' ')).filter((content) => content !== '');
    }
    const seen = new Set();
    return splitGroups
        .map((group) => {
            const elements = group.flat().filter((element) => {
                if (seen.has(element)) {
                    return false;
                }
                seen.add(element);
                return true;
            });
            return elements.join(', ') + (elements.length === 0 ? '' : ',');
        })
        .filter((content) => content !== '');
};

export const normalizePromptForComparison = (content) => {
    const elements = splitPromptElements(content);
    if (elements === null) {
        return content.trim().replace(/\r\n?/gu, '\n');
    }
    return [...new Set(elements)].join('\n');
};
