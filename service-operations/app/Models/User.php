<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Use the identity DB's users table via the identity connection.
     */
    protected $connection = 'identity';
    protected $table = 'oriotel1_identity.users';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'identifiant',
        'nom',
        'prenom',
        'cin',
        'phone',
        'telephone',
        'statut',
        'photo',
        'avatar',
        'adresse',
        'date_naissance',
        'daily_capacity',
        'avg_task_completion_time',
        'tasks_completed_per_day',
        'delay_rate'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Calculer le score de charge
     */
    public function getWorkloadScoreAttribute() {
        $tasks = $this->tasks()
            ->whereNotIn('status', ['COMPLETED', 'CANCELLED'])
            ->get();

        $score = 0;
        foreach ($tasks as $task) {
            $multiplier = match($task->priority) {
                'URGENT' => 5,
                'HIGH' => 3,
                'MEDIUM' => 2,
                'LOW' => 1,
                default => 1
            };
            $score += $multiplier;
        }
        return $score;
    }

    /**
     * Calculer la charge de travail quotidienne (heures utilisées)
     */
    public function getDailyWorkloadAttribute($date = null) {
        $date = $date ?? now()->toDateString();
        $tasks = $this->tasks()
            ->whereNotIn('status', ['COMPLETED', 'CANCELLED'])
            ->whereDate('due_date', $date)
            ->get();

        return $tasks->sum('estimated_duration');
    }

    /**
     * Get the user's full name.
     */
    public function getNameAttribute(): string
    {
        return trim(($this->attributes['prenom'] ?? '') . ' ' . ($this->attributes['nom'] ?? '')) ?: ($this->attributes['name'] ?? '');
    }

    /**
     * Get the user's full name (alias).
     */
    public function getFullNameAttribute(): string
    {
        return $this->name;
    }

    /**
     * Check if the user is an admin.
     */
    public function isAdmin(): bool
    {
        $role = strtolower($this->role ?? '');
        return $role === 'admin' || $role === 'administrateur';
    }

    /**
     * Get the user's attendances.
     */
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Get the user's attendance anomalies.
     */
    public function attendanceAnomalies()
    {
        return $this->hasMany(AttendanceAnomaly::class);
    }

    /**
     * Get the user's tasks.
     */
    public function tasks() {
        return $this->belongsToMany(Task::class, 'oriotel1_operations.task_user');
    }

    /**
     * Get the user's task reassignments (from).
     */
    public function taskReassignmentsFrom() {
        return $this->hasMany(TaskReassignment::class, 'from_user_id');
    }

    /**
     * Get the user's task reassignments (to).
     */
    public function taskReassignmentsTo() {
        return $this->hasMany(TaskReassignment::class, 'to_user_id');
    }

    /**
     * Override the notifications relationship to use the operations database.
     */
    public function notifications()
    {
        return $this->morphMany(DatabaseNotification::class, 'notifiable')
                    ->latest();
    }

    /**
     * Get the unread notifications.
     */
    public function unreadNotifications()
    {
        return $this->notifications()->whereNull('read_at');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_naissance' => 'date',
            'daily_capacity' => 'decimal:2',
            'avg_task_completion_time' => 'decimal:2',
            'tasks_completed_per_day' => 'integer',
            'delay_rate' => 'decimal:2'
        ];
    }
}