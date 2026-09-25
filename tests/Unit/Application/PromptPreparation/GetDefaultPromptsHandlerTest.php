<?php

namespace Tests\Unit\Application\PromptPreparation;

use App\Application\PromptPreparation\Ports\DefaultPromptQueryService;
use App\Application\PromptPreparation\Queries\GetDefaultPrompts\GetDefaultPromptsHandler;
use App\Application\PromptPreparation\Queries\GetDefaultPrompts\GetDefaultPromptsResult;
use PHPUnit\Framework\TestCase;

class GetDefaultPromptsHandlerTest extends TestCase
{
    public function test_positiveとnegativeのデフォルトプロンプトを取得する(): void
    {
        $expectedPositive = 'masterpiece, best quality, highres,';
        $expectedNegative = 'bad anatomy, bad hands,';
        $queryService = new class ($expectedPositive, $expectedNegative) implements DefaultPromptQueryService {
            public function __construct(
                private readonly string $positive,
                private readonly string $negative,
            ) {
            }

            public function get(int $modelFamilyId = 1): GetDefaultPromptsResult
            {
                return new GetDefaultPromptsResult(
                    positive: $this->positive,
                    negative: $this->negative,
                );
            }
        };
        $handler = new GetDefaultPromptsHandler($queryService);

        $result = $handler->handle();

        $this->assertSame($expectedPositive, $result->positive);
        $this->assertSame($expectedNegative, $result->negative);
    }
}
