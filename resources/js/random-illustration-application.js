export const createRandomIllustrationApplication =
    ({ hasOptionIds, restoreOptions, buildOutputPair, applyOutputPair }) =>
    ({ optionIds, suggestions }) => {
        if (!Array.isArray(optionIds) || !hasOptionIds(optionIds)) {
            return false;
        }
        const validSuggestions =
            Array.isArray(suggestions) &&
            suggestions.every(
                (item) =>
                    item &&
                    ['positive', 'negative'].includes(item.polarity) &&
                    typeof item.tags === 'string' &&
                    /[^,\s]/u.test(item.tags),
            );
        if (!validSuggestions) {
            return false;
        }
        restoreOptions(optionIds);
        const outputs = buildOutputPair();
        for (const polarity of ['positive', 'negative']) {
            const additions = suggestions
                .filter((item) => item.polarity === polarity)
                .map((item) => item.tags.trim())
                .filter(Boolean);
            if (additions.length > 0) {
                outputs[polarity] = [outputs[polarity], additions.join(', ')]
                    .filter(Boolean)
                    .join('\n\n');
            }
        }
        applyOutputPair(outputs);
        return true;
    };
