<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'tipo', 'estado', 'wallet_origen_id', 'wallet_destino_id', 'monto',
        'saldo_resultante_origen', 'saldo_resultante_destino', 'descripcion',
        'idempotency_key', 'requiere_totp', 'expira_en',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'saldo_resultante_origen' => 'decimal:2',
        'saldo_resultante_destino' => 'decimal:2',
        'requiere_totp' => 'boolean',
        'expira_en' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function (Transaction $t) {
            $t->uuid = $t->uuid ?? Str::uuid()->toString();
        });
    }

    public function walletOrigen()
    {
        return $this->belongsTo(Wallet::class, 'wallet_origen_id');
    }

    public function walletDestino()
    {
        return $this->belongsTo(Wallet::class, 'wallet_destino_id');
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
