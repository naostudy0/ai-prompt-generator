<?php

namespace App\Domain\PromptPreparation\Models\DefaultPrompt;

final readonly class DefaultPromptText
{
    private function __construct(
        public string $value,
        public bool $formatted,
    ) {
    }

    public static function fromInput(string $input): self
    {
        if (trim($input) === '') {
            return new self('', true);
        }

        $elements = self::splitElements($input);

        if ($elements === null) {
            return new self($input, false);
        }

        $uniqueElements = [];

        foreach ($elements as $element) {
            $trimmedElement = trim($element);

            if ($trimmedElement === '' || in_array($trimmedElement, $uniqueElements, true)) {
                continue;
            }

            $uniqueElements[] = $trimmedElement;
        }

        if ($uniqueElements === []) {
            return new self('', true);
        }

        return new self(implode(', ', $uniqueElements).',', true);
    }

    /**
     * 丸括弧内のカンマと改行を保持しながら、外側の区切りで文面を分割する。
     *
     * @return list<string>|null 括弧が対応しない場合はnull
     */
    private static function splitElements(string $input): ?array
    {
        $elements = [];
        $current = '';
        $parenthesisDepth = 0;
        $escaped = false;
        $length = strlen($input);

        for ($index = 0; $index < $length; $index++) {
            $character = $input[$index];

            if ($escaped) {
                $current .= $character;
                $escaped = false;

                continue;
            }

            if ($character === '\\') {
                $current .= $character;
                $escaped = true;

                continue;
            }

            if ($character === '(') {
                $parenthesisDepth++;
                $current .= $character;

                continue;
            }

            if ($character === ')') {
                if ($parenthesisDepth === 0) {
                    return null;
                }

                $parenthesisDepth--;
                $current .= $character;

                continue;
            }

            if ($parenthesisDepth === 0 && ($character === ',' || $character === "\n" || $character === "\r")) {
                $elements[] = $current;
                $current = '';

                if ($character === "\r" && $index + 1 < $length && $input[$index + 1] === "\n") {
                    $index++;
                }

                continue;
            }

            $current .= $character;
        }

        if ($parenthesisDepth !== 0) {
            return null;
        }

        $elements[] = $current;

        return $elements;
    }
}
