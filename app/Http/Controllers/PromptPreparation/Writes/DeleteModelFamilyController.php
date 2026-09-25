<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\DeleteModelFamily\DeleteModelFamilyHandler;
use App\Domain\PromptPreparation\Repositories\ModelFamilyDeletionDenied;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class DeleteModelFamilyController extends Controller
{
    public function __invoke(int $family, DeleteModelFamilyHandler $handler): JsonResponse
    {
        try {
            $handler->handle($family);
        } catch (ModelFamilyDeletionDenied) {
            return response()->json(['message' => '使用中または初期の系統は削除できません。'], 409);
        }

        return response()->json(null, 204);
    }
}
