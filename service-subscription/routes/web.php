<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SouscriptionController;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/souscriptions', [SouscriptionController::class, 'store']);
Route::get('/souscriptions', [SouscriptionController::class, 'index']);
Route::get('/souscriptions/{id}', [SouscriptionController::class, 'show']);
Route::put('/souscriptions/{id}', [SouscriptionController::class, 'update']);
Route::post('/souscriptions/{id}', [SouscriptionController::class, 'update']); // FormData _method=PUT spoofing
Route::post('/souscriptions/{id}/status', [SouscriptionController::class, 'updateStatus']);
Route::put('/souscriptions/{id}/status', [SouscriptionController::class, 'updateStatus']);
Route::delete('/souscriptions/{id}', [SouscriptionController::class, 'destroy']);
