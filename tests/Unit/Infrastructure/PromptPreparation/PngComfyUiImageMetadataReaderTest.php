<?php

namespace Tests\Unit\Infrastructure\PromptPreparation;

use App\Application\PromptPreparation\Exceptions\InvalidComfyUiImage;
use App\Infrastructure\PromptPreparation\Images\PngComfyUiImageMetadataReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PngComfyUiImageMetadataReaderTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryPaths = [];

    /** @return iterable<string, array{string}> */
    public static function supportedChunks(): iterable
    {
        $json = '{"37":{"inputs":{"prompt":"fictional positive,"},"class_type":"Example"}}';

        yield 'tEXt' => [self::chunk('tEXt', "prompt\0".$json)];
        yield 'iTXt' => [self::chunk('iTXt', "prompt\0\0\0\0\0".$json)];
        yield 'zTXt' => [self::chunk('zTXt', "prompt\0\0".gzcompress($json))];
        yield 'compressed iTXt' => [self::chunk('iTXt', "prompt\0\1\0\0\0".gzcompress($json))];
    }

    #[DataProvider('supportedChunks')]
    public function test_対応するPNGテキストチャンクからAPI形式ワークフローを読み取る(string $metadata): void
    {
        $path = $this->temporaryPng($metadata);

        $result = (new PngComfyUiImageMetadataReader)->read($path);

        self::assertSame('fictional positive,', $result->prompt['37']['inputs']['prompt']);
    }

    public function test_promptがないPNGを拒否する(): void
    {
        $this->expectException(InvalidComfyUiImage::class);

        (new PngComfyUiImageMetadataReader)->read($this->temporaryPng());
    }

    public function test_promptが重複するPNGを拒否する(): void
    {
        $json = '{"1":{"inputs":{"text":"fictional"}}}';
        $this->expectException(InvalidComfyUiImage::class);

        (new PngComfyUiImageMetadataReader)->read($this->temporaryPng(
            self::chunk('tEXt', "prompt\0".$json).self::chunk('tEXt', "prompt\0".$json),
        ));
    }

    public function test_壊れたCRCを拒否する(): void
    {
        $chunk = self::chunk('tEXt', "prompt\0{}");
        $chunk[strlen($chunk) - 1] = chr(ord($chunk[strlen($chunk) - 1]) ^ 0xff);
        $this->expectException(InvalidComfyUiImage::class);

        (new PngComfyUiImageMetadataReader)->read($this->temporaryPng($chunk));
    }

    public function test_途中で切れたチャンクを拒否する(): void
    {
        $this->expectException(InvalidComfyUiImage::class);

        (new PngComfyUiImageMetadataReader)->read($this->temporaryFile("\x89PNG\r\n\x1a\n".pack('N', 100).'tEXtshort'));
    }

    public function test_展開後promptが5MiBを超える場合に拒否する(): void
    {
        $json = '{"value":"'.str_repeat('a', 5 * 1024 * 1024).'"}';
        $this->expectException(InvalidComfyUiImage::class);

        (new PngComfyUiImageMetadataReader)->read($this->temporaryPng(
            self::chunk('zTXt', "prompt\0\0".gzcompress($json)),
        ));
    }

    public function test_JSON深度が128を超える場合に拒否する(): void
    {
        $json = str_repeat('{"a":', 129).'1'.str_repeat('}', 129);
        $this->expectException(InvalidComfyUiImage::class);

        (new PngComfyUiImageMetadataReader)->read($this->temporaryPng(self::chunk('tEXt', "prompt\0".$json)));
    }

    private function temporaryPng(string $metadata = ''): string
    {
        return $this->temporaryFile("\x89PNG\r\n\x1a\n".$metadata.self::chunk('IEND', ''));
    }

    private function temporaryFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'comfyui-png-');
        self::assertIsString($path);
        file_put_contents($path, $content);
        $this->temporaryPaths[] = $path;

        return $path;
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryPaths as $path) {
            @unlink($path);
        }
        parent::tearDown();
    }

    private static function chunk(string $type, string $data): string
    {
        $body = $type.$data;

        return pack('N', strlen($data)).$body.pack('N', crc32($body));
    }
}
