<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\ActivityLogController;
<<<<<<< HEAD
use App\Http\Controllers\Api\V1\UserController;use App\Http\Controllers\Api\V1\RolePermissionController;
use App\Http\Controllers\Api\V1\DashboardController;
=======
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\RolePermissionController;
>>>>>>> import/master

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

<<<<<<< HEAD
    // ─── User Management (Protected by Permission Middleware) ─────────
    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('users', [UserController::class, 'index'])->middleware('permission:Utilisateurs,Lecture');
        Route::post('users', [UserController::class, 'store'])->middleware('permission:Utilisateurs,Création');
        Route::get('users/{user}', [UserController::class, 'show'])->middleware('permission:Utilisateurs,Lecture');
        Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update'])->middleware('permission:Utilisateurs,Modification');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('permission:Utilisateurs,Suppression');
    });
=======
    // ─── User Management (Public for Dev) ─────────────────────────────
    Route::apiResource('users', UserController::class);
>>>>>>> import/master

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
<<<<<<< HEAD
        Route::get('/my-permissions', [RolePermissionController::class, 'myPermissions'])->middleware('auth:sanctum');
=======
>>>>>>> import/master


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
<<<<<<< HEAD

    // ─── Dashboard Routes ────────────────────────────────────────────
    Route::prefix('dashboard')->middleware('auth:sanctum')->group(function () {
        Route::get('/kpis', [DashboardController::class, 'getKPIs']);
        Route::get('/alerts', [DashboardController::class, 'getAlerts']);
        Route::get('/activity', [DashboardController::class, 'getActivity']);
        Route::get('/performance', [DashboardController::class, 'getPerformance']);
        Route::get('/ai-suggestions', [DashboardController::class, 'getAISuggestions']);
        Route::get('/charts', [DashboardController::class, 'getCharts']);
        Route::get('/system-health', [DashboardController::class, 'getSystemHealth']);
    });
=======
>>>>>>> import/master
});

