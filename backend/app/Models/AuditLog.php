<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Bitácora de auditoría: solo INSERT, nunca UPDATE/DELETE desde la app (inmutable).
class AuditLog extends Model
{
    const UPDATED_AT = null;
    public $timestamps = false;

    protected $fillable = ['user_id', 'evento', 'ip_address', 'user_agent', 'metadata'];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
