<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnnouncementComment extends Model
{
    protected $fillable = ['announcement_id', 'parent_id', 'user_id', 'user_name', 'body'];

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    /** Direct parent comment (null for top-level) */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(AnnouncementComment::class, 'parent_id');
    }

    /** Direct replies to this comment */
    public function replies(): HasMany
    {
        return $this->hasMany(AnnouncementComment::class, 'parent_id')->orderBy('created_at');
    }
}
