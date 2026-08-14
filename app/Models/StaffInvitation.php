<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class StaffInvitation extends Model
{
    protected $fillable = [
        'seller_id',
        'email',
        'name',
        'role',
        'permissions',
        'token',
        'expires_at',
        'accepted_at',
        'staff_user_id',
    ];

    protected $casts = [
        'permissions' => 'array',
        'expires_at'  => 'datetime',
        'accepted_at' => 'datetime',
    ];

    // ─── Relationships ──────────────────────────────────────────────────────

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isPending(): bool
    {
        return ! $this->isAccepted() && ! $this->isExpired();
    }
}
