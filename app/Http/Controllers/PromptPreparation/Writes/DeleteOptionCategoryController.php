<?php

namespace App\Http\Controllers\PromptPreparation\Writes;

use App\Application\PromptPreparation\Writes\DeleteOptionCategory\DeleteOptionCategoryHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

final class DeleteOptionCategoryController extends Controller
{
    public function __invoke(DeleteOptionCategoryHandler $handler, int $category): Response
    {
        $handler->handle($category);

        return response()->noContent();
    }
}
