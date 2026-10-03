<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\SaveGenerationSettingOption\SaveGenerationSettingOptionHandler;
use App\Domain\PromptPreparation\Models\GenerationSettingKind;
use App\Domain\PromptPreparation\Repositories\DuplicateGenerationSettingOption;
use App\Domain\PromptPreparation\Repositories\InvalidGenerationSettingSelection;
use App\Http\Controllers\Controller;
use App\Http\Requests\PromptPreparation\SaveGenerationSettingOptionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

final class SaveGenerationSettingOptionController extends Controller
{
    public function __invoke(
        SaveGenerationSettingOptionRequest $request,
        SaveGenerationSettingOptionHandler $handler,
    ): JsonResponse {
        $familyRouteValue = $request->route('family');
        $optionRouteValue = $request->route('option');
        $family = is_numeric($familyRouteValue) ? (int) $familyRouteValue : null;
        $option = is_numeric($optionRouteValue) ? (int) $optionRouteValue : null;
        try {
            $result = $handler->handle(
                $option,
                $family ?? $request->integer('modelFamilyId'),
                GenerationSettingKind::from($request->string('kind')->toString()),
                $request->string('value')->toString(),
            );
        } catch (DuplicateGenerationSettingOption) {
            throw ValidationException::withMessages(['value' => ['同じ設定値がすでに登録されています。']]);
        } catch (InvalidGenerationSettingSelection) {
            throw ValidationException::withMessages(['option' => ['設定の系統または種類は変更できません。']]);
        }

        return response()->json($result, $option === null ? 201 : 200);
    }
}
