export const createPositivePromptSections = (lora, categories) => [
    lora.lora,
    lora.trigger,
    categories.expression,
    categories.gaze,
    lora.outfit,
    categories.action,
    categories.location,
    categories.composition,
    categories.option,
];
