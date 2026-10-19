import { createAiCharacterLoraOutput } from './character-lora-variant.js';
import { joinPromptSections } from './default-prompts.js';

export const createGeneratedPromptApplication = ({
    getDefaultPrompts,
    getSelectedCharacterLora,
    getActiveFamilySource,
    applyOutputPair,
    afterApply = () => {},
}) =>
    ({ positivePrompt, negativePrompt, includeDefaultPrompts }) => {
        if (positivePrompt.trim() === '') {
            return false;
        }
        const defaultSections = includeDefaultPrompts
            ? getDefaultPrompts()
            : { positive: '', negative: '' };
        const positiveBase = joinPromptSections([defaultSections.positive, positivePrompt]);
        const combinedNegative = joinPromptSections([defaultSections.negative, negativePrompt]);
        const selected = getSelectedCharacterLora();
        const output = createAiCharacterLoraOutput(positiveBase, selected.lora, selected.trigger);
        const family = getActiveFamilySource();
        applyOutputPair({
            positive: output.positive,
            negative: combinedNegative,
            variantMetadata: output.metadata,
            variantFamily: {
                modelFamilyId: family.modelFamilyId,
                loraId: selected.loraId,
                defaults: includeDefaultPrompts
                    ? family.defaults
                    : { positive: false, negative: false },
                defaultSections,
            },
        });
        afterApply();
        return true;
    };
