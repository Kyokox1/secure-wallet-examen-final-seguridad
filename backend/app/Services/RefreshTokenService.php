<?php

namespace App\Services;

use App\Models\RefreshToken;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Support\Str;
use Carbon\Carbon;

// RS-07: access token corto (15 min) + refresh token con rotación y detección de reúso.
// Si un refresh token ya usado se reutiliza, se revoca TODA la familia (posible robo de token).
class RefreshTokenService
{
    public function issueAccessToken(User $user): string
    {
        $payload = [
            'sub' => $user->uuid,
            'role' => $user->role,
            'jti' => (string) Str::uuid(), // identificador único del token (para poder revocarlo individualmente)
            'iat' => time(),
            'exp' => time() + (15 * 60), // 15 minutos
        ];
        return JWT::encode($payload, config('app.jwt_secret'), 'HS256');
    }

    // RF-10: invalida el access token en el servidor al hacer logout.
    // Guardamos su 'jti' en una blacklist en caché, con TTL igual al tiempo de vida restante del token,
    // así la entrada se autolimpia sola y nunca crece indefinidamente.
    public function blacklistAccessToken(string $jti, int $exp): void
    {
        $ttlSeconds = max($exp - time(), 0);
        cache()->put('jwt_blacklist:' . $jti, true, $ttlSeconds);
    }

    public function isAccessTokenBlacklisted(string $jti): bool
    {
        return cache()->has('jwt_blacklist:' . $jti);
    }

    public function issueRefreshToken(User $user, ?string $familyId = null): array
    {
        $familyId = $familyId ?? Str::uuid()->toString();
        $plainToken = Str::random(64);

        RefreshToken::create([
            'user_id' => $user->id,
            'family_id' => $familyId,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        return ['token' => $plainToken, 'family_id' => $familyId];
    }

    // Devuelve el registro válido o null. Si detecta reuso, revoca toda la familia.
    public function validateAndRotate(string $plainToken): ?array
    {
        $hash = hash('sha256', $plainToken);
        $record = RefreshToken::where('token_hash', $hash)->first();

        if (!$record) {
            return null;
        }

        if ($record->used || $record->revoked) {
            // Reuso detectado: revocar toda la familia.
            RefreshToken::where('family_id', $record->family_id)->update(['revoked' => true]);
            return null;
        }

        if ($record->expires_at->isPast()) {
            return null;
        }

        $record->update(['used' => true]);

        $user = $record->user;
        $new = $this->issueRefreshToken($user, $record->family_id);

        return ['user' => $user, 'refresh_token' => $new['token']];
    }

    public function revokeAllForUser(User $user): void
    {
        RefreshToken::where('user_id', $user->id)->update(['revoked' => true]);
    }
}
