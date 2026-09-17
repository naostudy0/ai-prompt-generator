<?php

namespace Tests\Feature\Infrastructure\PromptPreparation;

use App\Domain\PromptPreparation\Models\DefaultPrompt\PromptPolarity;
use App\Infrastructure\PromptPreparation\Persistence\Eloquent\Models\DefaultPromptRecord;
use App\Infrastructure\PromptPreparation\Queries\EloquentDefaultPromptQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EloquentDefaultPromptQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_SQLiteからpositiveとnegativeのデフォルトプロンプトを取得する(): void
    {
        $expectedPositive = 'masterpiece, best quality, highres,';
        $expectedNegative = 'bad anatomy, bad hands, extra fingers,';
        DefaultPromptRecord::query()->create([
            'polarity' => PromptPolarity::Positive->value,
            'content' => $expectedPositive,
        ]);
        DefaultPromptRecord::query()->create([
            'polarity' => PromptPolarity::Negative->value,
            'content' => $expectedNegative,
        ]);
        $queryService = new EloquentDefaultPromptQueryService();

        $result = $queryService->get();

        $this->assertSame($expectedPositive, $result->positive);
        $this->assertSame($expectedNegative, $result->negative);
    }

    public function test_保存されたデフォルトプロンプトがない場合は空文字列を取得する(): void
    {
        $expectedPositive = '';
        $expectedNegative = '';
        $queryService = new EloquentDefaultPromptQueryService();

        $result = $queryService->get();

        $this->assertSame($expectedPositive, $result->positive);
        $this->assertSame($expectedNegative, $result->negative);
    }

    public function test_positiveだけが保存されている場合はnegativeを空文字列として取得する(): void
    {
        $expectedPositive = 'masterpiece, best quality, highres,';
        $expectedNegative = '';
        DefaultPromptRecord::query()->create([
            'polarity' => PromptPolarity::Positive->value,
            'content' => $expectedPositive,
        ]);
        $queryService = new EloquentDefaultPromptQueryService();

        $result = $queryService->get();

        $this->assertSame($expectedPositive, $result->positive);
        $this->assertSame($expectedNegative, $result->negative);
    }

    public function test_negativeだけが保存されている場合はpositiveを空文字列として取得する(): void
    {
        $expectedPositive = '';
        $expectedNegative = 'bad anatomy, bad hands, extra fingers,';
        DefaultPromptRecord::query()->create([
            'polarity' => PromptPolarity::Negative->value,
            'content' => $expectedNegative,
        ]);
        $queryService = new EloquentDefaultPromptQueryService();

        $result = $queryService->get();

        $this->assertSame($expectedPositive, $result->positive);
        $this->assertSame($expectedNegative, $result->negative);
    }
}
