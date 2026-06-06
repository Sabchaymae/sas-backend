<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    const ROLE_ADMIN = 'administrateur';
    const ROLE_ANIMATEUR = 'animateur';
    const ROLE_AGENCE = 'agence';
    const ROLE_OPERATEUR = 'operateur';
    const ROLE_ASSISTANT = 'assistant';


    // Status Constants
    const STATUS_ACTIVE = 'active';
    const STATUS_PENDING = 'pending';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_REJECTED = 'rejected';

    // Type Constants
    const TYPE_INTERNE = 'interne';
    const TYPE_EXTERNE = 'externe';

    // Security
    const MAX_LOGIN_ATTEMPTS = 5;


    const INTERNAL_ROLES = ['administrateur', 'animateur', 'gestionnaire'];

    // Roles allowed to self-register via /auth/register (admins are created internally)
    const REGISTERABLE_ROLES = ['animateur', 'agence', 'operateur', 'assistant'];


    protected $fillable = [
        'identifiant',
        'nom',
        'prenom',
        'first_name', // Alias
        'last_name',  // Alias
        'email',
        'cin',
        'phone',
        'telephone',
        'email_verified_at',
        'password',
        'role',
        'role_type',
        'statut',
        'status',
        'photo',
        'avatar',
        'adresse',
        'access_reason',
        'date_naissance',
        'two_factor_enabled',
        'login_attempts',
        'locked_until',
        'must_change_password',
        'password_changed_at',
        'last_login_at',
        'last_login_ip',
        'two_factor_code',
        'two_factor_expires_at',
        'two_factor_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_enabled' => 'boolean',
            'must_change_password' => 'boolean',
            'locked_until' => 'datetime',
            'date_naissance' => 'date',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_expires_at' => 'datetime',
            'two_factor_verified_at' => 'datetime',
        ];

    }

    // ─── MAPPING (Compatibility with first_name / last_name) ───────

    public function getFirstNameAttribute()
    {
        return $this->attributes['prenom'] ?? '';
    }
    public function setFirstNameAttribute($value)
    {
        $this->attributes['prenom'] = $value;
    }

    public function getLastNameAttribute()
    {
        return $this->attributes['nom'] ?? '';
    }
    public function setLastNameAttribute($value)
    {
        $this->attributes['nom'] = $value;
    }

    public function getStatusAttribute()
    {
        return $this->attributes['statut'] ?? '';
    }
    public function setStatusAttribute($value)
    {
        $this->attributes['statut'] = $value;
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->prenom} {$this->nom}";
    }

    // ─── AUTH & SECURITY METHODS ────────────────────────────────────

    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    public function isAdmin(): bool
    {
        $role = strtolower($this->role ?? '');
        return $role === self::ROLE_ADMIN || $role === 'admin';
    }

    public function isActive(): bool
    {
        return $this->statut === self::STATUS_ACTIVE;
    }

    public function incrementLoginAttempts(): void
    {
        $this->increment('login_attempts');

        if ($this->login_attempts >= self::MAX_LOGIN_ATTEMPTS) {
            $this->update([
                'locked_until' => now()->addMinutes(30)
            ]);
        }
    }

    public function resetLoginAttempts(): void
    {
        $this->update([
            'login_attempts' => 0,
            'locked_until' => null
        ]);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return (bool) $this->two_factor_enabled;
    }

    public function getPreferredTwoFactorChannel(): string
    {
        return 'email';
    }

    // ─── GENERATORS ────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (!$user->identifiant) {
                $user->identifiant = self::generateIdentifiant($user->nom, $user->date_naissance);
            }
        });
    }

    public static function generateIdentifiant(string $nom, ?string $dateNaissance): string
    {
        // Premier deux lettres du nom en minuscule
        $prefix = strtolower(substr(str_replace(' ', '', $nom), 0, 2));
        
        // Numéro séquentiel (ex: 001)
        $lastUser = self::withTrashed()->orderBy('id', 'desc')->first();
        $nextId = $lastUser ? $lastUser->id + 1 : 1;
        $number = str_pad($nextId, 3, '0', STR_PAD_LEFT);
        
        // Année de naissance
        $year = $dateNaissance ? date('Y', strtotime($dateNaissance)) : date('Y');

        return "{$prefix}X{$number}@{$year}";
    }

    // ─── RBAC Relationships ──────────────────────────────────────────

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_has_roles');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_has_permissions');
    }

    public function hasPermission(string $permissionSlug): bool
    {
        // 1. Individual overrides
        if ($this->permissions()->where('slug', $permissionSlug)->exists()) {
            return true;
        }

        // 2. Roles from pivot table
        if ($this->roles()->whereHas('permissions', function ($query) use ($permissionSlug) {
            $query->where('slug', $permissionSlug);
        })->exists()) {
            return true;
        }

        // 3. Role from direct column (slugified)
        if ($this->role) {
            $roleSlug = \Illuminate\Support\Str::slug($this->role);
            return Role::where('slug', $roleSlug)->whereHas('permissions', function ($query) use ($permissionSlug) {
                $query->where('slug', $permissionSlug);
            })->exists();
        }

        return false;
    }

    /**
     * Check if user has permission for a specific module and action.
     * Enforces inheritance: having higher actions grants 'Lecture' automatically.
     */
    public function hasModulePermission(string $module, string $action): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $permissionSlug = \Illuminate\Support\Str::slug($module . ' ' . $action, '.');
        if ($this->hasPermission($permissionSlug)) {
            return true;
        }

        // If checking for 'Lecture' (READ), any higher permission inherits it.
        if (strtolower($action) === 'lecture') {
            $higherActions = ['Création', 'Modification', 'Suppression', 'Validation', 'Export'];
            foreach ($higherActions as $higherAction) {
                $slug = \Illuminate\Support\Str::slug($module . ' ' . $higherAction, '.');
                if ($this->hasPermission($slug)) {
                    return true;
                }
            }
        }

        return false;
    }
}
