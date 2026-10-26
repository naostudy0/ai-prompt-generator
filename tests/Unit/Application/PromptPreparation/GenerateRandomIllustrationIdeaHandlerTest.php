<?php

namespace Tests\Unit\Application\PromptPreparation;

use App\Application\PromptPreparation\DTO\RandomIllustrationFixedSelection;
use App\Application\PromptPreparation\DTO\RandomIllustrationIdea;
use App\Application\PromptPreparation\DTO\RandomIllustrationOptionCandidate;
use App\Application\PromptPreparation\DTO\RandomIllustrationSelectionIds;
use App\Application\PromptPreparation\Ports\RandomIllustrationIdeaGenerator;
use App\Application\PromptPreparation\Ports\RandomIllustrationInstructions;
use App\Application\PromptPreparation\Ports\RandomIllustrationSelectionQueryService;
use App\Application\PromptPreparation\Ports\RandomSource;
use App\Application\PromptPreparation\Writes\GenerateRandomIllustrationIdea\GenerateRandomIllustrationIdeaHandler;
use App\Application\PromptPreparation\Writes\GenerateRandomIllustrationIdea\GenerateRandomIllustrationIdeaInput;
use PHPUnit\Framework\TestCase;

final class GenerateRandomIllustrationIdeaHandlerTest extends TestCase
{
    public function test_未選択なら登録済み場所を抽選して編集可能な案を返す(): void
    {
        $candidate = new RandomIllustrationOptionCandidate(8, 2, 'どこでも', 'single', '図書館', 'library,', true);
        $query = new class ($candidate) implements RandomIllustrationSelectionQueryService {
            public function __construct(private RandomIllustrationOptionCandidate $candidate)
            {
            }
            public function resolveFixedSelections(RandomIllustrationSelectionIds $ids): array
            {
                return [];
            }
            public function locationCandidates(): array
            {
                return [$this->candidate];
            }
            public function resolutionCandidates(): array
            {
                return [$this->candidate];
            }
        };
        $random = new class () implements RandomSource {
            public function pick(array $candidates): mixed
            {
                return $candidates[0] ?? null;
            }
        };
        $instructions = new class () implements RandomIllustrationInstructions {
            public function generation(): string
            {
                return '日本語で生成';
            }
            public function resolution(): string
            {
                return '解析';
            }
        };
        $generator = new class () implements RandomIllustrationIdeaGenerator {
            public ?RandomIllustrationOptionCandidate $anchor = null;
            public function generate(string $systemPrompt, array $fixed, ?RandomIllustrationOptionCandidate $anchor): RandomIllustrationIdea
            {
                $this->anchor = $anchor;
                return new RandomIllustrationIdea('静かな図書館の一枚絵', 'fake', 5);
            }
        };

        $result = (new GenerateRandomIllustrationIdeaHandler($query, $random, $instructions, $generator))->handle(new GenerateRandomIllustrationIdeaInput(new RandomIllustrationSelectionIds()));

        self::assertSame($candidate, $generator->anchor);
        self::assertSame(['id' => 8, 'label' => '図書館'], $result->locationAnchor);
        self::assertSame('静かな図書館の一枚絵', $result->description);
    }

    public function test_選択済み場所は固定されて別の場所を抽選しない(): void
    {
        $fixed = new RandomIllustrationFixedSelection('option:3', '教室', 'classroom,', 'option', 3, 2, 'single', true);
        $query = new class ($fixed) implements RandomIllustrationSelectionQueryService {
            public function __construct(private RandomIllustrationFixedSelection $fixed)
            {
            }
            public function resolveFixedSelections(RandomIllustrationSelectionIds $ids): array
            {
                return [$this->fixed];
            }
            public function locationCandidates(): array
            {
                return [new RandomIllustrationOptionCandidate(8, 2, '場所', 'single', '図書館', 'library,', true)];
            }
            public function resolutionCandidates(): array
            {
                return [];
            }
        };
        $random = new class () implements RandomSource {
            public bool $called = false;
            public function pick(array $candidates): mixed
            {
                $this->called = true;
                return null;
            }
        };
        $instructions = new class () implements RandomIllustrationInstructions {
            public function generation(): string
            {
                return '生成';
            }
            public function resolution(): string
            {
                return '解析';
            }
        };
        $generator = new class () implements RandomIllustrationIdeaGenerator {
            public function generate(string $systemPrompt, array $fixed, ?RandomIllustrationOptionCandidate $anchor): RandomIllustrationIdea
            {
                TestCase::assertNull($anchor);
                return new RandomIllustrationIdea('教室', 'fake', 1);
            }
        };
        $result = (new GenerateRandomIllustrationIdeaHandler($query, $random, $instructions, $generator))->handle(new GenerateRandomIllustrationIdeaInput(new RandomIllustrationSelectionIds(optionIds: [3])));

        self::assertFalse($random->called);
        self::assertNull($result->locationAnchor);
        self::assertSame([['key' => 'option:3', 'label' => '教室']], $result->fixedSelectionSummaries);
    }
}
