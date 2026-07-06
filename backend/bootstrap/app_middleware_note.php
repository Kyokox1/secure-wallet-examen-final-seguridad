<?php
// NOTA: registrar en bootstrap/app.php (Laravel 11+/13) dentro de ->withMiddleware(function (Middleware $middleware) {...}):
//
// $middleware->api(prepend: [\App\Http\Middleware\ForceJsonErrors::class]);
// $middleware->alias([
//     'jwt.auth' => \App\Http\Middleware\JwtAuthenticate::class,
//     'role' => \App\Http\Middleware\RoleMiddleware::class,
// ]);
//
// Y en ->withExceptions(function (Exceptions $exceptions) {...}):
// $exceptions->shouldRenderJsonWhen(fn () => true);
