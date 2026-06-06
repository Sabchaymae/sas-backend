<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncidentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'client_name' => $this->client_name,
            'client_phone' => $this->client_phone,
            'client_address' => $this->client_address,
            'city' => $this->city,
            'client_email' => $this->client_email,
            'incident_date' => $this->incident_date,
            'is_recurring' => (bool)$this->is_recurring,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'ai_analysis' => [
                'site' => $this->ai_site,
                'equipment' => $this->ai_equipment,
                'category' => $this->ai_category,
                'impacted_service' => $this->ai_impacted_service,
                'priority' => $this->ai_priority,
                'priority_score' => $this->ai_priority_score,
                'summary' => $this->ai_summary,
            ],
            'similar_incidents_count' => \DB::table('incident_similarities')
                ->where('incident_id', $this->id)
                ->orWhere('similar_incident_id', $this->id)
                ->count(),
            'creator' => $this->whenLoaded('creator', function() {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                ];
            }),
        ];
    }
}
