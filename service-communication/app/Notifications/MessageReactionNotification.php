<?php

namespace App\Notifications;

use App\Models\Message;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MessageReactionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Message $message,
        public readonly User    $reactor,
        public readonly string  $emoji,
        public readonly string  $action, // 'added' | 'updated'
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        $preview = $this->message->content
            ? (mb_strlen($this->message->content) > 40
                ? mb_substr($this->message->content, 0, 40) . '…'
                : $this->message->content)
            : '📎 Fichier';

        return [
            'type'            => 'message_reaction',
            'emoji'           => $this->emoji,
            'action'          => $this->action,
            'reactor_id'      => $this->reactor->id,
            'reactor_name'    => trim("{$this->reactor->prenom} {$this->reactor->nom}"),
            'message_id'      => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'message_preview' => $preview,
        ];
    }

    public function toBroadcast(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
