<?php

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\User;

/**
 * ActivityLogPolicy
 *
 * Rules:
 *  - Administrators  → can view ALL logs, export, purge
 *  - Other roles     → can view ONLY their own logs (filtered by user_id)
 *  - No one except admins can purge logs
 */
class ActivityLogPolicy
{
    /**
     * Determine if the user can list activity logs.
     * Always true — the query scope handles user filtering.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view a specific log entry.
     */
    public function view(User $user, ActivityLog $log): bool
    {
        // Admin sees everything; others only their own
        return $user->isAdmin() || $log->user_id === $user->id;
    }

    /**
     * Determine if the user can export logs.
     * Only admins can export the full history.
     */
    public function export(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can delete/purge old logs.
     * Only admins can purge the history.
     */
    public function purge(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user can view chart/stats data.
     * Only admins can view aggregate stats across all users.
     */
    public function stats(User $user): bool
    {
        return $user->isAdmin();
    }
}
