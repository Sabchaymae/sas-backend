<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeletedConversation extends Model
{
    protected $fillable = [
        'user_id',
        'conversation_id',
        'deleted_at',
        'is_hidden',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
        'is_hidden'  => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
