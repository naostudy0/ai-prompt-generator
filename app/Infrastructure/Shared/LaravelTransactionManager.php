<?php

namespace App\Infrastructure\Shared;

use App\Application\Shared\TransactionManager;
use Closure;
use Illuminate\Support\Facades\DB;

final class LaravelTransactionManager implements TransactionManager
{
    public function run(Closure $operation): mixed
    {
        return DB::transaction($operation);
    }
}
