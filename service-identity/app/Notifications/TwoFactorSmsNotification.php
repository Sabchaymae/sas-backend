<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\VonageMessage;
use Illuminate\Notifications\Notification;

class TwoFactorSmsNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $code,
    ) {}

    public function via(object $notifiable): array
    {
        return ['vonage'];
    }

    public function toVonage(object $notifiable): VonageMessage
    {
        return (new VonageMessage)
            ->content("Oriotel — Votre code de vérification est : {$this->code}. Il expire dans 10 minutes.");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'code'    => $this->code,
            'channel' => 'sms',
        ];
    }
}
