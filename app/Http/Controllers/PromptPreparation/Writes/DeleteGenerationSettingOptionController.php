<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\DeleteGenerationSettingOption\DeleteGenerationSettingOptionHandler;
use App\Domain\PromptPreparation\Repositories\GenerationSettingOptionInUse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class DeleteGenerationSettingOptionController extends Controller
{
    public function __invoke(int $option, DeleteGenerationSettingOptionHandler $handler): JsonResponse
    {
        try {
            $handler->handle($option);
        } catch (GenerationSettingOptionInUse) {
            return response()->json(['message' => '人物LoRAが使用中のため削除できません。'], 409);
        }

        return response()->json(status: 204);
    }
}
