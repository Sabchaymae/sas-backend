<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * Use the identity DB's users table directly since they share the same MySQL instance.
     */
    protected $table = 'oriotel_identity.users';

    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'password',
    ];

    protected $appends = ['name'];

    public function getNameAttribute()
    {
        return "{$this->prenom} {$this->nom}";
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function conversations()
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
            ->withPivot(['role', 'last_read_at'])
            ->withTimestamps();
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function blockedUsers()
    {
        return $this->hasMany(BlockedUser::class, 'blocker_id');
    }

    /**
     * Specifies the user's channel name for broadcast notifications.
     */
    public function receivesBroadcastNotificationsOn(): string
    {
        return 'notifications.' . $this->id;
    }
}

