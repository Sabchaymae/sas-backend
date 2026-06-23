<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    protected $fillable = [
        'author_id',
        'author_name',
        'title',
        'body',
        'type',
        'color',
        'pinned',
        'expires_at',
    ];

    protected $casts = [
        'pinned'     => 'boolean',
        'expires_at' => 'datetime',
    ];

    // ── Scopes ──────────────────────────────────────────────────────────────

    /** Only announcements that haven't expired yet. */
    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now());
    }

    /** Most recent first, pinned on top. */
    public function scopeOrdered($query)
    {
        return $query->orderByDesc('pinned')->orderByDesc('created_at');
    }

    // ── Relations ───────────────────────────────────────────────────────────

    public function reactions(): HasMany
    {
        return $this->hasMany(AnnouncementReaction::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(AnnouncementComment::class)->orderBy('created_at');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
