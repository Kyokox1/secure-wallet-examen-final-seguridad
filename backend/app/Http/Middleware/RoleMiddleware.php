<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

// RS-02: RBAC verificado en el SERVIDOR. Cualquier rol distinto al exigido -> 403.
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $role)
    {
        $user = $request->attributes->get('auth_user');

        if (!$user || $user->role !== $role) {
            return response()->json(['message' => 'Prohibido: rol insuficiente.'], 403);
        }

        return $next($request);
    }
}
