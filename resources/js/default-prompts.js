const assertSuccessfulResponse = (response) => {
    if (!response.ok) {
        throw new Error('The default prompt request failed.');
    }
};

const isPromptPolarity = (value) => value === 'positive' || value === 'negative';

const isObject = (value) => typeof value === 'object' && value !== null && !Array.isArray(value);

export const requestDefaultPrompts = async (fetcher, url) => {
    const response = await fetcher(url, {
        headers: { Accept: 'application/json' },
    });
    assertSuccessfulResponse(response);

    const prompts = await response.json();

    if (
        !isObject(prompts) ||
        typeof prompts.positive !== 'string' ||
        typeof prompts.negative !== 'string'
    ) {
        throw new Error('The default prompt response is invalid.');
    }

    return {
        positive: prompts.positive,
        negative: prompts.negative,
    };
};

export const requestSaveDefaultPrompt = async (
    fetcher,
    url,
    csrfToken,
    expectedPolarity,
    content,
) => {
    const response = await fetcher(url, {
        method: 'PUT',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({ content }),
    });
    assertSuccessfulResponse(response);

    const result = await response.json();

    if (
        !isObject(result) ||
        !isPromptPolarity(result.polarity) ||
        result.polarity !== expectedPolarity ||
        typeof result.content !== 'string' ||
        typeof result.formatSucceeded !== 'boolean'
    ) {
        throw new Error('The default prompt save response is invalid.');
    }

    return {
        polarity: result.polarity,
        content: result.content,
        formatSucceeded: result.formatSucceeded,
    };
};

export const joinPromptSections = (sections) =>
    sections.filter((section) => section !== '').join('\n\n');

export const createPromptOutputs = (prompts, selected) => ({
    positive: selected.positive ? joinPromptSections([prompts.positive]) : '',
    negative: selected.negative ? joinPromptSections([prompts.negative]) : '',
});

export const writePromptToClipboard = async (writeText, content) => {
    await writeText(content);
};
