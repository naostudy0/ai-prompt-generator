export const createPositivePromptSections = (lora, categories) => [
    lora.lora,
    lora.trigger,
    lora.outfit,
    ...categories.optionGroups,
];
