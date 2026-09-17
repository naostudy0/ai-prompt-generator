<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\DeleteLoraTrigger\DeleteLoraTriggerHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

final class DeleteLoraTriggerController extends Controller
{
    public function __invoke(int $trigger, DeleteLoraTriggerHandler $handler): Response
    {
        $handler->handle($trigger);

        return response()->noContent();
    }
}
