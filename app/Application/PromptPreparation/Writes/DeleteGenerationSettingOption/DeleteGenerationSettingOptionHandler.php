<?php

namespace App\Application\PromptPreparation\Writes\DeleteGenerationSettingOption;

use App\Domain\PromptPreparation\Repositories\GenerationSettingOptionRepository;

final readonly class DeleteGenerationSettingOptionHandler
{
    public function __construct(private GenerationSettingOptionRepository $options)
    {
    }

    public function handle(int $id): void
    {
        $this->options->delete($id);
    }
}
