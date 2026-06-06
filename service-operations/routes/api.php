<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\IncidentController;

Route::prefix('v1')->group(function () {
    Route::apiResource('tasks', TaskController::class);
    Route::apiResource('incidents', IncidentController::class);
    Route::post('tasks/{task}/comments', [TaskController::class, 'addComment']);
    Route::get('notifications', [ProductController::class, 'getNotifications']);
    Route::post('notifications/{id}/read', [ProductController::class, 'markNotificationAsRead']);
    Route::get('stock/movements', [ProductController::class, 'movements']);
    Route::post('products/{id}/movement', [ProductController::class, 'handleMovement']);
    Route::apiResource('products', ProductController::class);
});
