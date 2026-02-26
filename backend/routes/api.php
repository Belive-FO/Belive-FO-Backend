<?php

use App\Http\Controllers\Auth\LarkAuthController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web'])->group(function () {
    // Public auth routes
    Route::post('/auth/lark/callback', [LarkAuthController::class, 'callback']);
    Route::post('/auth/login', [LarkAuthController::class, 'login'])
        ->middleware('throttle:5,1');

    // Protected auth routes (require authentication)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [LarkAuthController::class, 'logout'])
            ->middleware('throttle:10,1');

        Route::get('/user', [LarkAuthController::class, 'me']);
    });
});
