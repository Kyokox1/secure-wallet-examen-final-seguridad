<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefreshToken extends Model
{
    protected $fillable = ['user_id', 'family_id', 'token_hash', 'revoked', 'used', 'expires_at'];

    protected $casts = [
        'revoked' => 'boolean',
        'used' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
