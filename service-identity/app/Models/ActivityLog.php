<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ActivityLog extends Model
{
    // ─── Type Constants ───────────────────────────────────────────────
    const TYPE_CONNEXION    = 'connexion';
    const TYPE_CREATION     = 'creation';
    const TYPE_MODIFICATION = 'modification';
    const TYPE_SUPPRESSION   = 'suppression';
    const TYPE_SOUSCRIPTION = 'souscription';
    const TYPE_VALIDATION   = 'validation';
    const TYPE_ANOMALIE     = 'anomalie';
    const TYPE_REFUS        = 'refus';

    const ALL_TYPES = [
        self::TYPE_CONNEXION,
        self::TYPE_CREATION,
        self::TYPE_MODIFICATION,
        self::TYPE_SUPPRESSION,
        self::TYPE_SOUSCRIPTION,
        self::TYPE_VALIDATION,
        self::TYPE_ANOMALIE,
        self::TYPE_REFUS,
    ];

    // ─── Module Constants ─────────────────────────────────────────────
    const MODULE_AUTH          = 'Auth';
    const MODULE_USERS         = 'Users';
    const MODULE_SUBSCRIPTIONS = 'Subscriptions';
    const MODULE_OPERATIONS    = 'Operations';
    const MODULE_PROFILE       = 'Profile';
    const MODULE_SECURITY      = 'Security';

    protected $fillable = [
        'user_id',
        'user_name',
        'user_role',
        'type',
        'action',
        'module',
        'detail',
        'ip_address',
        'user_agent',
        'properties',
    ];

    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────

    public function scopeOfType(Builder $query, ?string $type): Builder
    {
        return $type ? $query->where('type', $type) : $query;
    }

    public function scopeOfModule(Builder $query, ?string $module): Builder
    {
        return $module ? $query->where('module', $module) : $query;
    }

    public function scopeOfUser(Builder $query, ?string $userName): Builder
    {
        return $userName ? $query->where('user_name', $userName) : $query;
    }

    public function scopeFromDate(Builder $query, ?string $date): Builder
    {
        return $date ? $query->whereDate('created_at', '>=', $date) : $query;
    }

    public function scopeToDate(Builder $query, ?string $date): Builder
    {
        return $date ? $query->whereDate('created_at', '<=', $date) : $query;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (!$term) return $query;
        return $query->where(function ($q) use ($term) {
            $q->where('action', 'like', "%{$term}%")
              ->orWhere('user_name', 'like', "%{$term}%")
              ->orWhere('detail', 'like', "%{$term}%");
        });
    }

    // ─── API Resource Format ──────────────────────────────────────────

    /**
     * Format this log entry for the frontend (matches historyData.js shape).
     */
    public function toApiArray(): array
    {
        return [
            'id'     => $this->id,
            'user'   => $this->user_name,
            'role'   => $this->user_role,
            'type'   => $this->type,
            'action' => $this->action,
            'module' => $this->module,
            'detail' => $this->detail ?? '',
            'date'   => $this->created_at->toIso8601String(),
            'ip'     => $this->ip_address ?? '–',
        ];
    }
}
