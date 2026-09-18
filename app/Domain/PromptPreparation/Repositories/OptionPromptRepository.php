<?php

namespace App\Domain\PromptPreparation\Repositories;

use App\Domain\PromptPreparation\Models\OptionPrompt;

interface OptionPromptRepository
{
    public function get(int $id): OptionPrompt;

    public function nextPosition(int $groupId): int;

    public function save(OptionPrompt $prompt): OptionPrompt;

    public function delete(int $id): void;

    public function normalizePositions(int $groupId): void;

    public function moveBefore(int $id, int $targetGroupId, ?int $beforeId): void;
}
