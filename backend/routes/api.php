<?php

use App\Http\Controllers\Auth\LarkAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/auth/lark/callback', [LarkAuthController::class, 'callback']);

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user()->load('roles');
    });
    
    Route::post('/auth/logout', [LarkAuthController::class, 'logout']);
    
    // Module routes will be added here by their service providers
});
