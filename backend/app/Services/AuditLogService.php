<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogService
{
    // RS-09: registra login, MFA, bloqueos, recargas y transferencias con IP y user-agent.
    public static function log(?int $userId, string $evento, Request $request, array $metadata = []): void
    {
        AuditLog::create([
            'user_id' => $userId,
            'evento' => $evento,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'metadata' => $metadata,
        ]);
    }
}
