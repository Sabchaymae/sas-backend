<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $token,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
        $resetUrl    = "{$frontendUrl}/auth/reset-password?token={$this->token}&email={$notifiable->email}";

        return (new MailMessage)
            ->subject('Oriotel — Réinitialisation de mot de passe')
            ->view('emails.reset-password', [
                'user'     => $notifiable,
                'resetUrl' => $resetUrl,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'action' => 'password_reset',
        ];
    }
}
