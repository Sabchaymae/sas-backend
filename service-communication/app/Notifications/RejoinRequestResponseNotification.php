<?php

namespace App\Notifications;

use App\Models\RejoinRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class RejoinRequestResponseNotification extends Notification implements ShouldBroadcastNow
{
    use Queueable;

    public function __construct(public RejoinRequest $request) {}

    public function via(object $notifiable): array
    {
        return ['broadcast', 'database'];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $convName = $this->request->conversation?->name ?? 'Groupe';
        $adminName = $this->request->admin
            ? trim("{$this->request->admin->prenom} {$this->request->admin->nom}")
            : '';
        return new BroadcastMessage([
            'type'             => 'rejoin_response',
            'request_id'       => $this->request->id,
            'conversation_id'  => $this->request->conversation_id,
            'conversation_name'=> $convName,
            'status'           => $this->request->status,
            'admin_name'       => $adminName,
        ]);
    }

    public function broadcastType(): string
    {
        return 'rejoin_response';
    }

    public function toArray(object $notifiable): array
    {
        $convName = $this->request->conversation?->name ?? 'Groupe';
        $adminName = $this->request->admin
            ? trim("{$this->request->admin->prenom} {$this->request->admin->nom}")
            : '';
        return [
            'type'             => 'rejoin_response',
            'request_id'       => $this->request->id,
            'conversation_id'  => $this->request->conversation_id,
            'conversation_name'=> $convName,
            'status'           => $this->request->status,
            'admin_name'       => $adminName,
        ];
    }
}
