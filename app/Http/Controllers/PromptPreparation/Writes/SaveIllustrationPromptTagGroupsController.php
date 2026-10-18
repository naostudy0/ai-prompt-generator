<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\SaveIllustrationPromptTagGroups\IllustrationPromptTagGroupInput;
use App\Application\PromptPreparation\Writes\SaveIllustrationPromptTagGroups\SaveIllustrationPromptTagGroupsHandler;
use App\Application\PromptPreparation\Writes\SaveIllustrationPromptTagGroups\SaveIllustrationPromptTagGroupsInput;
use App\Http\Controllers\Controller;
use App\Http\Requests\PromptPreparation\SaveIllustrationPromptTagGroupsRequest;
use Illuminate\Http\JsonResponse;

final class SaveIllustrationPromptTagGroupsController extends Controller
{
    public function __invoke(
        SaveIllustrationPromptTagGroupsRequest $request,
        SaveIllustrationPromptTagGroupsHandler $handler,
    ): JsonResponse {
        /** @var list<array{label: string, tags: string}> $groups */
        $groups = $request->validated('groups');
        $result = $handler->handle(new SaveIllustrationPromptTagGroupsInput(array_map(
            fn (array $group): IllustrationPromptTagGroupInput => new IllustrationPromptTagGroupInput(
                $this->trimWhitespace($group['label']),
                $this->trimWhitespace($group['tags']),
            ),
            $groups,
        )));

        return response()->json([
            'groupId' => $result->groupId,
            'savedCount' => $result->savedCount,
            'duplicateCount' => $result->duplicateCount,
        ]);
    }

    private function trimWhitespace(string $value): string
    {
        return preg_replace('/^\s+|\s+$/u', '', $value) ?? trim($value);
    }
}
