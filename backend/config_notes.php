<?php
// Agregar en config/app.php, dentro del array retornado:
//     'jwt_secret' => env('JWT_SECRET'),
//
// Agregar en config/services.php:
//     'recaptcha' => [
//         'site_key' => env('RECAPTCHA_SITE_KEY'),
//         'secret' => env('RECAPTCHA_SECRET'),
//     ],
//
// Agregar/editar config/cors.php:
//     'paths' => ['api/*'],
//     'allowed_methods' => ['*'],
//     'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:5173')], // RS-06: nunca '*'
//     'allowed_headers' => ['*'],
//     'supports_credentials' => false,
