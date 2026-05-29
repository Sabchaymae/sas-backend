<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;

class PasswordResetController extends Controller
{
    /**
     * POST /api/v1/auth/forgot-password
     *
     * Send a password reset link to the user's email.
     * Always returns 200 to prevent email enumeration.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $email = $request->validated('email');
        $user  = User::where('email', $email)->where('status', User::STATUS_ACTIVE)->first();

        if ($user) {
            // Throttle: max 1 reset email per minute
            $recentToken = DB::table('password_reset_tokens')
                ->where('email', $email)
                ->where('created_at', '>', now()->subMinute())
                ->first();

            if (!$recentToken) {
                // Delete any existing tokens
                DB::table('password_reset_tokens')->where('email', $email)->delete();

                // Generate a new token
                $token = Str::random(64);
                DB::table('password_reset_tokens')->insert([
                    'email'      => $email,
                    'token'      => Hash::make($token),
                    'created_at' => now(),
                ]);

                $user->notify(new ResetPasswordNotification($token));
            }
        }

        // Always return success to prevent email enumeration
        return response()->json([
            'success' => true,
            'message' => 'Si un compte existe avec cette adresse email, un lien de réinitialisation a été envoyé.',
        ]);
    }

    /**
     * POST /api/v1/auth/reset-password
     *
     * Reset the user's password using the token from the email.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $data  = $request->validated();
        $user  = User::where('email', $data['email'])->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Token de réinitialisation invalide ou expiré.',
            ], 422);
        }

        // Find the token record
        $tokenRecord = DB::table('password_reset_tokens')
            ->where('email', $data['email'])
            ->first();

        if (!$tokenRecord || !Hash::check($data['token'], $tokenRecord->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Token de réinitialisation invalide ou expiré.',
            ], 422);
        }

        // Check token expiry (60 minutes)
        if (now()->diffInMinutes($tokenRecord->created_at) > 60) {
            DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

            return response()->json([
                'success' => false,
                'message' => 'Le token de réinitialisation a expiré. Veuillez en demander un nouveau.',
            ], 422);
        }

        // Update the password
        $user->update([
            'password'            => $data['password'],
            'password_changed_at' => now(),
        ]);

        // Delete the used token
        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

        // Revoke all existing tokens (security: force re-login)
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe réinitialisé avec succès. Vous pouvez maintenant vous connecter.',
        ]);
    }
}
