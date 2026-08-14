<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            \App\Http\Middleware\SetLocale::class,
        ]);

        $middleware->alias([
            'isUsersAdmin' => \App\Http\Middleware\IsUsersAdmin::class,
            'isOrdersAdmin' => \App\Http\Middleware\IsOrdersAdmin::class,
            'isProductsAdmin' => \App\Http\Middleware\IsProductsAdmin::class,
            'super_admin' => \App\Http\Middleware\IsSuperAdmin::class

        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
