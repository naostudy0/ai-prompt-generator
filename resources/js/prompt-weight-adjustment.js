const WEIGHT_STEP = 0.05;
const WEIGHT_PATTERN =
    /^\(([\s\S]*):([+-]?(?:(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?|Infinity|NaN))\)$/;

export const adjustPromptWeight = (selectedText, adjustment) => {
    if (typeof selectedText !== 'string' || selectedText.trim() === '') {
        return null;
    }
    if (adjustment !== 'increase' && adjustment !== 'decrease') {
        return null;
    }

    const match = selectedText.match(WEIGHT_PATTERN);
    const promptText = match?.[1] ?? selectedText;
    const currentWeight = match === null ? 1 : Number(match[2]);

    if (promptText.trim() === '' || !Number.isFinite(currentWeight)) {
        return null;
    }

    const delta = adjustment === 'increase' ? WEIGHT_STEP : -WEIGHT_STEP;
    const adjustedWeight = Number((currentWeight + delta).toFixed(2));

    return adjustedWeight === 1 ? promptText : `(${promptText}:${adjustedWeight})`;
};
