<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\RefreshTokenService;
use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Illuminate\Http\Request;

// Middleware de autenticación JWT propio (access token de vida corta, RS-07).
class JwtAuthenticate
{
    public function __construct(protected RefreshTokenService $refreshTokenService) {}

    public function handle(Request $request, Closure $next)
    {
        $header = $request->header('Authorization', '');

        if (!str_starts_with($header, 'Bearer ')) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $token = substr($header, 7);

        try {
            $decoded = JWT::decode($token, new Key(config('app.jwt_secret'), 'HS256'));
        } catch (ExpiredException $e) {
            return response()->json(['message' => 'Token expirado.'], 401);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Token inválido.'], 401);
        }

        // RF-10: rechazar tokens revocados explícitamente (logout) aunque aún no hayan expirado.
        if (isset($decoded->jti) && $this->refreshTokenService->isAccessTokenBlacklisted($decoded->jti)) {
            return response()->json(['message' => 'Token revocado. Inicie sesión nuevamente.'], 401);
        }

        $user = User::where('uuid', $decoded->sub)->first();

        if (!$user || $user->is_blocked) {
            return response()->json(['message' => 'Cuenta no disponible.'], 401);
        }

        $request->setUserResolver(fn () => $user);
        $request->attributes->set('auth_user', $user);
        $request->attributes->set('jwt_payload', $decoded); // disponible por si otro controlador necesita jti/exp

        return $next($request);
    }
}
