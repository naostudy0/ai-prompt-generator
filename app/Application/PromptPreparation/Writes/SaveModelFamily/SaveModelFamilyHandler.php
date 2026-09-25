<?php

namespace App\Application\PromptPreparation\Writes\SaveModelFamily;

use App\Application\Shared\TransactionManager;
use App\Domain\PromptPreparation\Models\DefaultPrompt\DefaultPrompt;
use App\Domain\PromptPreparation\Models\DefaultPrompt\PromptPolarity;
use App\Domain\PromptPreparation\Models\ModelFamily;
use App\Domain\PromptPreparation\Models\PromptText;
use App\Domain\PromptPreparation\Repositories\DefaultPromptRepository;
use App\Domain\PromptPreparation\Repositories\ModelFamilyRepository;

final readonly class SaveModelFamilyHandler
{
    public function __construct(
        private ModelFamilyRepository $families,
        private DefaultPromptRepository $prompts,
        private TransactionManager $transactions,
    ) {
    }

    /** @return array{id: int, name: string} */
    public function handle(?int $id, string $name): array
    {
        $family = new ModelFamily($name);

        return $this->transactions->run(function () use ($id, $family): array {
            $savedId = $this->families->save($id, $family);
            if ($id === null) {
                foreach (PromptPolarity::cases() as $polarity) {
                    $this->prompts->save(new DefaultPrompt(
                        $polarity,
                        PromptText::fromInput(''),
                        $savedId,
                    ));
                }
            }

            return ['id' => $savedId, 'name' => $family->name];
        });
    }
}
