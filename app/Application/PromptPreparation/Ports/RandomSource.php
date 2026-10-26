<?php

namespace App\Application\PromptPreparation\Ports;

interface RandomSource
{
    /**
     * @template T
     * @param  list<T>  $candidates
     * @return T|null
     */
    public function pick(array $candidates): mixed;
}
