<?php

namespace App\Http\Controllers\PromptPreparation\Queries;

use App\Application\PromptPreparation\Queries\GetFavoritePrompt\GetFavoritePromptHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class GetFavoritePromptController extends Controller
{
    public function __invoke(int $favoritePrompt, GetFavoritePromptHandler $handler): JsonResponse
    {
        $favorite = $handler->handle($favoritePrompt);

        return $favorite === null
            ? response()->json(['message' => 'お気に入りが見つかりません。'], 404)
            : response()->json($favorite);
    }
}
