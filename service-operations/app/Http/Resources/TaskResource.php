<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'due_date' => $this->due_date ? $this->due_date->format('Y-m-d') : null,
            'attachments_count' => $this->attachments_count,
            'comments_count' => $this->comments_count,
            'estimated_duration' => $this->estimated_duration,
            'assigned_at' => $this->assigned_at ? $this->assigned_at->format('Y-m-d H:i') : null,
            'is_timeout' => $this->is_timeout,
            'users' => $this->users->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar' => "https://ui-avatars.com/api/?name=" . urlencode($user->name) . "&background=random",
                ];
            }),
            'comments' => $this->comments->map(function($comment) {
                return [
                    'id' => $comment->id,
                    'content' => $comment->content,
                    'type' => $comment->type,
                    'user' => $comment->user ? [
                        'id' => $comment->user->id,
                        'name' => $comment->user->name,
                    ] : [
                        'id' => null,
                        'name' => 'Utilisateur inconnu',
                    ],
                    'created_at' => $comment->created_at,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
