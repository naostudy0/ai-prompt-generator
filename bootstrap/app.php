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
            || $request->is('outfits/*');

        $middleware->trimStrings(except: [$preservePromptContent]);
        $middleware->convertEmptyStringsToNull(except: [$preservePromptContent]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
