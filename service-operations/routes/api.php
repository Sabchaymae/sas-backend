<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;

Route::prefix('v1')->group(function () {
    Route::apiResource('products', ProductController::class);
});
