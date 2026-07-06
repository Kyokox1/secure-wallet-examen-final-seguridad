<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = []; // no mass assignment directo; se crea vía servicio

    protected $casts = [
        'saldo' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::creating(function (Wallet $wallet) {
            $wallet->uuid = $wallet->uuid ?? Str::uuid()->toString();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
