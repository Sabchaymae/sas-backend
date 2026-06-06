<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'status',
        'priority',
        'due_date',
        'attachments_count',
        'comments_count',
        'client_name',
        'client_phone',
        'client_address',
        'client_email',
        'incident_date',
        'is_recurring',
        'is_incident',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function comments()
    {
        return $this->hasMany(TaskComment::class);
    }
}
