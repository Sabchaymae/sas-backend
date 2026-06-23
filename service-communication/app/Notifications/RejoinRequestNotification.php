<?php

namespace App\Notifications;

use App\Models\RejoinRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class RejoinRequestNotification extends Notification implements ShouldBroadcastNow
{
    use Queueable;

    public function __construct(public RejoinRequest $request) {}

    public function via(object $notifiable): array
    {
        return ['broadcast', 'database'];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $user = $this->request->user;
        $convName = $this->request->conversation?->name ?? 'Groupe';
        return new BroadcastMessage([
            'type'             => 'rejoin_request',
            'request_id'       => $this->request->id,
            'conversation_id'  => $this->request->conversation_id,
            'conversation_name'=> $convName,
            'user_id'          => $this->request->user_id,
            'user_name'        => $user ? trim("{$user->prenom} {$user->nom}") : 'Utilisateur',
            'status'           => $this->request->status,
            'created_at'       => $this->request->created_at,
        ]);
    }

    public function broadcastType(): string
    {
        return 'rejoin_request';
    }

    public function toArray(object $notifiable): array
    {
        $user = $this->request->user;
        $convName = $this->request->conversation?->name ?? 'Groupe';
        return [
            'type'             => 'rejoin_request',
            'request_id'       => $this->request->id,
            'conversation_id'  => $this->request->conversation_id,
            'conversation_name'=> $convName,
            'user_id'          => $this->request->user_id,
            'user_name'        => $user ? trim("{$user->prenom} {$user->nom}") : 'Utilisateur',
            'status'           => $this->request->status,
        ];
    }
}
