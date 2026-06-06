<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Incident extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'client_name',
        'client_phone',
        'client_address',
        'city',
        'client_email',
        'incident_date',
        'is_recurring',
        'status',
        'created_by',
        'ai_site',
        'ai_equipment',
        'ai_category',
        'ai_impacted_service',
        'ai_priority',
        'ai_priority_score',
        'ai_summary'
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
