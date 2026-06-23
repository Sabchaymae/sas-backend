<?php

namespace App\Notifications;

use App\Models\GroupInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class GroupInvitationNotification extends Notification implements ShouldBroadcastNow
{
    use Queueable;

    public $invitation;

    /**
     * Create a new notification instance.
     */
    public function __construct(GroupInvitation $invitation)
    {
        $this->invitation = $invitation->load(['conversation', 'inviter']);
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['broadcast', 'database'];
    }

    /**
     * Get the broadcast representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'type' => 'group_invitation',
            'invitation_id' => $this->invitation->id,
            'conversation_id' => $this->invitation->conversation_id,
            'conversation_name' => $this->invitation->conversation->name,
            'inviter_id' => $this->invitation->invited_by,
            'inviter_name' => $this->invitation->inviter->prenom . ' ' . $this->invitation->inviter->nom,
            'status' => $this->invitation->status,
            'content' => "Vous avez été invité à rejoindre le groupe " . $this->invitation->conversation->name,
            'created_at' => $this->invitation->created_at,
        ]);
    }

    /**
<<<<<<< HEAD
=======
     * Override the broadcast event name so the frontend receives a clean type.
     */
    public function broadcastType(): string
    {
        return 'group_invitation';
    }

    /**
>>>>>>> import/master
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'group_invitation',
            'invitation_id' => $this->invitation->id,
            'conversation_id' => $this->invitation->conversation_id,
            'conversation_name' => $this->invitation->conversation->name,
            'inviter_id' => $this->invitation->invited_by,
            'inviter_name' => $this->invitation->inviter->prenom . ' ' . $this->invitation->inviter->nom,
            'content' => "Vous avez été invité à rejoindre le groupe " . $this->invitation->conversation->name,
        ];
    }
}
