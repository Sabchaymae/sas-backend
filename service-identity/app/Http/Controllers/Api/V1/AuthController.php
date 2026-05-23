<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\VerifyTwoFactorRequest;
use App\Http\Requests\Auth\VerifyRegistrationRequest;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Services\AuthService;
use App\Services\ActivityLogService;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService,
    ) {}

    /**
     * POST /api/v1/auth/login
     *
     * Authenticate user. Returns one of:
     * - action: "authenticated"          → full token
     * - action: "two_factor_required"    → 2FA code sent
     * - action: "force_password_change"  → temporary token
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->attemptLogin(
            login:    $request->validated('login'),
            password: $request->validated('password'),
            ip:       $request->ip(),
        );

        $statusCode = $result['success'] ? 200 : 401;

        if (($result['action'] ?? '') === 'account_locked')   $statusCode = 429;
        if (($result['action'] ?? '') === 'account_inactive') $statusCode = 403;

        // ── Activity log ──────────────────────────────────────────────
        $actor = [
            'id'   => $result['user']['id']        ?? null,
            'name' => $result['user']['full_name']  ?? $request->validated('login'),
            'role' => $result['user']['role']       ?? 'inconnu',
        ];

        match ($result['action'] ?? '') {
            'authenticated'         => ActivityLogService::connexion('Connexion réussie', "Connexion depuis {$request->ip()}.", $actor),
            'two_factor_required'   => ActivityLogService::connexion('Vérification 2FA initiée', "Code 2FA envoyé par email.", $actor),
            'force_password_change' => ActivityLogService::log(ActivityLog::TYPE_MODIFICATION, 'Changement de mot de passe requis', ActivityLog::MODULE_SECURITY, "Connexion avec mot de passe temporaire.", $actor),
            'account_locked'        => ActivityLogService::anomalie('Compte verrouillé', "Trop de tentatives — compte verrouillé.", $actor),
            'account_inactive'      => ActivityLogService::refus('Accès refusé — compte inactif', "Connexion sur un compte non actif.", $actor),
            default => $result['success'] ? null : ActivityLogService::anomalie('Tentative de connexion échouée', "Identifiants incorrects depuis {$request->ip()}.", $actor),
        };

        return response()->json($result, $statusCode);
    }

    /**
     * POST /api/v1/auth/register
     *
     * Submit an access request (pending admin approval).
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        if ($result['success'] ?? false) {
            ActivityLogService::log(
                ActivityLog::TYPE_SOUSCRIPTION,
                "Nouvelle demande d'accès",
                ActivityLog::MODULE_AUTH,
                "Inscription soumise pour validation.",
                ['id' => null, 'name' => $request->validated('first_name').' '.$request->validated('last_name'), 'role' => $request->validated('role', 'inconnu')]
            );
            return response()->json($result, 201);
        }

        return response()->json($result, 422);
    }

    /**
     * POST /api/v1/auth/register/verify
     *
     * Verify email after registration (and finally create the user).
     */
    public function verifyRegistration(VerifyRegistrationRequest $request): JsonResponse
    {
        $result = $this->authService->verifyRegistrationEmail(
            email: $request->validated('email'),
            code:  $request->validated('code')
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * POST /api/v1/auth/register/resend
     *
     * Resend verification code for pending registration.
     */
    public function resendRegistrationCode(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);
        
        $result = $this->authService->resendRegistrationCode($request->input('email'));

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * POST /api/v1/auth/two-factor/verify
     *
     * Verify the 2FA code and receive full auth token.
     */
    public function verifyTwoFactor(VerifyTwoFactorRequest $request): JsonResponse
    {
        $user = \App\Models\User::findOrFail($request->validated('user_id'));

        $result = $this->authService->completeTwoFactor(
            user: $user,
            code: $request->validated('code'),
            ip:   $request->ip(),
        );

        $actor = ['id' => $user->id, 'name' => $user->full_name, 'role' => $user->role];

        $result['success']
            ? ActivityLogService::connexion('Vérification 2FA réussie', "Code validé avec succès.", $actor)
            : ActivityLogService::anomalie('Échec 2FA', "Code incorrect ou expiré.", $actor);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * POST /api/v1/auth/two-factor/resend
     *
     * Resend 2FA code.
     */
    public function resendTwoFactor(Request $request): JsonResponse
    {
        $request->validate(['user_id' => 'required|integer|exists:users,id']);

        $user = \App\Models\User::findOrFail($request->input('user_id'));

        if (!$user->hasTwoFactorEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'La vérification à deux facteurs n\'est pas activée pour ce compte.',
            ], 400);
        }

        app(\App\Services\TwoFactorService::class)->resendCode($user);

        return response()->json([
            'success' => true,
            'message' => 'Un nouveau code a été envoyé.',
            'channel' => $user->getPreferredTwoFactorChannel(),
        ]);
    }

    /**
     * POST /api/v1/auth/force-change-password
     *
     * Change password for new accounts (requires temporary token).
     */
    public function forceChangePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->must_change_password) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun changement de mot de passe requis.',
            ], 400);
        }

        $result = $this->authService->forceChangePassword(
            user:        $user,
            newPassword: $request->validated('password'),
        );

        if ($result['success'] ?? false) {
            ActivityLogService::modification('Changement de mot de passe forcé', ActivityLog::MODULE_SECURITY, "Nouveau mot de passe défini (obligation initiale).");
        }

        return response()->json($result);
    }

    /**
     * POST /api/v1/auth/change-password
     *
     * Voluntary password change (authenticated user).
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $result = $this->authService->changePassword(
            user:            $request->user(),
            currentPassword: $request->validated('current_password'),
            newPassword:     $request->validated('password'),
        );

        if ($result['success'] ?? false) {
            ActivityLogService::modification('Changement de mot de passe', ActivityLog::MODULE_SECURITY, "Mot de passe modifié volontairement.");
        }

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * POST /api/v1/auth/logout
     *
     * Revoke current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        ActivityLogService::connexion('Déconnexion', "L'utilisateur a clôturé sa session.", ['id' => $user->id, 'name' => $user->full_name, 'role' => $user->role]);

        $result = $this->authService->logout($user);

        return response()->json($result);
    }

    /**
     * GET /api/v1/auth/me
     *
     * Get authenticated user profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'user'    => [
                'id'                   => $user->id,
                'first_name'           => $user->first_name,
                'last_name'            => $user->last_name,
                'full_name'            => $user->full_name,
                'email'                => $user->email,
                'phone'                => $user->phone,
                'role'                 => $user->role,
                'role_type'            => $user->role_type,
                'status'               => $user->status,
                'two_factor_enabled'   => $user->two_factor_enabled,
                'must_change_password' => $user->must_change_password,
                'email_verified_at'    => $user->email_verified_at,
                'last_login_at'        => $user->last_login_at,
                'created_at'           => $user->created_at,
            ],
        ]);
    }
}
