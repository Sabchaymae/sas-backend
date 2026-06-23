<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageReactionUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $action; // 'added' or 'removed'
    public $userId;
    public $emoji;

    /**
     * Create a new event instance.
     */
    public function __construct(Message $message, string $action, int $userId, string $emoji)
    {
        $this->message = $message;
        $this->action = $action;
        $this->userId = $userId;
        $this->emoji = $emoji;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new Channel('chat.' . $this->message->conversation_id);
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'MessageReactionUpdated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        // Load fresh reactions with users
        $this->message->load(['reactions.user']);
        
        return [
            'message_id'      => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'action'          => $this->action,
            'user_id'         => $this->userId,
            'emoji'           => $this->emoji,
            'reactor_name'    => optional(\App\Models\User::find($this->userId))->prenom
                                 . ' '
                                 . optional(\App\Models\User::find($this->userId))->nom,
            'reactions'       => $this->message->reactions->map(function($reaction) {
                return [
                    'id'         => $reaction->id,
                    'emoji'      => $reaction->emoji,
                    'user_id'    => $reaction->user_id,
                    'user'       => [
                        'id'     => $reaction->user->id,
                        'nom'    => $reaction->user->nom,
                        'prenom' => $reaction->user->prenom,
                    ],
                    'created_at' => $reaction->created_at,
                ];
            }),
        ];
    }
}
