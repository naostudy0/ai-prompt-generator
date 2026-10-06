<?php

namespace App\Http\Controllers\PromptPreparation\Queries;

use App\Application\PromptPreparation\Queries\ListOptionCategories\ListOptionCategoriesHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class ListOptionCategoriesController extends Controller
{
    public function __invoke(ListOptionCategoriesHandler $handler): JsonResponse
    {
        return response()->json(['categories' => $handler->handle()]);
    }
}
