<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\ActivityLogController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\RolePermissionController;

/*
|--------------------------------------------------------------------------
| Identity Service — API Routes (v1)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // ─── Public Routes (No Authentication) ───────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/register/verify', [AuthController::class, 'verifyRegistration']);
        Route::post('/register/resend', [AuthController::class, 'resendRegistrationCode']);
        Route::post('/two-factor/verify', [AuthController::class, 'verifyTwoFactor']);
        Route::post('/two-factor/resend', [AuthController::class, 'resendTwoFactor']);
        Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword']);
        Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);
    });

    // ─── User Management (Public for Dev) ─────────────────────────────
    Route::apiResource('users', UserController::class);

    // ─── Activity History (Public for Dev) ───────────────────────────
    Route::prefix('history')->group(function () {
        Route::get('/', [ActivityLogController::class, 'index']);
        Route::get('/stats', [ActivityLogController::class, 'stats']);
        Route::get('/filters', [ActivityLogController::class, 'filters']);
        Route::get('/export', [ActivityLogController::class, 'export']);
        Route::delete('/purge', [ActivityLogController::class, 'purge']);
        Route::get('/{id}', [ActivityLogController::class, 'show'])->where('id', '[0-9]+');
    });

    // ─── Protected Routes (Require Valid Token) ──────────────────────
    Route::middleware('auth:sanctum')->group(function () {

        Route::prefix('auth')->group(function () {
            Route::post('/force-change-password', [AuthController::class, 'forceChangePassword'])
                ->middleware('ability:password:change');
            Route::post('/change-password', [AuthController::class, 'changePassword'])
                ->middleware('active');
            Route::get('/me', [AuthController::class, 'me'])->middleware('active');
            Route::post('/logout', [AuthController::class, 'logout']);
        });

        // ─── User Validation (Inter-Service) ────────────────────────
        Route::prefix('users')->middleware('active')->group(function () {
            Route::get('/validate/{id}', function ($id) {
                $user = \App\Models\User::select('id', 'first_name', 'last_name', 'email', 'role', 'role_type', 'status')
                    ->where('id', $id)
                    ->where('status', 'active')
                    ->first();

                if (!$user) {
                    return response()->json(['valid' => false, 'message' => 'Utilisateur introuvable ou inactif.'], 404);
                }

                return response()->json(['valid' => true, 'user' => $user]);
            });
        });

    });

    // ─── Roles & Permissions (Temporary bypass for dev) ──────────────
    Route::prefix('roles-permissions')->group(function () {
        Route::get('/roles', [RolePermissionController::class, 'index']);
        Route::post('/roles', [RolePermissionController::class, 'store']);
        Route::put('/roles/{id}', [RolePermissionController::class, 'update']);
        Route::delete('/roles/{id}', [RolePermissionController::class, 'destroy']);

        Route::get('/roles/{id}/users', [RolePermissionController::class, 'roleUsers']);
        Route::get('/permissions', [RolePermissionController::class, 'getPermissions']);
        Route::post('/permissions/sync', [RolePermissionController::class, 'syncPermissions']);
        Route::get('/roles/{role}/users', [RolePermissionController::class, 'getRoleUsers']);
        Route::get('/permissions', [RolePermissionController::class, 'getPermissions']);


        // Temporary helper for dev
        Route::post('/seed', [RolePermissionController::class, 'seed']);
    });

    // ─── Health Check ────────────────────────────────────────────────
    Route::get('/health', function () {
        return response()->json([
            'service' => 'identity',
            'status' => 'healthy',
            'time' => now()->toIso8601String(),
        ]);
    });
});

