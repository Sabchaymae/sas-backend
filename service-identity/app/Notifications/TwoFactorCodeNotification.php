<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TwoFactorCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $code,
        protected ?string $firstName = null,
        protected ?string $lastName = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Fallback names if not passed to constructor (for existing users login)
        $firstName = $this->firstName ?? ($notifiable->first_name ?? 'Utilisateur');
        $lastName  = $this->lastName  ?? ($notifiable->last_name  ?? '');

        return (new MailMessage)
            ->subject('Oriotel — Code de vérification')
            ->view('emails.two-factor-code', [
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'code'       => $this->code,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'code'    => $this->code,
            'channel' => 'email',
        ];
    }
}
