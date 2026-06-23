<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AnnouncementReactionUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int    $announcement_id;
    public array  $reactions;
    public int    $user_id;
    public string $reactor_name;
    public string $emoji;
    public string $action; // 'added' | 'changed' | 'removed'

    public function __construct(int $announcement_id, array $reactions, int $user_id, string $reactor_name, string $emoji, string $action)
    {
        $this->announcement_id = $announcement_id;
        $this->reactions       = $reactions;
        $this->user_id         = $user_id;
        $this->reactor_name    = $reactor_name;
        $this->emoji           = $emoji;
        $this->action          = $action;
    }

    public function broadcastOn(): array
    {
        return [new Channel('announcements')];
    }

    public function broadcastAs(): string
    {
        return 'announcement.reaction.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'announcement_id' => $this->announcement_id,
            'reactions'       => $this->reactions,
            'user_id'         => $this->user_id,
            'reactor_name'    => $this->reactor_name,
            'emoji'           => $this->emoji,
            'action'          => $this->action,
        ];
    }
}
