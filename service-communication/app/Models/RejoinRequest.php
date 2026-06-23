<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RejoinRequest extends Model
{
    use HasFactory;

    protected $table = 'group_rejoin_requests';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'admin_id',
        'status',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
