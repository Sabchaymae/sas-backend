<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ActivityLogResource
 *
 * Transforms a single ActivityLog Eloquent model into the JSON shape
 * expected by the Oriotel ERP frontend historique page.
 *
 * Output shape:
 * {
 *   "id":     1,
 *   "user":   "Admin Oriotel",
 *   "role":   "administrateur",
 *   "type":   "connexion",
 *   "action": "Connexion réussie",
 *   "module": "Auth",
 *   "detail": "...",
 *   "date":   "2026-05-07T09:00:00+01:00",
 *   "ip":     "192.168.1.1",
 *   "user_id": 1
 * }
 */
class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'      => $this->id,
            'user'    => $this->user_name,
            'role'    => $this->user_role,
            'type'    => $this->type,
            'action'  => $this->action,
            'module'  => $this->module,
            'detail'  => $this->detail ?? '',
            'date'    => $this->created_at->toIso8601String(),
            'ip'      => $this->ip_address ?? '–',
            'user_id' => $this->user_id,

            // Include properties only when needed (detail view)
            'properties' => $this->when(
                $request->routeIs('*.history.show'),
                $this->properties
            ),
            'user_agent' => $this->when(
                $request->routeIs('*.history.show'),
                $this->user_agent
            ),
        ];
    }
}
