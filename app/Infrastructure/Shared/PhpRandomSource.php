<?php

namespace App\Infrastructure\Shared;

use App\Application\PromptPreparation\Ports\RandomSource;

final class PhpRandomSource implements RandomSource
{
    /**
     * @template T
     * @param  list<T>  $candidates
     * @return T|null
     */
    public function pick(array $candidates): mixed
    {
        return $candidates === [] ? null : $candidates[random_int(0, count($candidates) - 1)];
    }
}
