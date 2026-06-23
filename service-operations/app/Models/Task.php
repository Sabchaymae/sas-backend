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
        'estimated_duration',
        'assigned_at'
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'due_date' => 'date',
        'estimated_duration' => 'decimal:2'
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'oriotel1_operations.task_user');
    }

    public function comments()
    {
        return $this->hasMany(TaskComment::class);
    }

    public function reassignments()
    {
        return $this->hasMany(TaskReassignment::class);
    }

    // Vérifier si la tâche a dépassé le timeout 10s (pour test)
    public function getIsTimeoutAttribute() {
        if ($this->priority !== 'URGENT' || !$this->assigned_at) return false;
        if (in_array($this->status, ['IN_PROGRESS', 'COMPLETED', 'BLOCKED'])) return false;

        return $this->assigned_at->diffInSeconds(now()) >= 10;
    }
}
