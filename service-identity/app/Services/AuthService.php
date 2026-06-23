<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;

class AuthService
{
    public function __construct(
        protected TwoFactorService $twoFactorService,
    ) {}

    /**
     * Attempt to authenticate a user by credentials.
     *
     * Returns a result array describing the auth state.
     *
     * @return array{success: bool, action: string, message: string, user?: User, token?: string}
     */
    public function attemptLogin(string $login, string $password, ?string $ip = null): array
    {
        $user = User::where('email', $login)->orWhere('cin', $login)->first();

        if (!$user) {
            return $this->fail('invalid_credentials', 'Email ou mot de passe incorrect.');
        }

        // ── Account locked? ─────────────────────────────────────────
        if ($user->isLocked()) {
            $minutes = $user->locked_until->diffInMinutes(now());
            return $this->fail(
                'account_locked',
                "Compte verrouillé. Réessayez dans {$minutes} minute(s)."
            );
        }

        // ── Account not active? ─────────────────────────────────────
        if (!$user->isActive()) {
            return $this->fail(
                'account_inactive',
                $this->getStatusMessage($user->status)
            );
        }

        // ── Password check ──────────────────────────────────────────
        if (!Hash::check($password, $user->password)) {
            $user->incrementLoginAttempts();

            $remaining = User::MAX_LOGIN_ATTEMPTS - $user->login_attempts;
            $message   = $remaining > 0
                ? "Email ou mot de passe incorrect. {$remaining} tentative(s) restante(s)."
                : 'Compte verrouillé suite à trop de tentatives échouées.';

            return $this->fail('invalid_credentials', $message);
        }

        // ── Reset login attempts on success ─────────────────────────
        $user->resetLoginAttempts();

        // ── Must change password? ───────────────────────────────────
        if ($user->must_change_password) {
            // Issue a temporary token scoped to password change only
            $tempToken = $user->createToken('password-change', ['password:change'], now()->addMinutes(15));

            return [
                'success' => true,
                'action'  => 'force_password_change',
                'message' => 'Vous devez changer votre mot de passe avant de continuer.',
                'user'    => $this->formatUser($user),
                'token'   => $tempToken->plainTextToken,
            ];
        }

        // ── 2FA required? ───────────────────────────────────────────
        if ($user->hasTwoFactorEnabled()) {
            $this->twoFactorService->sendCode($user);

            return [
                'success' => true,
                'action'  => 'two_factor_required',
                'message' => 'Un code de vérification a été envoyé.',
                'user_id' => $user->id,
                'channel' => $user->getPreferredTwoFactorChannel(),
            ];
        }

        // ── Standard login — issue full token ───────────────────────
        return $this->issueFullToken($user, $ip);
    }

    /**
     * Complete 2FA verification and issue full token.
     */
    public function completeTwoFactor(User $user, string $code, ?string $ip = null): array
    {
        $result = $this->twoFactorService->verifyCode($user, $code);

        if (!$result['valid']) {
            return $this->fail('invalid_2fa', $result['message']);
        }

        return $this->issueFullToken($user, $ip);
    }

    /**
     * Register a new access request (pending approval).
     */
    public function register(array $data): array
    {
        $email = $data['email'];

        // Check if email or phone already exists in DB to prevent duplicates early
        if (User::where('email', $email)->exists()) {
            return $this->fail('email_exists', 'Cette adresse email est déjà utilisée.');
        }
        
        if (User::where('phone', $data['phone'] ?? null)->orWhere('telephone', $data['phone'] ?? null)->exists()) {
            return $this->fail('phone_exists', 'Ce numéro de téléphone est déjà utilisé.');
        }

        // Store registration data in cache for 30 minutes
        Cache::put("pending_reg_data_{$email}", $data, now()->addMinutes(30));

        // Send email verification code
        $this->twoFactorService->sendAnonymousCode($email, $data['first_name'], $data['last_name']);

        return [
            'success' => true,
            'action'  => 'email_verification_required',
            'message' => 'Un code de vérification a été envoyé à votre adresse email. Veuillez le vérifier pour finaliser votre demande.',
            'email'   => $email, // Pass email instead of user_id
        ];
    }

    /**
     * Resend verification code for a pending registration.
     */
    public function resendRegistrationCode(string $email): array
    {
        if (!Cache::has("pending_reg_data_{$email}")) {
            return $this->fail('expired', 'Votre session d\'inscription a expiré. Veuillez recommencer.');
        }

        $data = Cache::get("pending_reg_data_{$email}");
        $this->twoFactorService->sendAnonymousCode($email, $data['first_name'] ?? null, $data['last_name'] ?? null);

        return [
            'success' => true,
            'message' => 'Un nouveau code a été envoyé.',
        ];
    }

    /**
     * Verify registration email and complete the request submission (Store in DB).
     */
    public function verifyRegistrationEmail(string $email, string $code): array
    {
        // 1. Verify code
        if (!$this->twoFactorService->verifyAnonymousCode($email, $code)) {
            return $this->fail('invalid_code', 'Code de vérification incorrect ou expiré.');
        }

        // 2. Retrieve data from cache
        $data = Cache::get("pending_reg_data_{$email}");
        if (!$data) {
            return $this->fail('expired', 'Les données d\'inscription ont expiré. Veuillez recommencer.');
        }

        // 3. Finally create the user in the database
        $user = User::create([
            'first_name'    => $data['first_name'],
            'last_name'     => $data['last_name'],
            'email'         => $data['email'],
            'cin'           => $data['cin'],
            'phone'         => $data['phone'] ?? null,
            'password'      => $data['password'],
            'access_reason' => $data['access_reason'] ?? null,
            'role'          => $data['role'],
            'role_type'     => in_array($data['role'], User::INTERNAL_ROLES) ? User::TYPE_INTERNE : User::TYPE_EXTERNE,
            'status'        => User::STATUS_PENDING,
            'email_verified_at' => now(),
        ]);

        // 4. Cleanup cache
        Cache::forget("pending_reg_data_{$email}");

        return [
            'success' => true,
            'action'  => 'registration_pending',
            'message' => 'Email vérifié avec succès. Votre demande d\'accès a été soumise. Un administrateur l\'examinera prochainement.',
            'user'    => $this->formatUser($user),
        ];
    }

    /**
     * Force password change for new accounts.
     */
    public function forceChangePassword(User $user, string $newPassword): array
    {
        $user->update([
            'password'             => $newPassword,
            'must_change_password' => false,
            'password_changed_at'  => now(),
        ]);

        // Revoke the temporary token
        $user->currentAccessToken()?->delete();

        // Issue a fresh full-access token
        $token = $user->createToken('auth-token', ['*'], now()->addHours(24));

        $this->recordLogin($user, request()->ip());

        return [
            'success' => true,
            'action'  => 'authenticated',
            'message' => 'Mot de passe mis à jour avec succès.',
            'user'    => $this->formatUser($user),
            'token'   => $token->plainTextToken,
        ];
    }

    /**
     * Change password (voluntary — authenticated user).
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): array
    {
        if (!Hash::check($currentPassword, $user->password)) {
            return $this->fail('invalid_password', 'Le mot de passe actuel est incorrect.');
        }

        $user->update([
            'password'            => $newPassword,
            'password_changed_at' => now(),
        ]);

        // Revoke all existing tokens and issue a new one
        $user->tokens()->delete();
        $token = $user->createToken('auth-token', ['*'], now()->addHours(24));

        return [
            'success' => true,
            'action'  => 'password_changed',
            'message' => 'Mot de passe modifié avec succès.',
            'token'   => $token->plainTextToken,
        ];
    }

    /**
     * Logout — revoke current token.
     */
    public function logout(User $user): array
    {
        $user->currentAccessToken()?->delete();

        return [
            'success' => true,
            'message' => 'Déconnexion réussie.',
        ];
    }

    // ─── Private Helpers ─────────────────────────────────────────────

    private function issueFullToken(User $user, ?string $ip): array
    {
        $token = $user->createToken('auth-token', ['*'], now()->addHours(24));

        $this->recordLogin($user, $ip);

        return [
            'success' => true,
            'action'  => 'authenticated',
            'message' => 'Connexion réussie.',
            'user'    => $this->formatUser($user),
            'token'   => $token->plainTextToken,
        ];
    }

    private function recordLogin(User $user, ?string $ip): void
    {
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ]);
    }

    private function formatUser(User $user): array
    {
        return [
            'id'                    => $user->id,
            'first_name'            => $user->first_name,
            'last_name'             => $user->last_name,
            'full_name'             => $user->full_name,
            'email'                 => $user->email,
            'cin'                   => $user->cin,
            'phone'                 => $user->phone,
            'role'                  => $user->role,
            'role_type'             => $user->role_type,
            'status'                => $user->status,
            'two_factor_enabled'    => $user->two_factor_enabled,
            'must_change_password'  => $user->must_change_password,
            'email_verified_at'     => $user->email_verified_at,
            'last_login_at'         => $user->last_login_at,
        ];
    }

    private function fail(string $action, string $message): array
    {
        return [
            'success' => false,
            'action'  => $action,
            'message' => $message,
        ];
    }

    private function getStatusMessage(string $status): string
    {
        return match ($status) {
            User::STATUS_PENDING   => 'Votre compte est en attente d\'approbation par un administrateur.',
            User::STATUS_SUSPENDED => 'Votre compte a été suspendu. Contactez l\'administrateur.',
            User::STATUS_REJECTED  => 'Votre demande d\'accès a été refusée.',
            default                => 'Compte inactif.',
        };
    }
}
