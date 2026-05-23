<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogService
{
    /**
     * Record an activity log entry.
     *
     * @param  string       $type    One of ActivityLog::TYPE_* constants
     * @param  string       $action  Short description (e.g. "Connexion réussie")
     * @param  string       $module  Module name  (e.g. "Auth")
     * @param  string|null  $detail  Longer description
     * @param  array|null   $user    ['id', 'name', 'role'] – defaults to authenticated user
     * @param  array|null   $properties Extra structured data to store as JSON
     */
    public static function log(
        string  $type,
        string  $action,
        string  $module,
        ?string $detail     = null,
        ?array  $user       = null,
        ?array  $properties = null,
    ): ActivityLog {
        /** @var \App\Models\User|null $authUser */
        $authUser = auth('sanctum')->user();

        return ActivityLog::create([
            'user_id'    => $user['id']   ?? $authUser?->id,
            'user_name'  => $user['name'] ?? ($authUser ? $authUser->full_name : 'Système'),
            'user_role'  => $user['role'] ?? ($authUser?->role ?? 'system'),
            'type'       => $type,
            'action'     => $action,
            'module'     => $module,
            'detail'     => $detail,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'properties' => $properties,
        ]);
    }

    // ─── Convenience shortcuts ────────────────────────────────────────

    public static function connexion(string $action, ?string $detail = null, ?array $user = null): ActivityLog
    {
        return self::log(ActivityLog::TYPE_CONNEXION, $action, ActivityLog::MODULE_AUTH, $detail, $user);
    }

    public static function modification(string $action, string $module = ActivityLog::MODULE_USERS, ?string $detail = null): ActivityLog
    {
        return self::log(ActivityLog::TYPE_MODIFICATION, $action, $module, $detail);
    }

    public static function anomalie(string $action, ?string $detail = null, ?array $user = null): ActivityLog
    {
        return self::log(ActivityLog::TYPE_ANOMALIE, $action, ActivityLog::MODULE_SECURITY, $detail, $user);
    }

    public static function refus(string $action, ?string $detail = null, ?array $user = null): ActivityLog
    {
        return self::log(ActivityLog::TYPE_REFUS, $action, ActivityLog::MODULE_SECURITY, $detail, $user);
    }

    public static function validation(string $action, string $module = ActivityLog::MODULE_USERS, ?string $detail = null): ActivityLog
    {
        return self::log(ActivityLog::TYPE_VALIDATION, $action, $module, $detail);
    }

    public static function souscription(string $action, ?string $detail = null): ActivityLog
    {
        return self::log(ActivityLog::TYPE_SOUSCRIPTION, $action, ActivityLog::MODULE_SUBSCRIPTIONS, $detail);
    }
}
