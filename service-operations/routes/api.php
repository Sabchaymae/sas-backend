<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\TaskOptimizationController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceAnomalyController;
use App\Http\Controllers\DeviceController;

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Attendance & Employee management
    Route::apiResource('employees', EmployeeController::class);
    Route::apiResource('devices', DeviceController::class);
    Route::get('attendance', [AttendanceController::class, 'index']);
    Route::post('attendance', [AttendanceController::class, 'store'])->withoutMiddleware('auth:sanctum'); // Allow Python service to post without auth for simplicity, or we can use a token
    Route::get('anomalies', [AttendanceAnomalyController::class, 'index']);
    Route::post('anomalies', [AttendanceAnomalyController::class, 'store'])->withoutMiddleware('auth:sanctum');

    Route::get('tasks/optimization/suggestions', [TaskOptimizationController::class, 'getSuggestions']);
    Route::post('tasks/optimization/apply', [TaskOptimizationController::class, 'applyOptimizations']);
    Route::get('tasks/optimization/dashboard', [TaskOptimizationController::class, 'getDashboardData']);
    Route::apiResource('tasks', TaskController::class);
    Route::apiResource('incidents', IncidentController::class);
    Route::post('tasks/{task}/comments', [TaskController::class, 'storeComment']);
    Route::get('notifications', [ProductController::class, 'getNotifications']);
    Route::post('notifications/{id}/read', [ProductController::class, 'markNotificationAsRead']);
    Route::get('stock/movements', [ProductController::class, 'movements']);
    Route::post('products/{id}/movement', [ProductController::class, 'handleMovement']);
    Route::apiResource('products', ProductController::class);
});

// Temporary test routes without auth (for testing only!)
Route::prefix('v1/test')->group(function () {
    Route::get('attendance', [AttendanceController::class, 'index']);
    Route::post('attendance', [AttendanceController::class, 'store']);
    Route::get('employees', [EmployeeController::class, 'index']);
    Route::get('anomalies', [AttendanceAnomalyController::class, 'index']);
    Route::get('tasks', [TaskController::class, 'index']);
    Route::post('tasks', [TaskController::class, 'store']);
    Route::put('tasks/{id}', [TaskController::class, 'update']);
    Route::delete('tasks/{id}', [TaskController::class, 'destroy']);
    Route::get('incidents', [IncidentController::class, 'index']);
    Route::post('incidents', [IncidentController::class, 'store']);
    Route::put('incidents/{id}', [IncidentController::class, 'update']);
    Route::delete('incidents/{id}', [IncidentController::class, 'destroy']);
    Route::get('tasks/optimization/suggestions', [TaskOptimizationController::class, 'getSuggestions']);
    Route::post('tasks/optimization/apply', [TaskOptimizationController::class, 'applyOptimizations']);
    Route::get('tasks/optimization/dashboard', [TaskOptimizationController::class, 'getDashboardData']);
});
