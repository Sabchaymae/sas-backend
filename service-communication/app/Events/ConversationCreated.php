<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $conversation;
    public $forUserId;

    /**
     * Create a new event instance.
     */
    public function __construct(Conversation $conversation, int $forUserId)
    {
        $this->conversation = $conversation;
        $this->forUserId = $forUserId;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        // Broadcast sur le channel privé de l'utilisateur
        return new Channel('user.' . $this->forUserId);
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'ConversationCreated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        // Charger les relations nécessaires
        $this->conversation->load(['lastMessage', 'participants', 'participantData']);
        
        return [
            'conversation' => $this->conversation,
        ];
    }
}
