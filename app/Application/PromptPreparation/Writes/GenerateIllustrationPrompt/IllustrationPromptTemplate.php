<?php

namespace App\Application\PromptPreparation\Writes\GenerateIllustrationPrompt;

final readonly class IllustrationPromptTemplate
{
    public function __construct(
        public string $character = '',
        public string $action = '',
        public string $background = '',
        public string $composition = '',
        public string $atmosphere = '',
        public string $constraints = '',
    ) {
    }

    public function toDescription(): string
    {
        $sections = [
            '人物' => $this->character,
            '動作・表情' => $this->action,
            '場所・背景' => $this->background,
            '構図' => $this->composition,
            '雰囲気' => $this->atmosphere,
            '必須条件' => $this->constraints,
        ];
        $description = [];
        foreach ($sections as $heading => $value) {
            if ($value !== '') {
                $description[] = "【{$heading}】\n{$value}";
            }
        }

        return implode("\n\n", $description);
    }
}
