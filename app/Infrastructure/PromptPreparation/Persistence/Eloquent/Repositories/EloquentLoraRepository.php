<?php

namespace App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories;

use App\Domain\PromptPreparation\Models\Lora\Lora;
use App\Domain\PromptPreparation\Models\Lora\LoraFileName;
use App\Domain\PromptPreparation\Models\Lora\LoraStrength;
use App\Domain\PromptPreparation\Repositories\LoraRepository;
use App\Domain\PromptPreparation\Repositories\DuplicateLoraFileName;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models\LoraRecord;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;

final class EloquentLoraRepository implements LoraRepository
{
    public function save(Lora $lora): Lora
    {
        $record = $lora->id === null
            ? new LoraRecord()
            : LoraRecord::query()->findOrFail($lora->id);

        try {
            $record->fill([
                'name' => $lora->name,
                'file_name' => $lora->fileName->value,
                'recommended_strength_step' => $lora->recommendedStrength->step,
            ])->save();
        } catch (UniqueConstraintViolationException $exception) {
            throw new DuplicateLoraFileName(previous: $exception);
        }

        return new Lora(
            id: $record->getKey(),
            name: $record->name,
            fileName: new LoraFileName($record->file_name),
            recommendedStrength: LoraStrength::fromStep($record->recommended_strength_step),
        );
    }

    public function delete(int $id): void
    {
        $deleted = LoraRecord::query()->whereKey($id)->delete();

        if ($deleted === 0) {
            throw (new ModelNotFoundException())->setModel(LoraRecord::class, [$id]);
        }
    }
}
