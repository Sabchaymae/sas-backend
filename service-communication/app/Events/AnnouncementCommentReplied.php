<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AnnouncementCommentReplied implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int   $announcementId;
    public int   $commentId;       // parent comment id
    public array $reply;

    public function __construct(int $announcementId, int $commentId, array $reply)
    {
        $this->announcementId = $announcementId;
        $this->commentId      = $commentId;
        $this->reply          = $reply;
    }

    public function broadcastOn(): array
    {
        return [new Channel('announcements')];
    }

    public function broadcastAs(): string
    {
        return 'announcement.comment.replied';
    }

    public function broadcastWith(): array
    {
        return [
            'announcement_id' => $this->announcementId,
            'comment_id'      => $this->commentId,
            'reply'           => $this->reply,
        ];
    }
}
