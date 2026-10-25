<?php

namespace App\Infrastructure\PromptPreparation\Images;

use App\Application\PromptPreparation\DTO\ComfyUiImageMetadata;
use App\Application\PromptPreparation\Exceptions\InvalidComfyUiImage;
use App\Application\PromptPreparation\Ports\ComfyUiImageMetadataReader;
use JsonException;

final class PngComfyUiImageMetadataReader implements ComfyUiImageMetadataReader
{
    private const SIGNATURE = "\x89PNG\r\n\x1a\n";

    private const MAX_PROMPT_BYTES = 5 * 1024 * 1024;

    public function read(string $temporaryPath): ComfyUiImageMetadata
    {
        $stream = @fopen($temporaryPath, 'rb');
        if ($stream === false) {
            throw new InvalidComfyUiImage('画像を読み取れませんでした。');
        }

        try {
            if ($this->readBytes($stream, 8) !== self::SIGNATURE) {
                throw new InvalidComfyUiImage('PNG画像を選択してください。');
            }

            $prompt = null;
            while (! feof($stream)) {
                $lengthBytes = $this->readBytes($stream, 4);
                $length = unpack('Nlength', $lengthBytes)['length'];
                if (! is_int($length) || $length > self::MAX_PROMPT_BYTES + 1024) {
                    throw new InvalidComfyUiImage('画像のメタデータが大きすぎます。');
                }
                $type = $this->readBytes($stream, 4);
                $data = $this->readBytes($stream, $length);
                $crc = $this->readBytes($stream, 4);
                if (! hash_equals(pack('N', crc32($type.$data)), $crc)) {
                    throw new InvalidComfyUiImage('PNG画像が壊れています。');
                }

                $candidate = $this->promptFromChunk($type, $data);
                if ($candidate !== null) {
                    if ($prompt !== null) {
                        throw new InvalidComfyUiImage('画像内のプロンプト情報を一意に特定できません。');
                    }
                    $prompt = $candidate;
                }
                if ($type === 'IEND') {
                    break;
                }
            }
        } finally {
            fclose($stream);
        }

        if ($prompt === null) {
            throw new InvalidComfyUiImage('ComfyUIのプロンプト情報が見つかりません。');
        }
        if (strlen($prompt) > self::MAX_PROMPT_BYTES) {
            throw new InvalidComfyUiImage('画像のプロンプト情報が大きすぎます。');
        }

        try {
            $decoded = json_decode($prompt, true, 128, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidComfyUiImage('画像のプロンプト情報が不正です。', previous: $exception);
        }
        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new InvalidComfyUiImage('画像のプロンプト情報が不正です。');
        }

        return new ComfyUiImageMetadata($decoded);
    }

    /** @param resource $stream */
    private function readBytes($stream, int $length): string
    {
        $value = '';
        while (strlen($value) < $length && ! feof($stream)) {
            $part = fread($stream, $length - strlen($value));
            if ($part === false) {
                break;
            }
            $value .= $part;
        }
        if (strlen($value) !== $length) {
            throw new InvalidComfyUiImage('PNG画像が途中で切れています。');
        }

        return $value;
    }

    private function promptFromChunk(string $type, string $data): ?string
    {
        if ($type === 'tEXt') {
            [$keyword, $text] = $this->splitOnce($data);

            return $keyword === 'prompt' ? $text : null;
        }
        if ($type === 'zTXt') {
            [$keyword, $rest] = $this->splitOnce($data);
            if ($keyword !== 'prompt') {
                return null;
            }
            if ($rest === '' || ord($rest[0]) !== 0) {
                throw new InvalidComfyUiImage('画像の圧縮プロンプト情報が不正です。');
            }

            return $this->decompress(substr($rest, 1));
        }
        if ($type !== 'iTXt') {
            return null;
        }

        [$keyword, $rest] = $this->splitOnce($data);
        if ($keyword !== 'prompt') {
            return null;
        }
        if (strlen($rest) < 2 || ord($rest[1]) !== 0) {
            throw new InvalidComfyUiImage('画像の国際化プロンプト情報が不正です。');
        }
        $compressed = ord($rest[0]);
        [, $rest] = $this->splitOnce(substr($rest, 2));
        [, $text] = $this->splitOnce($rest);

        return match ($compressed) {
            0 => $text,
            1 => $this->decompress($text),
            default => throw new InvalidComfyUiImage('画像の圧縮方式に対応していません。'),
        };
    }

    /** @return array{string, string} */
    private function splitOnce(string $value): array
    {
        $position = strpos($value, "\0");
        if ($position === false) {
            throw new InvalidComfyUiImage('PNG画像のテキスト情報が不正です。');
        }

        return [substr($value, 0, $position), substr($value, $position + 1)];
    }

    private function decompress(string $value): string
    {
        $decoded = @gzuncompress($value, self::MAX_PROMPT_BYTES + 1);
        if (! is_string($decoded) || strlen($decoded) > self::MAX_PROMPT_BYTES) {
            throw new InvalidComfyUiImage('画像の圧縮プロンプト情報が不正または大きすぎます。');
        }

        return $decoded;
    }
}
