import {
    createCharacterLoraSourceMetadata,
    createStrictCharacterLoraVariant,
} from './character-lora-variant.js';
import { selectLoraTag } from './lora-options.js';

export const prepareFavoriteCharacterLoraQueueItem = ({
    favorite,
    key,
    options,
    selection,
    selectedSections,
}) => {
    const selectedLora = options.loras.find((lora) => lora.id === selection.loraId);
    if (!selectedLora) {
        throw new Error('現在選択中の人物LoRAを利用できません。');
    }
    const selectedTrigger =
        selection.triggerId === null
            ? null
            : options.triggers.find(
                  (trigger) =>
                      trigger.id === selection.triggerId && trigger.loraId === selectedLora.id,
              );
    if (selection.triggerId !== null && !selectedTrigger) {
        throw new Error('現在選択中の人物LoRAトリガーを利用できません。');
    }

    const savedSelection = favorite.selectionSnapshot?.lora ?? {};
    const defaultPositive = favorite.selectionSnapshot?.defaultSections?.positive ?? '';
    let originalTag = '';
    let originalTrigger = '';
    if (savedSelection.loraId !== null && savedSelection.loraId !== undefined) {
        const savedLora = options.loras.find((lora) => lora.id === savedSelection.loraId);
        if (!savedLora) {
            throw new Error('保存時の人物LoRAを利用できません。');
        }
        originalTag = selectLoraTag(savedLora, Number(savedSelection.strength ?? 1));
        if (savedSelection.triggerId !== null && savedSelection.triggerId !== undefined) {
            const savedTrigger = options.triggers.find(
                (trigger) =>
                    trigger.id === savedSelection.triggerId && trigger.loraId === savedLora.id,
            );
            if (!savedTrigger) {
                throw new Error('保存時の人物LoRAトリガーを利用できません。');
            }
            originalTrigger = savedTrigger.content;
        }
    }

    const original = createCharacterLoraSourceMetadata(
        defaultPositive,
        originalTag,
        originalTrigger,
    );
    return {
        candidateKey: key,
        modelFamilyId: selectedLora.modelFamilyId ?? 1,
        loraId: selectedLora.id,
        positive: createStrictCharacterLoraVariant(
            favorite.positivePrompt,
            original,
            selectedSections.lora,
            selectedSections.trigger,
        ),
        negative: favorite.negativePrompt,
    };
};
