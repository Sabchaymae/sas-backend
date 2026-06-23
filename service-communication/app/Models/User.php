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
<<<<<<< HEAD
     * Use the identity DB's users table via the identity connection.
     */
    protected $connection = 'identity';
    protected $table = 'oriotel1_identity.users';
=======
     * Use the identity DB's users table directly since they share the same MySQL instance.
     */
    protected $table = 'oriotel_identity.users';
>>>>>>> import/master

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
<<<<<<< HEAD
        // Explicitly point to the communication database for the pivot table
        $communicationDb = config('database.connections.mysql.database');
        return $this->belongsToMany(Conversation::class, "$communicationDb.conversation_participants")
=======
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
>>>>>>> import/master
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

