<?php

namespace Tests\Feature\Infrastructure\PromptPreparation;

use App\Domain\PromptPreparation\Models\DefaultPrompt\DefaultPrompt;
use App\Domain\PromptPreparation\Models\PromptText;
use App\Domain\PromptPreparation\Models\DefaultPrompt\PromptPolarity;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Repositories\EloquentDefaultPromptRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EloquentDefaultPromptRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_positiveのデフォルトプロンプトをSQLiteへ保存する(): void
    {
        $content = 'masterpiece, best quality,';
        $repository = new EloquentDefaultPromptRepository();

        $repository->save(new DefaultPrompt(
            polarity: PromptPolarity::Positive,
            text: PromptText::fromInput($content),
        ));

        $this->assertDatabaseHas('default_prompts', [
            'polarity' => PromptPolarity::Positive->value,
            'content' => $content,
        ]);
    }

    public function test_positiveを保存し直してもnegativeは変更しない(): void
    {
        $originalPositive = 'masterpiece,';
        $updatedPositive = 'masterpiece, highres,';
        $negative = 'bad anatomy, bad hands,';
        $repository = new EloquentDefaultPromptRepository();
        $repository->save(new DefaultPrompt(
            polarity: PromptPolarity::Positive,
            text: PromptText::fromInput($originalPositive),
        ));
        $repository->save(new DefaultPrompt(
            polarity: PromptPolarity::Negative,
            text: PromptText::fromInput($negative),
        ));

        $repository->save(new DefaultPrompt(
            polarity: PromptPolarity::Positive,
            text: PromptText::fromInput($updatedPositive),
        ));

        $this->assertDatabaseCount('default_prompts', 2);
        $this->assertDatabaseHas('default_prompts', [
            'polarity' => PromptPolarity::Positive->value,
            'content' => $updatedPositive,
        ]);
        $this->assertDatabaseHas('default_prompts', [
            'polarity' => PromptPolarity::Negative->value,
            'content' => $negative,
        ]);
        $this->assertDatabaseMissing('default_prompts', [
            'polarity' => PromptPolarity::Positive->value,
            'content' => $originalPositive,
        ]);
    }
}
