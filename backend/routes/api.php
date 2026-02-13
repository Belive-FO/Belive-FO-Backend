<?php

use App\Http\Controllers\Auth\LarkAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public auth routes
Route::post('/auth/lark/callback', [LarkAuthController::class, 'callback']);
Route::post('/auth/login', [LarkAuthController::class, 'login'])
    ->middleware('throttle:5,1');
Route::post('/auth/logout', [LarkAuthController::class, 'logout'])
    ->middleware('throttle:10,1');

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user()->load('roles');
    });

    // Module routes will be added here by their service providers
});
