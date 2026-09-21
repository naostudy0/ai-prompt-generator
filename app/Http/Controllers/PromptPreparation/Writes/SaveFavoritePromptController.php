<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Queries\GetFavoritePrompt\GetFavoritePromptHandler;
use App\Application\PromptPreparation\Writes\SaveFavoritePrompt\Exceptions\FavoritePromptNotFound;
use App\Application\PromptPreparation\Writes\SaveFavoritePrompt\SaveFavoritePromptHandler;
use App\Application\PromptPreparation\Writes\SaveFavoritePrompt\SaveFavoritePromptInput;
use App\Http\Controllers\Controller;
use App\Http\Requests\PromptPreparation\SaveFavoritePromptRequest;
use Illuminate\Http\JsonResponse;

final class SaveFavoritePromptController extends Controller
{
    public function __invoke(
        SaveFavoritePromptRequest $request,
        SaveFavoritePromptHandler $handler,
        GetFavoritePromptHandler $getFavorite,
        ?int $favoritePrompt = null,
    ): JsonResponse {
        $selectionSnapshot = $request->selectionSnapshot();
        $selectionSummary = $request->selectionSummary();
        if ($selectionSnapshot === null || $selectionSummary === null) {
            return response()->json(['message' => '選択状態の形式が正しくありません。'], 422);
        }
        $image = $request->file('image');
        try {
            $saved = $handler->handle(new SaveFavoritePromptInput(
                id: $favoritePrompt,
                name: $request->string('name')->toString(),
                positivePrompt: $request->string('positivePrompt')->toString(),
                negativePrompt: $request->string('negativePrompt')->toString(),
                selectionSnapshot: $selectionSnapshot,
                selectionSummary: $selectionSummary,
                imageSourcePath: $image?->getRealPath() ?: null,
                imageOriginalName: $image?->getClientOriginalName(),
                imageMimeType: $image?->getMimeType(),
                imageSize: ($imageSize = $image?->getSize()) === false ? null : $imageSize,
                imageExtension: $image?->guessExtension(),
                removeImage: $request->boolean('removeImage'),
            ));
        } catch (FavoritePromptNotFound) {
            return response()->json(['message' => 'お気に入りが見つかりません。'], 404);
        }

        return response()->json(
            $getFavorite->handle((int) $saved->id),
            $favoritePrompt === null ? 201 : 200,
        );
    }
}
