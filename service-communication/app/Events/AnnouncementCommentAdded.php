<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AnnouncementCommentAdded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int   $announcementId;
    public array $comment;

    public function __construct(int $announcementId, array $comment)
    {
        $this->announcementId = $announcementId;
        $this->comment        = $comment;
    }

    public function broadcastOn(): array
    {
        return [new Channel('announcements')];
    }

    public function broadcastAs(): string
    {
        return 'announcement.comment.added';
    }

    public function broadcastWith(): array
    {
        return [
            'announcement_id' => $this->announcementId,
            'comment'         => $this->comment,
        ];
    }
}
