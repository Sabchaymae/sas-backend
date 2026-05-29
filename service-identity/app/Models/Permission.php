<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;


class Permission extends Model
{
    use HasFactory;


    protected $fillable = [
        'name',
        'slug',
        'module',
        'action',
    ];

    /**
     * The roles that belong to the permission.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_has_permissions');
    }

    /**
     * The users that have been assigned the permission individually.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_has_permissions');

    }
}
