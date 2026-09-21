import { filterLoras, selectLoraTag } from './lora-options.js';

const sectionSeparator = '\n\n';

export const createCharacterLoraSourceMetadata = (defaultPositive, lora, trigger) => {
    const insertionPoint = defaultPositive.length;
    const loraStart = defaultPositive === '' ? 0 : insertionPoint + sectionSeparator.length;
    const loraRange = lora === '' ? null : { start: loraStart, end: loraStart + lora.length };
    const triggerStart = (loraRange?.end ?? loraStart) + sectionSeparator.length;
    return {
        lora,
        trigger,
        defaultPositive,
        insertionPoint,
        loraRange,
        triggerRange:
            trigger === '' ? null : { start: triggerStart, end: triggerStart + trigger.length },
    };
};

export const locateCharacterLoraSourceMetadata = (positive, metadata) => {
    if (metadata.lora === '') {
        return {
            ...metadata,
            insertionPoint: positive.startsWith(metadata.defaultPositive)
                ? metadata.defaultPositive.length
                : 0,
        };
    }

    const tagStart = positive.indexOf(metadata.lora);
    if (tagStart === -1 || positive.indexOf(metadata.lora, tagStart + 1) !== -1) {
        return { ...metadata, loraRange: null, triggerRange: null };
    }

    const triggerMatches = [];
    if (metadata.trigger !== '') {
        const section = `${sectionSeparator}${metadata.trigger}`;
        let searchFrom = tagStart + metadata.lora.length;
        while (searchFrom < positive.length) {
            const matchStart = positive.indexOf(section, searchFrom);
            if (matchStart === -1) {
                break;
            }
            const end = matchStart + section.length;
            if (end === positive.length || positive.startsWith(sectionSeparator, end)) {
                triggerMatches.push({ start: matchStart + sectionSeparator.length, end });
            }
            searchFrom = matchStart + 1;
        }
    }

    return {
        ...metadata,
        loraRange: { start: tagStart, end: tagStart + metadata.lora.length },
        triggerRange: triggerMatches.length === 1 ? triggerMatches[0] : null,
    };
};

export const trackCharacterLoraSourceEdit = (metadata, previous, current) => {
    let prefix = 0;
    while (
        prefix < previous.length &&
        prefix < current.length &&
        previous[prefix] === current[prefix]
    ) {
        prefix += 1;
    }
    let suffix = 0;
    while (
        suffix < previous.length - prefix &&
        suffix < current.length - prefix &&
        previous[previous.length - suffix - 1] === current[current.length - suffix - 1]
    ) {
        suffix += 1;
    }
    const removedEnd = previous.length - suffix;
    const delta = current.length - previous.length;
    const shiftRange = (range) => {
        if (range === null) {
            return null;
        }
        if (removedEnd <= range.start) {
            return { start: range.start + delta, end: range.end + delta };
        }
        if (prefix >= range.end) {
            return range;
        }
        return null;
    };
    return {
        ...metadata,
        insertionPoint:
            removedEnd <= metadata.insertionPoint
                ? metadata.insertionPoint + delta
                : prefix < metadata.insertionPoint
                  ? prefix + current.length - prefix - suffix
                  : metadata.insertionPoint,
        loraRange: shiftRange(metadata.loraRange),
        triggerRange:
            metadata.triggerRange !== null &&
            prefix >= metadata.triggerRange.start &&
            prefix <= metadata.triggerRange.end &&
            (prefix > metadata.triggerRange.start || removedEnd > metadata.triggerRange.start)
                ? null
                : shiftRange(metadata.triggerRange),
    };
};

export const createCharacterLoraVariant = (positive, original, candidate, trigger) => {
    const replacementTag = selectLoraTag(candidate, candidate.recommendedStrength);
    const replacementTrigger = trigger?.content ?? '';
    const replacement = [replacementTag, replacementTrigger]
        .filter((section) => section !== '')
        .join(sectionSeparator);

    if (original.lora === '') {
        const insertionPoint = original.insertionPoint;
        if (insertionPoint === 0) {
            return `${replacement}${positive === '' ? '' : sectionSeparator + positive}`;
        }
        return `${positive.slice(0, insertionPoint)}${sectionSeparator}${replacement}${positive.slice(insertionPoint)}`;
    }

    const tagIndex = original.loraRange?.start;
    if (
        tagIndex === undefined ||
        positive.slice(tagIndex, original.loraRange.end) !== original.lora
    ) {
        throw new Error('元の人物LoRAタグを特定できません。');
    }

    const tagEnd = original.loraRange.end;
    const originalTriggerMatches =
        original.trigger !== '' &&
        original.triggerRange !== null &&
        positive.slice(original.triggerRange.start, original.triggerRange.end) === original.trigger;
    if (originalTriggerMatches) {
        const triggerStart = original.triggerRange.start;
        const removeSeparator =
            replacementTrigger === '' &&
            positive.slice(triggerStart - sectionSeparator.length, triggerStart) ===
                sectionSeparator;
        const start = removeSeparator ? triggerStart - sectionSeparator.length : triggerStart;
        const withTrigger = `${positive.slice(0, start)}${replacementTrigger}${positive.slice(original.triggerRange.end)}`;
        return `${withTrigger.slice(0, tagIndex)}${replacementTag}${withTrigger.slice(tagEnd)}`;
    }

    return `${positive.slice(0, tagIndex)}${replacement}${positive.slice(tagEnd)}`;
};

export const getCharacterLoraCandidates = (options, searchText) =>
    filterLoras(options.loras, searchText).flatMap((lora) => {
        const triggers = options.triggers.filter((item) => item.loraId === lora.id);
        return (triggers.length === 0 ? [null] : triggers).map((trigger) => ({ lora, trigger }));
    });
