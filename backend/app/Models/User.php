<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        // Mass assignment restringido explícitamente (RS-04): solo estos campos.
        'nombre_completo', 'ci', 'email', 'telefono', 'password',
    ];

    protected $hidden = [
        // RS-03: nunca expongas password ni secreto TOTP en JSON.
        'password', 'remember_token', 'mfa_secret', 'failed_login_attempts',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed', // usa bcrypt (config hashing.php driver=bcrypt, rounds>=12)
        'mfa_secret' => 'encrypted', // cifrado en reposo, adicional al hidden
        'mfa_enabled' => 'boolean',
        'is_blocked' => 'boolean',
        'locked_until' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function (User $user) {
            $user->uuid = $user->uuid ?? Str::uuid()->toString();
            $user->role = $user->role ?? 'USER';
        });
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'ADMIN';
    }

    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    // Ruta pública: nunca se usa el id incremental, siempre uuid (RS-01)
    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
