<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;


class UserTyping implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $conversationId;
    public $userId;
    public $userName;
    public $isTyping;

    public function __construct($conversationId, $userId, $isTyping = true)
    {
        $this->conversationId = $conversationId;
        $this->userId = $userId;
        $this->isTyping = $isTyping;
        $user = User::find($userId);
        $this->userName = $user?->name ?? '';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.' . $this->conversationId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'user.typing';
    }
    
    public function broadcastWith(): array
    {
        return [
            'userId' => $this->userId,
            'userName' => $this->userName,
            'isTyping' => $this->isTyping,
        ];
    }
}
