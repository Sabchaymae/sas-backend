<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserBlocked implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int  $blocker_id;
    public int  $blocked_id;
    public bool $is_blocked; // true = blocked, false = unblocked

    public function __construct(int $blocker_id, int $blocked_id, bool $is_blocked)
    {
        $this->blocker_id = $blocker_id;
        $this->blocked_id = $blocked_id;
        $this->is_blocked = $is_blocked;
    }

    /**
     * Broadcast on the blocked user's private notification channel
     * so only they receive this event.
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("notifications.{$this->blocked_id}")];
    }

    public function broadcastAs(): string
    {
        return 'user.blocked';
    }

    public function broadcastWith(): array
    {
        return [
            'blocker_id' => $this->blocker_id,
            'blocked_id' => $this->blocked_id,
            'is_blocked' => $this->is_blocked,
        ];
    }
}
