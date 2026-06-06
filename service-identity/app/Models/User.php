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
        return strtolower($this->role) === self::ROLE_ADMIN;
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
                $user->identifiant = self::generateIdentifiant($user->prenom, $user->date_naissance);
            }
        });
    }

    public static function generateIdentifiant(string $prenom, ?string $dateNaissance = null): string
    {
        $name = strtolower(preg_replace('/\s+/', '', $prenom));
        
        $birthYear = $dateNaissance ? \Illuminate\Support\Carbon::parse($dateNaissance)->year : now()->year;
        
        $pattern = '/^.+X(\d+)@.+$/';
        
        $lastUser = self::withTrashed()
            ->where('identifiant', 'LIKE', '%X%@%')
            ->orderByRaw('CAST(SUBSTRING(identifiant, LOCATE("X", identifiant) + 1, LOCATE("@", identifiant) - LOCATE("X", identifiant) - 1) AS UNSIGNED) DESC')
            ->first();
        
        $nextNumber = 1;
        if ($lastUser && preg_match($pattern, $lastUser->identifiant, $matches)) {
            $nextNumber = (int)$matches[1] + 1;
        }
        
        $numberPart = str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
        
        return $name . 'X' . $numberPart . '@' . $birthYear;
    }

    public static function generateTemporaryPassword(): string
    {
        $length = rand(8, 12);
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $password;
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
        if ($this->permissions()->where('slug', $permissionSlug)->exists()) {
            return true;
        }
        return $this->roles()->whereHas('permissions', function ($query) use ($permissionSlug) {
            $query->where('slug', $permissionSlug);
        })->exists();
    }

    public function getNameAttribute(): string
    {
        return "{$this->prenom} {$this->nom}";
    }

    protected $appends = ['name', 'full_name', 'status', 'first_name', 'last_name'];

    public function scopeSearch($query, ?string $term): \Illuminate\Database\Eloquent\Builder
    {
        if (!$term) {
            return $query;
        }
        return $query->where(function ($q) use ($term) {
            $q->where('nom', 'like', "%{$term}%")
              ->orWhere('prenom', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%")
              ->orWhere('cin', 'like', "%{$term}%")
              ->orWhere('identifiant', 'like', "%{$term}%");
        });
    }

    public function scopeOfRole($query, ?string $role): \Illuminate\Database\Eloquent\Builder
    {
        return $role ? $query->where('role', $role) : $query;
    }

    public function scopeOfStatus($query, ?string $statut): \Illuminate\Database\Eloquent\Builder
    {
        return $statut ? $query->where('statut', $statut) : $query;
    }

    public function scopeOfRoleType($query, ?string $roleType): \Illuminate\Database\Eloquent\Builder
    {
        return $roleType ? $query->where('role_type', $roleType) : $query;
    }

    public function scopeFromDate($query, ?string $date): \Illuminate\Database\Eloquent\Builder
    {
        return $date ? $query->whereDate('created_at', '>=', $date) : $query;
    }

    public function scopeToDate($query, ?string $date): \Illuminate\Database\Eloquent\Builder
    {
        return $date ? $query->whereDate('created_at', '<=', $date) : $query;
    }
}
