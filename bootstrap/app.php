<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $preservePromptContent = fn (Request $request): bool => $request->is('default-prompts/*')
            || $request->is('lora-triggers')
            || $request->is('lora-triggers/*')
            || $request->is('outfits')
            || $request->is('outfits/*')
            || $request->is('expressions')
            || $request->is('expressions/*')
            || $request->is('gazes')
            || $request->is('gazes/*')
            || $request->is('locations')
            || $request->is('locations/*')
            || $request->is('compositions')
            || $request->is('compositions/*')
            || $request->is('actions')
            || $request->is('actions/*')
            || $request->is('prompt-options')
            || $request->is('prompt-options/*');

        $middleware->trimStrings(except: [$preservePromptContent]);
        $middleware->convertEmptyStringsToNull(except: [$preservePromptContent]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
