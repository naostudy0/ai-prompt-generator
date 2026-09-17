<?php

namespace Tests\Unit\Application\PromptPreparation;

use App\Application\PromptPreparation\Writes\SaveDefaultPrompt\SaveDefaultPromptHandler;
use App\Application\PromptPreparation\Writes\SaveDefaultPrompt\SaveDefaultPromptInput;
use App\Domain\PromptPreparation\Models\DefaultPrompt\DefaultPrompt;
use App\Domain\PromptPreparation\Models\DefaultPrompt\PromptPolarity;
use App\Domain\PromptPreparation\Repositories\DefaultPromptRepository;
use PHPUnit\Framework\TestCase;

class SaveDefaultPromptHandlerTest extends TestCase
{
    public function test_整形したpositiveのデフォルトプロンプトを保存する(): void
    {
        $inputContent = 'abc,def,abc';
        $expectedContent = 'abc, def,';
        $repository = new class () implements DefaultPromptRepository {
            public ?DefaultPrompt $savedDefaultPrompt = null;

            public function save(DefaultPrompt $defaultPrompt): void
            {
                $this->savedDefaultPrompt = $defaultPrompt;
            }
        };
        $handler = new SaveDefaultPromptHandler($repository);

        $result = $handler->handle(new SaveDefaultPromptInput(
            polarity: PromptPolarity::Positive,
            content: $inputContent,
        ));

        $this->assertSame(PromptPolarity::Positive, $result->polarity);
        $this->assertSame($expectedContent, $result->content);
        $this->assertTrue($result->formatSucceeded);
        $this->assertInstanceOf(DefaultPrompt::class, $repository->savedDefaultPrompt);
        $this->assertSame(PromptPolarity::Positive, $repository->savedDefaultPrompt->polarity);
        $this->assertSame($expectedContent, $repository->savedDefaultPrompt->text->value);
    }

    public function test_整形できない文面は原文のまま保存したことを返す(): void
    {
        $inputContent = '(abc,def';
        $repository = new class () implements DefaultPromptRepository {
            public ?DefaultPrompt $savedDefaultPrompt = null;

            public function save(DefaultPrompt $defaultPrompt): void
            {
                $this->savedDefaultPrompt = $defaultPrompt;
            }
        };
        $handler = new SaveDefaultPromptHandler($repository);

        $result = $handler->handle(new SaveDefaultPromptInput(
            polarity: PromptPolarity::Negative,
            content: $inputContent,
        ));

        $this->assertSame(PromptPolarity::Negative, $result->polarity);
        $this->assertSame($inputContent, $result->content);
        $this->assertFalse($result->formatSucceeded);
        $this->assertInstanceOf(DefaultPrompt::class, $repository->savedDefaultPrompt);
        $this->assertSame(PromptPolarity::Negative, $repository->savedDefaultPrompt->polarity);
        $this->assertSame($inputContent, $repository->savedDefaultPrompt->text->value);
    }

    public function test_空文字列をデフォルトプロンプトとして保存する(): void
    {
        $inputContent = '';
        $repository = new class () implements DefaultPromptRepository {
            public ?DefaultPrompt $savedDefaultPrompt = null;

            public function save(DefaultPrompt $defaultPrompt): void
            {
                $this->savedDefaultPrompt = $defaultPrompt;
            }
        };
        $handler = new SaveDefaultPromptHandler($repository);

        $result = $handler->handle(new SaveDefaultPromptInput(
            polarity: PromptPolarity::Positive,
            content: $inputContent,
        ));

        $this->assertSame($inputContent, $result->content);
        $this->assertTrue($result->formatSucceeded);
        $this->assertInstanceOf(DefaultPrompt::class, $repository->savedDefaultPrompt);
        $this->assertSame($inputContent, $repository->savedDefaultPrompt->text->value);
    }
}
