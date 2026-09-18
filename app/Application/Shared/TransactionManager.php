<?php

namespace App\Application\Shared;

use Closure;

interface TransactionManager
{
    /**
     * @template TResult
     * @param Closure(): TResult $operation
     * @return TResult
     */
    public function run(Closure $operation): mixed;
}
