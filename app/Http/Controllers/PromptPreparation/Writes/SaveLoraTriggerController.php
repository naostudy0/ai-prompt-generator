<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\SaveLoraTrigger\SaveLoraTriggerHandler;
use App\Application\PromptPreparation\Writes\SaveLoraTrigger\SaveLoraTriggerInput;
use App\Http\Controllers\Controller;
use App\Http\Requests\PromptPreparation\SaveLoraTriggerRequest;
use Illuminate\Http\JsonResponse;

final class SaveLoraTriggerController extends Controller
{
    public function __invoke(SaveLoraTriggerRequest $request, SaveLoraTriggerHandler $handler, ?int $trigger = null): JsonResponse
    {
        $result = $handler->handle(new SaveLoraTriggerInput(
            id: $trigger,
            loraId: $request->integer('loraId'),
            name: $request->string('name')->toString(),
            content: $request->string('content')->toString(),
        ));

        return response()->json($result, $trigger === null ? 201 : 200);
    }
}
