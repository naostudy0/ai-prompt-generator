<?php

namespace App\Http\Controllers\PromptPreparation\Queries;

use App\Application\PromptPreparation\Queries\ListGenerationSettingOptions\ListGenerationSettingOptionsHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class ListGenerationSettingOptionsController extends Controller
{
    public function __invoke(int $family, ListGenerationSettingOptionsHandler $handler): JsonResponse
    {
        return response()->json(['options' => $handler->handle($family)]);
    }
}
