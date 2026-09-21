<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\DeleteFavoritePrompt\DeleteFavoritePromptHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class DeleteFavoritePromptController extends Controller
{
    public function __invoke(int $favoritePrompt, DeleteFavoritePromptHandler $handler): JsonResponse
    {
        return $handler->handle($favoritePrompt)
            ? response()->json(status: 204)
            : response()->json(['message' => 'お気に入りが見つかりません。'], 404);
    }
}
