<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use App\Notifications\TwoFactorCodeNotification;
use App\Notifications\TwoFactorSmsNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class TwoFactorService
{
    /**
     * Generate a 6-digit 2FA code and persist it to the user.
     */
    public function generateCode(User $user): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->update([
            'two_factor_code'       => $code,
            'two_factor_expires_at' => now()->addMinutes(config('two_factor.code_ttl', 15)),
            'two_factor_verified_at'=> null,
        ]);

        return $code;
    }

    /**
     * Send the 2FA code via the user's preferred channel.
     */
    public function sendCode(User $user): void
    {
        $code    = $this->generateCode($user);
        $channel = $user->getPreferredTwoFactorChannel();

        if ($channel === config('two_factor.channels.sms', 'sms') && $user->phone) {
            $user->notify(new TwoFactorSmsNotification($code));
        } else {
            $user->notify(new TwoFactorCodeNotification($code));
        }
    }

    /**
     * Verify the submitted 2FA code.
     *
     * @return array{valid: bool, message: string}
     */
    public function verifyCode(User $user, string $code): array
    {
        if (!$user->two_factor_code) {
            return [
                'valid'   => false,
                'message' => 'Aucun code de vérification n\'a été généré.',
            ];
        }

        if ($user->two_factor_expires_at && $user->two_factor_expires_at->isPast()) {
            return [
                'valid'   => false,
                'message' => 'Le code de vérification a expiré. Veuillez en demander un nouveau.',
            ];
        }

        if ($user->two_factor_code !== $code) {
            return [
                'valid'   => false,
                'message' => 'Le code de vérification est incorrect.',
            ];
        }

        // Mark 2FA as verified
        $user->update([
            'two_factor_code'        => null,
            'two_factor_expires_at'  => null,
            'two_factor_verified_at' => now(),
        ]);

        return [
            'valid'   => true,
            'message' => 'Code vérifié avec succès.',
        ];
    }

    /**
     * Resend a new 2FA code (invalidates previous one).
     */
    public function resendCode(User $user): void
    {
        $this->sendCode($user);
    }

    /**
     * ─── Anonymous Code Handling (For Registration Flow) ────────────────
     */

    public function sendAnonymousCode(string $email, string $firstName = null, string $lastName = null): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $ttl  = config('two_factor.code_ttl', 15);

        // Store code and names in cache keyed by email
        Cache::put("reg_code_{$email}", [
            'code' => $code,
            'first_name' => $firstName,
            'last_name' => $lastName
        ], now()->addMinutes($ttl));

        // Send notification to the raw email address
        Notification::route('mail', $email)
            ->notify(new TwoFactorCodeNotification($code, $firstName, $lastName));

        return $code;
    }

    public function verifyAnonymousCode(string $email, string $code): bool
    {
        $data = Cache::get("reg_code_{$email}");

        if (!$data || $data['code'] !== $code) {
            return false;
        }

        // Clear code after successful verification
        Cache::forget("reg_code_{$email}");

        return true;
    }
}
