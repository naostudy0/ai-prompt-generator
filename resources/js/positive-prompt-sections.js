export const createPositivePromptSections = (lora, categories) => [
    lora.lora,
    lora.trigger,
    lora.clothingLoras ?? '',
    lora.clothingLoraTriggers ?? '',
    lora.outfit,
    ...categories.optionGroups,
];
