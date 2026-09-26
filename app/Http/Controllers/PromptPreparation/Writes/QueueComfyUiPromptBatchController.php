<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\QueueComfyUiPromptBatch\QueueComfyUiPromptBatchHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\PromptPreparation\QueueComfyUiPromptBatchRequest;
use Illuminate\Http\JsonResponse;

final class QueueComfyUiPromptBatchController extends Controller
{
    public function __invoke(QueueComfyUiPromptBatchRequest $request, QueueComfyUiPromptBatchHandler $handler): JsonResponse
    {
        /** @var list<array{candidateKey: string, modelFamilyId: int, positive: string, negative: string}> $items */
        $items = array_map(static fn (array $item): array => [
            ...$item,
            'positive' => (string) ($item['positive'] ?? ''),
            'negative' => (string) ($item['negative'] ?? ''),
        ], $request->validated('items'));

        return response()->json(['results' => $handler->handle($items)]);
    }
}
