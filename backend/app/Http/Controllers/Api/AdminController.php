<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

// RF-09: panel admin. RS-02: acceso restringido por RoleMiddleware('ADMIN') en las rutas.
class AdminController extends Controller
{
    public function users(Request $request)
    {
        $perPage = min((int) $request->query('per_page', 20), 100);
        $users = User::select('uuid', 'nombre_completo', 'email', 'telefono', 'role', 'is_blocked', 'mfa_enabled', 'created_at')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json($users);
    }

    public function block(Request $request, string $uuid)
    {
        $request->validate(['bloquear' => ['required', 'boolean']]);

        $user = User::where('uuid', $uuid)->first();
        if (!$user) {
            return response()->json(['message' => 'No encontrado.'], 404);
        }

        $user->update(['is_blocked' => $request->boolean('bloquear')]);

        $admin = $request->attributes->get('auth_user');
        AuditLogService::log($admin->id, $request->boolean('bloquear') ? 'ACCOUNT_BLOCKED' : 'ACCOUNT_UNBLOCKED', $request, [
            'target_user' => $user->uuid,
        ]);

        return response()->json(['message' => 'Estado actualizado.', 'is_blocked' => $user->is_blocked]);
    }

    public function auditLogs(Request $request)
    {
        $perPage = min((int) $request->query('per_page', 30), 100);
        $logs = AuditLog::with('user:id,uuid,nombre_completo,email')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json($logs);
    }
}
